<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CatalogBackupManager
{
    public const TABLES = [
        'tenants',
        'users',
        'categories',
        'menu_items',
        'products',
        'product_media',
        'audit_logs',
        'error_occurrences',
        'privacy_requests',
        'communications',
        'communication_messages',
        'communication_recipients',
        'push_subscriptions',
    ];

    public function create(): array
    {
        return $this->createArchive();
    }

    public function createTenant(Tenant $tenant): array
    {
        return $this->createArchive($tenant);
    }

    private function createArchive(?Tenant $tenant = null): array
    {
        $this->ensureRequirements();
        $archive = new EncryptedBackupArchive;
        $temporary = $this->temporaryFile();
        $writer = null;
        $rows = 0;
        $files = 0;
        $bytes = 0;

        try {
            $writer = $archive->writer($temporary);
            $writer->add([
                'type' => 'metadata',
                'format' => 1,
                'scope' => $tenant ? 'tenant' : 'general',
                'tenant_id' => $tenant?->getKey(),
                'tenant_slug' => $tenant?->slug,
                'created_at' => now()->utc()->toIso8601String(),
                'database_driver' => DB::getDriverName(),
                'tables' => self::TABLES,
                'migrations' => Schema::hasTable('migrations') ? DB::table('migrations')->orderBy('id')->pluck('migration')->all() : [],
                'app_key_fingerprint' => hash('sha256', (string) config('app.key')),
            ]);

            DB::transaction(function () use ($writer, $tenant, &$rows): void {
                foreach (self::TABLES as $table) {
                    if (! Schema::hasTable($table)) {
                        throw new RuntimeException("A tabela obrigatória {$table} não existe.");
                    }

                    $this->queryForTable($table, $tenant)->orderBy('id')->chunkById(200, function ($records) use ($writer, $table, &$rows): void {
                        foreach ($records as $record) {
                            $writer->add(['type' => 'row', 'table' => $table, 'data' => (array) $record]);
                            $rows++;
                        }
                    });
                }
            });

            $uploadPrefix = $tenant ? 'tenants/'.$tenant->getKey() : 'tenants';
            foreach (Storage::disk('uploads')->allFiles($uploadPrefix) as $path) {
                $this->assertSafeUploadPath($path);
                $stream = Storage::disk('uploads')->readStream($path);
                if ($stream === false) {
                    throw new RuntimeException("Não foi possível ler o arquivo {$path}.");
                }

                $writer->add(['type' => 'file_start', 'path' => $path]);
                $hash = hash_init('sha256');
                $size = 0;
                $index = 0;

                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 524288);
                        if ($chunk === false) {
                            throw new RuntimeException("Falha durante a leitura de {$path}.");
                        }
                        if ($chunk === '') {
                            continue;
                        }

                        hash_update($hash, $chunk);
                        $size += strlen($chunk);
                        $writer->add([
                            'type' => 'file_chunk',
                            'path' => $path,
                            'index' => $index++,
                            'data' => base64_encode($chunk),
                        ]);
                    }
                } finally {
                    fclose($stream);
                }

                $writer->add([
                    'type' => 'file_end',
                    'path' => $path,
                    'size' => $size,
                    'sha256' => hash_final($hash),
                ]);
                $files++;
                $bytes += $size;
            }

            $writer->close(['rows' => $rows, 'files' => $files, 'file_bytes' => $bytes]);
            $filename = $this->archiveDirectory($tenant).'/catalog-'.($tenant ? $tenant->slug.'-' : 'general-').now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.catalog-backup';
            $stream = fopen($temporary, 'rb');

            if ($stream === false || ! Storage::disk($this->disk())->writeStream($filename, $stream)) {
                throw new RuntimeException('Não foi possível gravar o backup no disco configurado.');
            }

            fclose($stream);

            try {
                $this->verify($filename);
            } catch (Throwable $exception) {
                Storage::disk($this->disk())->delete($filename);

                throw $exception;
            }

            return ['path' => $filename, 'rows' => $rows, 'files' => $files, 'bytes' => filesize($temporary) ?: 0];
        } finally {
            $writer?->abort();
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function verify(string $path): array
    {
        $temporary = $this->copyToTemporary($path);

        try {
            $metadata = null;
            $footer = (new EncryptedBackupArchive)->read($temporary, function (array $record) use (&$metadata): void {
                if (($record['type'] ?? null) === 'metadata') {
                    $metadata = $record;
                }
            });

            if (! $metadata || ($metadata['format'] ?? null) !== 1) {
                throw new RuntimeException('Os metadados obrigatórios do backup não foram encontrados.');
            }

            return ['metadata' => $metadata, 'footer' => $footer];
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function restore(string $path): array
    {
        $this->ensureEmptyTarget();
        $temporary = $this->copyToTemporary($path);
        $archive = new EncryptedBackupArchive;
        $verified = $this->verifyLocal($archive, $temporary);
        if (($verified['metadata']['scope'] ?? 'general') !== 'general') {
            unlink($temporary);
            throw new RuntimeException('Um backup de empresa não pode ser usado na restauração geral.');
        }
        $expectedFingerprint = hash('sha256', (string) config('app.key'));

        if (! hash_equals((string) ($verified['metadata']['app_key_fingerprint'] ?? ''), $expectedFingerprint)) {
            unlink($temporary);
            throw new RuntimeException('A APP_KEY atual não corresponde à utilizada no backup.');
        }

        $currentMigrations = DB::table('migrations')->pluck('migration')->all();
        $missingMigrations = array_diff($verified['metadata']['migrations'] ?? [], $currentMigrations);
        if ($missingMigrations !== []) {
            unlink($temporary);
            throw new RuntimeException('O código de destino não possui todas as migrations exigidas pelo backup.');
        }

        $stage = storage_path('app/restore-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($stage);
        $currentFile = null;
        $currentStream = null;
        $currentHash = null;
        $currentSize = 0;
        $currentIndex = 0;
        $restoredPaths = [];
        $rowCount = 0;
        $fileCount = 0;

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($archive, $temporary, $stage, $verified, &$currentFile, &$currentStream, &$currentHash, &$currentSize, &$currentIndex, &$rowCount, &$fileCount, &$restoredPaths): void {
                $archive->read($temporary, function (array $record) use ($stage, &$currentFile, &$currentStream, &$currentHash, &$currentSize, &$currentIndex, &$rowCount, &$fileCount): void {
                    $type = $record['type'] ?? null;

                    if ($type === 'row') {
                        $table = (string) ($record['table'] ?? '');
                        if (! in_array($table, self::TABLES, true) || ! is_array($record['data'] ?? null)) {
                            throw new RuntimeException('O backup contém uma linha de banco inválida.');
                        }
                        DB::table($table)->insert($record['data']);
                        $rowCount++;

                        return;
                    }

                    if ($type === 'file_start') {
                        if (is_resource($currentStream)) {
                            throw new RuntimeException('A estrutura de arquivos do backup é inválida.');
                        }
                        $currentFile = (string) ($record['path'] ?? '');
                        $this->assertSafeUploadPath($currentFile);
                        $destination = $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $currentFile);
                        File::ensureDirectoryExists(dirname($destination));
                        $currentStream = fopen($destination, 'wb');
                        if ($currentStream === false) {
                            throw new RuntimeException("Não foi possível preparar {$currentFile} para restauração.");
                        }
                        $currentHash = hash_init('sha256');
                        $currentSize = 0;
                        $currentIndex = 0;

                        return;
                    }

                    if ($type === 'file_chunk') {
                        if (! is_resource($currentStream) || ($record['path'] ?? null) !== $currentFile || ($record['index'] ?? null) !== $currentIndex) {
                            throw new RuntimeException('A sequência de um arquivo do backup é inválida.');
                        }
                        $chunk = base64_decode((string) ($record['data'] ?? ''), true);
                        if ($chunk === false || fwrite($currentStream, $chunk) === false) {
                            throw new RuntimeException("Falha ao restaurar {$currentFile}.");
                        }
                        hash_update($currentHash, $chunk);
                        $currentSize += strlen($chunk);
                        $currentIndex++;

                        return;
                    }

                    if ($type === 'file_end') {
                        if (! is_resource($currentStream) || ($record['path'] ?? null) !== $currentFile) {
                            throw new RuntimeException('O fechamento de um arquivo do backup é inválido.');
                        }
                        fclose($currentStream);
                        $currentStream = null;
                        $hash = hash_final($currentHash);
                        if (($record['size'] ?? null) !== $currentSize || ! hash_equals((string) ($record['sha256'] ?? ''), $hash)) {
                            throw new RuntimeException("A integridade do arquivo {$currentFile} não foi confirmada.");
                        }
                        $fileCount++;

                        return;
                    }

                    if ($type !== 'metadata') {
                        throw new RuntimeException('O backup contém um tipo de registro desconhecido.');
                    }
                });

                if (is_resource($currentStream)) {
                    fclose($currentStream);
                    throw new RuntimeException('O último arquivo do backup ficou incompleto.');
                }

                if (($verified['footer']['rows'] ?? null) !== $rowCount || ($verified['footer']['files'] ?? null) !== $fileCount) {
                    throw new RuntimeException('A quantidade restaurada não corresponde ao fechamento autenticado do backup.');
                }

                foreach (File::allFiles($stage) as $file) {
                    $relative = str_replace('\\', '/', $file->getRelativePathname());
                    $stream = fopen($file->getPathname(), 'rb');
                    if ($stream === false || ! Storage::disk('uploads')->writeStream($relative, $stream)) {
                        throw new RuntimeException("Não foi possível publicar o arquivo restaurado {$relative}.");
                    }
                    fclose($stream);
                    $restoredPaths[] = $relative;
                }
            }, 3);
        } catch (Throwable $exception) {
            foreach ($restoredPaths as $restoredPath) {
                Storage::disk('uploads')->delete($restoredPath);
            }

            throw $exception;
        } finally {
            Schema::enableForeignKeyConstraints();
            File::deleteDirectory($stage);
            if (is_resource($currentStream)) {
                fclose($currentStream);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return ['rows' => $rowCount, 'files' => $fileCount];
    }

    public function prune(): int
    {
        $disk = Storage::disk($this->disk());
        $files = collect($disk->allFiles($this->directory()))
            ->filter(fn (string $file) => str_ends_with($file, '.catalog-backup'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->values();
        $threshold = now()->subDays((int) config('backup.retention_days', 14))->getTimestamp();
        $minimum = (int) config('backup.minimum_copies', 3);
        $deleted = 0;

        foreach ($files->groupBy(fn (string $file) => dirname($file)) as $scopedFiles) {
            foreach ($scopedFiles->values()->slice($minimum) as $file) {
                if ($disk->lastModified($file) < $threshold && $disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function files(): array
    {
        return $this->generalFiles();
    }

    public function generalFiles(): array
    {
        $disk = Storage::disk($this->disk());

        return collect(array_merge(
            $disk->files($this->directory()),
            $disk->files($this->directory().'/general'),
        ))
            ->filter(fn (string $file) => str_ends_with($file, '.catalog-backup'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->values()
            ->all();
    }

    public function tenantFiles(Tenant $tenant): array
    {
        $disk = Storage::disk($this->disk());

        return collect($disk->files($this->archiveDirectory($tenant)))
            ->filter(fn (string $file) => str_ends_with($file, '.catalog-backup'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file))
            ->values()
            ->all();
    }

    public function resolveGeneralFile(string $filename): string
    {
        return $this->resolveFile($filename, [
            $this->directory().'/general',
            $this->directory(),
        ]);
    }

    public function resolveTenantFile(Tenant $tenant, string $filename): string
    {
        return $this->resolveFile($filename, [$this->archiveDirectory($tenant)]);
    }

    public function diskName(): string
    {
        return $this->disk();
    }

    private function queryForTable(string $table, ?Tenant $tenant): Builder
    {
        $query = DB::table($table);

        if (! $tenant) {
            return $query;
        }

        $tenantId = $tenant->getKey();

        return match ($table) {
            'tenants' => $query->where('id', $tenantId),
            'users', 'categories', 'menu_items', 'products', 'audit_logs', 'error_occurrences', 'privacy_requests', 'communications' => $query->where('tenant_id', $tenantId),
            'product_media' => $query->whereIn('product_id', DB::table('products')->select('id')->where('tenant_id', $tenantId)),
            'communication_messages', 'communication_recipients' => $query->whereIn('communication_id', DB::table('communications')->select('id')->where('tenant_id', $tenantId)),
            'push_subscriptions' => $query->whereIn('user_id', DB::table('users')->select('id')->where('tenant_id', $tenantId)),
            default => throw new RuntimeException("A tabela {$table} não possui uma regra de isolamento para backup de empresa."),
        };
    }

    private function archiveDirectory(?Tenant $tenant = null): string
    {
        return $tenant
            ? $this->directory().'/tenants/'.$tenant->getKey()
            : $this->directory().'/general';
    }

    private function resolveFile(string $filename, array $directories): string
    {
        if ($filename === '' || basename($filename) !== $filename || ! preg_match('/\A[A-Za-z0-9._-]+\.catalog-backup\z/', $filename)) {
            throw new RuntimeException('Nome de arquivo de backup inválido.');
        }

        foreach ($directories as $directory) {
            $path = trim($directory, '/').'/'.$filename;
            if (Storage::disk($this->disk())->exists($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Arquivo de backup não encontrado.');
    }

    private function verifyLocal(EncryptedBackupArchive $archive, string $temporary): array
    {
        $metadata = null;
        $footer = $archive->read($temporary, function (array $record) use (&$metadata): void {
            if (($record['type'] ?? null) === 'metadata') {
                $metadata = $record;
            }
        });

        if (! $metadata) {
            throw new RuntimeException('Metadados ausentes no backup.');
        }

        return ['metadata' => $metadata, 'footer' => $footer];
    }

    private function ensureRequirements(): void
    {
        if (! extension_loaded('openssl') || ! in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('OpenSSL com AES-256-GCM é obrigatório para backups.');
        }

        new EncryptedBackupArchive;

        if (blank(config('app.key'))) {
            throw new RuntimeException('APP_KEY não configurada.');
        }
    }

    private function ensureEmptyTarget(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Execute as migrations antes da restauração; tabela ausente: {$table}.");
            }
            if (DB::table($table)->exists()) {
                throw new RuntimeException("A restauração só é permitida em banco vazio; a tabela {$table} possui dados.");
            }
        }

        if (Storage::disk('uploads')->allFiles('tenants') !== []) {
            throw new RuntimeException('A restauração só é permitida quando a pasta de uploads dos clientes está vazia.');
        }
    }

    private function copyToTemporary(string $path): string
    {
        if (! Storage::disk($this->disk())->exists($path)) {
            throw new RuntimeException('Arquivo de backup não encontrado no disco configurado.');
        }

        $temporary = $this->temporaryFile();
        $source = Storage::disk($this->disk())->readStream($path);
        $destination = fopen($temporary, 'wb');

        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new RuntimeException('Não foi possível abrir o backup para leitura.');
        }

        try {
            if (stream_copy_to_stream($source, $destination) === false) {
                throw new RuntimeException('Não foi possível copiar o backup para verificação.');
            }
        } finally {
            fclose($source);
            fclose($destination);
        }

        return $temporary;
    }

    private function temporaryFile(): string
    {
        File::ensureDirectoryExists(storage_path('app'));
        $path = tempnam(storage_path('app'), 'catalog-backup-');

        if ($path === false) {
            throw new RuntimeException('Não foi possível criar um arquivo temporário protegido.');
        }

        return $path;
    }

    private function assertSafeUploadPath(string $path): void
    {
        $path = str_replace('\\', '/', $path);

        if (! str_starts_with($path, 'tenants/') || str_contains($path, '../') || str_contains($path, "\0")) {
            throw new RuntimeException('O backup contém um caminho de upload inseguro.');
        }
    }

    private function disk(): string
    {
        return (string) config('backup.disk', 'local');
    }

    private function directory(): string
    {
        return trim((string) config('backup.directory', 'backups'), '/');
    }
}
