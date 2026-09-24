<?php

namespace App\Model;

/**
 * Formats amounts, which are stored in cents throughout the app.
 */
final class Money
{
    private function __construct()
    {
    }

    /**
     * Formats an amount in cents as a decimal price, e.g. 1999 becomes "19.99".
     */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
