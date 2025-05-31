<?php

use App\Models\TemplateLanguage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $languages = collect([
            ['name' => 'Afrikaans', 'code' => 'af'],
            ['name' => 'Albanian', 'code' => 'sq'],
            ['name' => 'Arabic', 'code' => 'ar'],
            ['name' => 'Azerbaijani', 'code' => 'az'],
            ['name' => 'Bengali', 'code' => 'bn'],
            ['name' => 'Bulgarian', 'code' => 'bg'],
            ['name' => 'Catalan', 'code' => 'ca'],
            ['name' => 'Chinese (China)', 'code' => 'zh_CN'],
            ['name' => 'Chinese (Hong Kong)', 'code' => 'zh_HK'],
            ['name' => 'Chinese (Taiwan)', 'code' => 'zh_TW'],
            ['name' => 'Croatian', 'code' => 'hr'],
            ['name' => 'Czech', 'code' => 'cs'],
            ['name' => 'Danish', 'code' => 'da'],
            ['name' => 'Dutch', 'code' => 'nl'],
            ['name' => 'English', 'code' => 'en'],
            ['name' => 'English (United Kingdom)', 'code' => 'en_GB'],
            ['name' => 'English (United States)', 'code' => 'en_US'],
            ['name' => 'Estonian', 'code' => 'et'],
            ['name' => 'Filipino', 'code' => 'fil'],
            ['name' => 'Finnish', 'code' => 'fi'],
            ['name' => 'French', 'code' => 'fr'],
            ['name' => 'German', 'code' => 'de'],
            ['name' => 'Greek', 'code' => 'el'],
            ['name' => 'Gujarati', 'code' => 'gu'],
            ['name' => 'Hausa', 'code' => 'ha'],
            ['name' => 'Hebrew', 'code' => 'he'],
            ['name' => 'Hindi', 'code' => 'hi'],
            ['name' => 'Hungarian', 'code' => 'hu'],
            ['name' => 'Indonesian', 'code' => 'id'],
            ['name' => 'Irish', 'code' => 'ga'],
            ['name' => 'Italian', 'code' => 'it'],
            ['name' => 'Japanese', 'code' => 'ja'],
            ['name' => 'Kannada', 'code' => 'kn'],
            ['name' => 'Kazakh', 'code' => 'kk'],
            ['name' => 'Korean', 'code' => 'ko'],
            ['name' => 'Lao', 'code' => 'lo'],
            ['name' => 'Latvian', 'code' => 'lv'],
            ['name' => 'Lithuanian', 'code' => 'lt'],
            ['name' => 'Macedonian', 'code' => 'mk'],
            ['name' => 'Malay', 'code' => 'ms'],
            ['name' => 'Malayalam', 'code' => 'ml'],
            ['name' => 'Marathi', 'code' => 'mr'],
            ['name' => 'Norwegian', 'code' => 'nb'],
            ['name' => 'Persian', 'code' => 'fa'],
            ['name' => 'Polish', 'code' => 'pl'],
            ['name' => 'Portuguese (Brazil)', 'code' => 'pt_BR'],
            ['name' => 'Portuguese (Portugal)', 'code' => 'pt_PT'],
            ['name' => 'Punjabi', 'code' => 'pa'],
            ['name' => 'Romanian', 'code' => 'ro'],
            ['name' => 'Russian', 'code' => 'ru'],
            ['name' => 'Serbian', 'code' => 'sr'],
            ['name' => 'Slovak', 'code' => 'sk'],
            ['name' => 'Slovenian', 'code' => 'sl'],
            ['name' => 'Spanish', 'code' => 'es'],
            ['name' => 'Spanish (Argentina)', 'code' => 'es_AR'],
            ['name' => 'Spanish (Spain)', 'code' => 'es_ES'],
            ['name' => 'Spanish (Mexico)', 'code' => 'es_MX'],
            ['name' => 'Swahili', 'code' => 'sw'],
            ['name' => 'Swedish', 'code' => 'sv'],
            ['name' => 'Tamil', 'code' => 'ta'],
            ['name' => 'Telugu', 'code' => 'te'],
            ['name' => 'Thai', 'code' => 'th'],
            ['name' => 'Turkish', 'code' => 'tr'],
            ['name' => 'Ukrainian', 'code' => 'uk'],
            ['name' => 'Urdu', 'code' => 'ur'],
            ['name' => 'Uzbek', 'code' => 'uz'],
            ['name' => 'Vietnamese', 'code' => 'vi'],
            ['name' => 'Zulu', 'code' => 'zu'],
        ])->transform(function ($language) {
            $language['id'] = Str::ulid();

            return $language;
        });

        TemplateLanguage::insert($languages->toArray());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        TemplateLanguage::truncate();
    }
};
