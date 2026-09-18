<?php

namespace App\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        return $this->matchingCounter($secret, $code, $timestamp, $window) !== null;
    }

    public function matchingCounter(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        try {
            for ($offset = -$window; $offset <= $window; $offset++) {
                $candidateCounter = $counter + $offset;

                if (hash_equals($this->codeForCounter($secret, $candidateCounter), $code)) {
                    return $candidateCounter;
                }
            }
        } catch (InvalidArgumentException) {
            return null;
        }

        return null;
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        return $this->codeForCounter($secret, intdiv($timestamp ?? time(), 30));
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $issuer = (string) config('app.name', 'Catalogo SaaS');
        $label = rawurlencode($issuer.':'.$email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function qrCodeDataUri(string $provisioningUri): string
    {
        $qrCode = QrCode::create($provisioningUri)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize(280)
            ->setMargin(12);

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(function () {
                $code = strtoupper(bin2hex(random_bytes(5)));

                return substr($code, 0, 5).'-'.substr($code, 5);
            })
            ->all();
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeRecoveryCode($code), (string) config('app.key'));
    }

    public function recoveryCodeIndex(array $hashes, string $candidate): ?int
    {
        $candidateHash = $this->hashRecoveryCode($candidate);

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && hash_equals($hash, $candidateHash)) {
                return $index;
            }
        }

        return null;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        if ($key === null || $counter < 0) {
            throw new InvalidArgumentException('Segredo TOTP inválido.');
        }

        $binaryCounter = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $number = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($number % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $bits = '';

        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): ?string
    {
        $value = strtoupper(str_replace([' ', '-'], '', $value));
        $bits = '';

        foreach (str_split($value) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if ($position === false) {
                return null;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }
}
