<?php

namespace App\Services;

use Closure;
use RuntimeException;

class EncryptedBackupArchive
{
    private const HEADER = "CATALOG_BACKUP_V1\n";

    private readonly string $key;

    public function __construct(?string $encodedKey = null)
    {
        $value = trim($encodedKey ?? (string) config('backup.encryption_key'));
        $value = str_starts_with($value, 'base64:') ? substr($value, 7) : $value;
        $key = base64_decode($value, true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY deve conter exatamente 32 bytes em Base64.');
        }

        $this->key = $key;
    }

    public function writer(string $path): EncryptedBackupWriter
    {
        return new EncryptedBackupWriter($path, $this->key, self::HEADER);
    }

    public function read(string $path, ?Closure $consumer = null): array
    {
        $stream = fopen($path, 'rb');

        if ($stream === false || fgets($stream) !== self::HEADER) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('O arquivo não possui o formato de backup reconhecido.');
        }

        $sequence = 0;
        $records = 0;
        $footer = null;
        $digest = hash_init('sha256');

        try {
            while (($line = fgets($stream)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                $envelope = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                if (($envelope['s'] ?? null) !== $sequence) {
                    throw new RuntimeException('A sequência interna do backup é inválida.');
                }

                $nonce = base64_decode((string) ($envelope['n'] ?? ''), true);
                $tag = base64_decode((string) ($envelope['t'] ?? ''), true);
                $ciphertext = base64_decode((string) ($envelope['c'] ?? ''), true);

                if ($nonce === false || strlen($nonce) !== 12 || $tag === false || $ciphertext === false) {
                    throw new RuntimeException('Um registro criptografado do backup é inválido.');
                }

                $plaintext = openssl_decrypt(
                    $ciphertext,
                    'aes-256-gcm',
                    $this->key,
                    OPENSSL_RAW_DATA,
                    $nonce,
                    $tag,
                    "catalog-backup-v1:{$sequence}"
                );

                if ($plaintext === false) {
                    throw new RuntimeException('Falha de autenticação: chave incorreta ou backup corrompido.');
                }

                $record = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

                if (($record['type'] ?? null) === 'footer') {
                    $footer = $record;
                    break;
                }

                hash_update($digest, $plaintext);
                $consumer?->__invoke($record);
                $records++;
                $sequence++;
            }

            if (! $footer || ($footer['records'] ?? null) !== $records) {
                throw new RuntimeException('O backup está incompleto ou não possui fechamento válido.');
            }

            $calculated = hash_final($digest);
            if (! hash_equals((string) ($footer['digest'] ?? ''), $calculated)) {
                throw new RuntimeException('A verificação integral do backup falhou.');
            }

            while (($remaining = fgets($stream)) !== false) {
                if (trim($remaining) !== '') {
                    throw new RuntimeException('Foram encontrados dados inesperados após o fechamento do backup.');
                }
            }

            return $footer;
        } finally {
            fclose($stream);
        }
    }
}

class EncryptedBackupWriter
{
    private mixed $stream;

    private int $sequence = 0;

    private mixed $digest;

    private bool $closed = false;

    public function __construct(string $path, private readonly string $key, string $header)
    {
        $this->stream = fopen($path, 'wb');

        if ($this->stream === false || fwrite($this->stream, $header) === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário de backup.');
        }

        $this->digest = hash_init('sha256');
    }

    public function add(array $record): void
    {
        if ($this->closed) {
            throw new RuntimeException('O backup já foi finalizado.');
        }

        $plaintext = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        hash_update($this->digest, $plaintext);
        $this->writeEncrypted($plaintext);
    }

    public function close(array $summary = []): void
    {
        if ($this->closed) {
            return;
        }

        $footer = array_merge($summary, [
            'type' => 'footer',
            'records' => $this->sequence,
            'digest' => hash_final($this->digest),
        ]);

        $this->writeEncrypted(json_encode($footer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        fflush($this->stream);
        fclose($this->stream);
        $this->closed = true;
    }

    public function abort(): void
    {
        if (! $this->closed && is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->closed = true;
    }

    public function __destruct()
    {
        $this->abort();
    }

    private function writeEncrypted(string $plaintext): void
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            "catalog-backup-v1:{$this->sequence}",
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Não foi possível criptografar um registro do backup.');
        }

        $line = json_encode([
            's' => $this->sequence,
            'n' => base64_encode($nonce),
            't' => base64_encode($tag),
            'c' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";

        if (fwrite($this->stream, $line) === false) {
            throw new RuntimeException('Falha ao gravar o arquivo de backup.');
        }

        $this->sequence++;
    }
}
