<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\CatalogBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(CatalogBackupManager $manager): View
    {
        return view('platform.backups.index', [
            'backups' => $this->describe($manager, $manager->generalFiles()),
        ]);
    }

    public function store(CatalogBackupManager $manager): RedirectResponse
    {
        try {
            $backup = $manager->create();
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return back()->with('success', 'Backup geral criado e verificado: '.basename($backup['path'])." ({$backup['rows']} registros e {$backup['files']} arquivos).");
    }

    public function verify(string $backup, CatalogBackupManager $manager): RedirectResponse
    {
        try {
            $path = $manager->resolveGeneralFile($backup);
            $verified = $manager->verify($path);

            if (($verified['metadata']['scope'] ?? 'general') !== 'general') {
                throw new RuntimeException('O arquivo não é um backup geral da plataforma.');
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }

        return back()->with('success', "Integridade confirmada: {$verified['footer']['rows']} registros e {$verified['footer']['files']} arquivos.");
    }

    public function download(string $backup, CatalogBackupManager $manager): StreamedResponse
    {
        try {
            $path = $manager->resolveGeneralFile($backup);
            $verified = $manager->verify($path);
            if (($verified['metadata']['scope'] ?? 'general') !== 'general') {
                abort(404);
            }
        } catch (RuntimeException) {
            abort(404);
        }

        return Storage::disk($manager->diskName())->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function describe(CatalogBackupManager $manager, array $paths): array
    {
        $disk = Storage::disk($manager->diskName());

        return array_map(fn (string $path): array => [
            'path' => $path,
            'filename' => basename($path),
            'size' => $disk->size($path),
            'modified_at' => $disk->lastModified($path),
        ], $paths);
    }
}
