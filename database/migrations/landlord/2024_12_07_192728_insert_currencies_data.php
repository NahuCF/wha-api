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
            ['name' => 'Afghan Afghani', 'code' => 'AFN'],
            ['name' => 'Albanian Lek', 'code' => 'ALL'],
            ['name' => 'Algerian Dinar', 'code' => 'DZD'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Angolan Kwanza', 'code' => 'AOA'],
            ['name' => 'East Caribbean Dollar', 'code' => 'XCD'],
            ['name' => 'Argentine Peso', 'code' => 'ARS'],
            ['name' => 'Armenian Dram', 'code' => 'AMD'],
            ['name' => 'Australian Dollar', 'code' => 'AUD'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Azerbaijani Manat', 'code' => 'AZN'],
            ['name' => 'Bahamian Dollar', 'code' => 'BSD'],
            ['name' => 'Bahraini Dinar', 'code' => 'BHD'],
            ['name' => 'Bangladeshi Taka', 'code' => 'BDT'],
            ['name' => 'Barbadian Dollar', 'code' => 'BBD'],
            ['name' => 'Belarusian Ruble', 'code' => 'BYN'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Belize Dollar', 'code' => 'BZD'],
            ['name' => 'West African CFA franc', 'code' => 'XOF'],
            ['name' => 'Bhutanese Ngultrum', 'code' => 'BTN'],
            ['name' => 'Bolivian Boliviano', 'code' => 'BOB'],
            ['name' => 'Bosnia and Herzegovina Convertible Mark', 'code' => 'BAM'],
            ['name' => 'Botswana Pula', 'code' => 'BWP'],
            ['name' => 'Brazilian Real', 'code' => 'BRL'],
            ['name' => 'Brunei Dollar', 'code' => 'BND'],
            ['name' => 'Bulgarian Lev', 'code' => 'BGN'],
            ['name' => 'West African CFA franc', 'code' => 'XOF'],
            ['name' => 'Burundian Franc', 'code' => 'BIF'],
            ['name' => 'Cape Verdean Escudo', 'code' => 'CVE'],
            ['name' => 'Cambodian Riel', 'code' => 'KHR'],
            ['name' => 'Central African CFA franc', 'code' => 'CAF'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD'],
            ['name' => 'Central African CFA franc', 'code' => 'CAF'],
            ['name' => 'Central African CFA franc', 'code' => 'CAF'],
            ['name' => 'Chilean Peso', 'code' => 'CLP'],
            ['name' => 'Chinese Yuan', 'code' => 'CNY'],
            ['name' => 'Colombian Peso', 'code' => 'COP'],
            ['name' => 'Comorian Franc', 'code' => 'KMF'],
            ['name' => 'Central African CFA franc', 'code' => 'CAF'],
            ['name' => 'Costa Rican Colón', 'code' => 'CRC'],
            ['name' => 'Croatian Kuna', 'code' => 'HRK'],
            ['name' => 'Cuban Peso', 'code' => 'CUP'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Czech Koruna', 'code' => 'CZK'],
            ['name' => 'Danish Krone', 'code' => 'DKK'],
            ['name' => 'Djiboutian Franc', 'code' => 'DJF'],
            ['name' => 'East Caribbean Dollar', 'code' => 'XCD'],
            ['name' => 'Dominican Peso', 'code' => 'DOP'],
            ['name' => 'United States Dollar', 'code' => 'USD'],
            ['name' => 'Egyptian Pound', 'code' => 'EGP'],
            ['name' => 'United States Dollar', 'code' => 'USD'],
            ['name' => 'Central African CFA franc', 'code' => 'CAF'],
            ['name' => 'Eritrean Nakfa', 'code' => 'ERN'],
            ['name' => 'Euro', 'code' => 'EUR'],
            ['name' => 'Swazi Lilangeni', 'code' => 'SZL'],
            ['name' => 'Ethiopian Birr', 'code' => 'ETB'],
            ['name' => 'Fijian Dollar', 'code' => 'FJD'],
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
