<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $currencies = [
            ['name' => 'Australian Dollar', 'code' => 'AUD'],
            ['name' => 'Argentine Peso', 'code' => 'ARS'],
            ['name' => 'Argentine Peso', 'code' => 'ARS'],
            ['name' => 'Bolivian Boliviano', 'code' => 'BOB'],
            ['name' => 'Brazilian Real', 'code' => 'BRL'],
            ['name' => 'Bulgarian Lev', 'code' => 'BGN'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD'],
            ['name' => 'Chilean Peso', 'code' => 'CLP'],
            ['name' => 'Chinese Yuan', 'code' => 'CNY'],
            ['name' => 'Colombian Peso', 'code' => 'COP'],
            ['name' => 'Czech Koruna', 'code' => 'CZK'],
            ['name' => 'Danish Krone', 'code' => 'DKK'],
            ['name' => 'Egyptian Pound', 'code' => 'EGP'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Hong Kong Dollar', 'code' => 'HKD'],
            ['name' => 'Hungarian Forint', 'code' => 'HUF'],
            ['name' => 'Indian Rupee', 'code' => 'INR'],
            ['name' => 'Indian Rupee', 'code' => 'INR'],
            ['name' => 'Indonesian Rupiah', 'code' => 'IDR'],
            ['name' => 'Israeli New Shekel', 'code' => 'ILS'],
            ['name' => 'Japanese Yen', 'code' => 'JPY'],
            ['name' => 'Jordanian Dinar', 'code' => 'JOD'],
            ['name' => 'Kuwaiti Dinar', 'code' => 'KWD'],
            ['name' => 'Malaysian Ringgit', 'code' => 'MYR'],
            ['name' => 'Mexican Peso', 'code' => 'MXN'],
            ['name' => 'New Zealand Dollar', 'code' => 'NZD'],
            ['name' => 'Norwegian Krone', 'code' => 'NOK'],
            ['name' => 'Omani Rial', 'code' => 'OMR'],
            ['name' => 'Peruvian Sol', 'code' => 'PEN'],
            ['name' => 'Philippine Peso', 'code' => 'PHP'],
            ['name' => 'Polish Zloty', 'code' => 'PLN'],
            ['name' => 'Pound Sterling', 'code' => 'GBP'],
            ['name' => 'Romanian Leu', 'code' => 'RON'],
            ['name' => 'Russian Ruble', 'code' => 'RUB'],
            ['name' => 'South African Rand', 'code' => 'ZAR'],
            ['name' => 'South African Rand', 'code' => 'ZAR'],
            ['name' => 'South Korean Won', 'code' => 'KRW'],
            ['name' => 'Singapore Dollar', 'code' => 'SGD'],
            ['name' => 'Swedish Krona', 'code' => 'SEK'],
            ['name' => 'Swiss Franc', 'code' => 'CHF'],
            ['name' => 'Syrian Pound', 'code' => 'SYP'],
            ['name' => 'Tanzanian Shilling', 'code' => 'TZS'],
            ['name' => 'Thai Baht', 'code' => 'THB'],
            ['name' => 'Turkish Lira', 'code' => 'TRY'],
            ['name' => 'United Arab Emirates Dirham', 'code' => 'AED'],
            ['name' => 'United States Dollar', 'code' => 'USD'],
            ['name' => 'Zambian Kwacha', 'code' => 'ZMW'],
        ];

        Currency::insert($currencies);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
