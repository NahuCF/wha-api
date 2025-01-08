<?php

use App\Models\Timezone;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timezones = [
            ['name' => 'Africa/Abidjan', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Accra', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Addis_Ababa', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Algiers', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Asmara', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Bamako', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Bangui', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Banjul', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Bissau', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Blantyre', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Brazzaville', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Bujumbura', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Cairo', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Casablanca', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Ceuta', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Conakry', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Dakar', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Dar_es_Salaam', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Djibouti', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Addis_Ababa', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Harare', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Johannesburg', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Juba', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Kampala', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Khartoum', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Kigali', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Kinshasa', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Lagos', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Libreville', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Luanda', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Lubumbashi', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Malabo', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Maputo', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Maseru', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Mbabane', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Mogadishu', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Monrovia', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Nairobi', 'deviation' => 'GMT+03:00'],
            ['name' => 'Africa/Ndjamena', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Niamey', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Nouakchott', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Ouagadougou', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Porto-Novo', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Sao_Tome', 'deviation' => 'GMT+00:00'],
            ['name' => 'Africa/Tripoli', 'deviation' => 'GMT+02:00'],
            ['name' => 'Africa/Tunis', 'deviation' => 'GMT+01:00'],
            ['name' => 'Africa/Windhoek', 'deviation' => 'GMT+02:00'],
            ['name' => 'America/Adak', 'deviation' => 'GMT-10:00'],
            ['name' => 'America/Anchorage', 'deviation' => 'GMT-09:00'],
            ['name' => 'America/Anguilla', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Antigua', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Araguaina', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Buenos_Aires', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Catamarca', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/ComodRivadavia', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Cordoba', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Jujuy', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/La_Rioja', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Mendoza', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Rio_Gallegos', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Salta', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/San_Juan', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/San_Luis', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Tucuman', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Argentina/Ushuaia', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Aruba', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Asuncion', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Atikokan', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Barbados', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Belem', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Belize', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Blanc-Sablon', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Boa_Vista', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Bogota', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Boise', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Cambridge_Bay', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Campo_Grande', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Cancun', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Caracas', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Cayenne', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Cayman', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Chicago', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Chihuahua', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Costa_Rica', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Cuiaba', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Curacao', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Danmarkshavn', 'deviation' => 'GMT+00:00'],
            ['name' => 'America/Dawson', 'deviation' => 'GMT-08:00'],
            ['name' => 'America/Dawson_Creek', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Denver', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Detroit', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Dominica', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Edmonton', 'deviation' => 'GMT-07:00'],
            ['name' => 'America/Eirunepe', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/El_Salvador', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Fortaleza', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Glace_Bay', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Godthab', 'deviation' => 'GMT-03:00'],
            ['name' => 'America/Goose_Bay', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Grand_Turk', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Grenada', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Guadeloupe', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Guatemala', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Guayaquil', 'deviation' => 'GMT-05:00'],
            ['name' => 'America/Guyana', 'deviation' => 'GMT-04:00'],
            ['name' => 'America/Houston', 'deviation' => 'GMT-06:00'],
            ['name' => 'America/Hurdat', 'deviation' => 'GMT-05:00'],
        ];

        Timezone::insert($timezones);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
