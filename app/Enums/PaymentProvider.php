<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case MERCADOPAGO = 'mercadopago';
    case STRIPE = 'stripe';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
