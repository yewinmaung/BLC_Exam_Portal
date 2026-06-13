<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class EncryptionService
{
    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (RuntimeException $e) {
            return null;
        }
    }
}
