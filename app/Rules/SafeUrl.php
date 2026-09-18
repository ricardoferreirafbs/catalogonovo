<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isSafe($value)) {
            $fail('Use uma âncora, caminho interno ou URL segura com http, https, mailto ou tel.');
        }
    }

    private function isSafe(string $value): bool
    {
        if ($value !== trim($value)) {
            return false;
        }

        if ($value === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
            return false;
        }

        if (str_starts_with($value, '#')) {
            return (bool) preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $value);
        }

        if (str_starts_with($value, '/')) {
            return ! str_starts_with($value, '//');
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }

        if ($scheme === 'mailto') {
            return filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($scheme === 'tel') {
            return (bool) preg_match('/^tel:\+?[0-9(). -]{7,30}$/i', $value);
        }

        return false;
    }
}
