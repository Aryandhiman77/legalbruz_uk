<?php

namespace App\Support;

final class UkMobileNumber
{
    public const REGEX = '/^(?:\+44|0)7[0-9]{9}$/';

    public const ERROR_MESSAGE = 'Enter a valid UK mobile number beginning with 07 or +44 7.';

    public static function normalize(?string $mobile): string
    {
        $normalized = (string) preg_replace('/[\s()-]+/', '', (string) $mobile);

        if (str_starts_with($normalized, '07')) {
            return '+44' . substr($normalized, 1);
        }

        return $normalized;
    }

    public static function normalizeOrNull(?string $mobile): ?string
    {
        $normalized = self::normalize($mobile);

        return preg_match(self::REGEX, $normalized) === 1 ? $normalized : null;
    }
}
