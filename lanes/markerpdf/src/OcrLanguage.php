<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class OcrLanguage
{
    /**
     * Pinned to surya-ocr 0.6.13, the version locked by markerPDF at
     * da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34.
     *
     * @var array<string, string>
     */
    private const SURYA_CODE_TO_LANGUAGE = [
        '_math' => 'Math',
        'af' => 'Afrikaans',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'as' => 'Assamese',
        'az' => 'Azerbaijani',
        'be' => 'Belarusian',
        'bg' => 'Bulgarian',
        'bn' => 'Bengali',
        'br' => 'Breton',
        'bs' => 'Bosnian',
        'ca' => 'Catalan',
        'cs' => 'Czech',
        'cy' => 'Welsh',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'en' => 'English',
        'eo' => 'Esperanto',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'eu' => 'Basque',
        'fa' => 'Persian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'fy' => 'Western Frisian',
        'ga' => 'Irish',
        'gd' => 'Scottish Gaelic',
        'gl' => 'Galician',
        'gu' => 'Gujarati',
        'ha' => 'Hausa',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'hu' => 'Hungarian',
        'hy' => 'Armenian',
        'id' => 'Indonesian',
        'is' => 'Icelandic',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'jv' => 'Javanese',
        'ka' => 'Georgian',
        'kk' => 'Kazakh',
        'km' => 'Khmer',
        'kn' => 'Kannada',
        'ko' => 'Korean',
        'ku' => 'Kurdish',
        'ky' => 'Kyrgyz',
        'la' => 'Latin',
        'lo' => 'Lao',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'mg' => 'Malagasy',
        'mk' => 'Macedonian',
        'ml' => 'Malayalam',
        'mn' => 'Mongolian',
        'mr' => 'Marathi',
        'ms' => 'Malay',
        'my' => 'Burmese',
        'ne' => 'Nepali',
        'nl' => 'Dutch',
        'no' => 'Norwegian',
        'om' => 'Oromo',
        'or' => 'Oriya',
        'pa' => 'Punjabi',
        'pl' => 'Polish',
        'ps' => 'Pashto',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sa' => 'Sanskrit',
        'sd' => 'Sindhi',
        'si' => 'Sinhala',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'so' => 'Somali',
        'sq' => 'Albanian',
        'sr' => 'Serbian',
        'su' => 'Sundanese',
        'sv' => 'Swedish',
        'sw' => 'Swahili',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'th' => 'Thai',
        'tl' => 'Tagalog',
        'tr' => 'Turkish',
        'ug' => 'Uyghur',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'vi' => 'Vietnamese',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'zh' => 'Chinese',
    ];

    /**
     * Copied from marker/ocr/tesseract.py in upstream markerPDF.
     *
     * @var array<string, string>
     */
    private const TESSERACT_LANGUAGE_TO_CODE = [
        'Afrikaans' => 'afr',
        'Amharic' => 'amh',
        'Arabic' => 'ara',
        'Assamese' => 'asm',
        'Azerbaijani' => 'aze',
        'Belarusian' => 'bel',
        'Bulgarian' => 'bul',
        'Bengali' => 'ben',
        'Breton' => 'bre',
        'Bosnian' => 'bos',
        'Catalan' => 'cat',
        'Czech' => 'ces',
        'Welsh' => 'cym',
        'Danish' => 'dan',
        'German' => 'deu',
        'Greek' => 'ell',
        'English' => 'eng',
        'Esperanto' => 'epo',
        'Spanish' => 'spa',
        'Estonian' => 'est',
        'Basque' => 'eus',
        'Persian' => 'fas',
        'Finnish' => 'fin',
        'French' => 'fra',
        'Western Frisian' => 'fry',
        'Irish' => 'gle',
        'Scottish Gaelic' => 'gla',
        'Galician' => 'glg',
        'Gujarati' => 'guj',
        'Hausa' => 'hau',
        'Hebrew' => 'heb',
        'Hindi' => 'hin',
        'Croatian' => 'hrv',
        'Hungarian' => 'hun',
        'Armenian' => 'hye',
        'Indonesian' => 'ind',
        'Icelandic' => 'isl',
        'Italian' => 'ita',
        'Japanese' => 'jpn',
        'Javanese' => 'jav',
        'Georgian' => 'kat',
        'Kazakh' => 'kaz',
        'Khmer' => 'khm',
        'Kannada' => 'kan',
        'Korean' => 'kor',
        'Kurdish' => 'kur',
        'Kyrgyz' => 'kir',
        'Latin' => 'lat',
        'Lao' => 'lao',
        'Lithuanian' => 'lit',
        'Latvian' => 'lav',
        'Malagasy' => 'mlg',
        'Macedonian' => 'mkd',
        'Malayalam' => 'mal',
        'Mongolian' => 'mon',
        'Marathi' => 'mar',
        'Malay' => 'msa',
        'Burmese' => 'mya',
        'Nepali' => 'nep',
        'Dutch' => 'nld',
        'Norwegian' => 'nor',
        'Oromo' => 'orm',
        'Oriya' => 'ori',
        'Punjabi' => 'pan',
        'Polish' => 'pol',
        'Pashto' => 'pus',
        'Portuguese' => 'por',
        'Romanian' => 'ron',
        'Russian' => 'rus',
        'Sanskrit' => 'san',
        'Sindhi' => 'snd',
        'Sinhala' => 'sin',
        'Slovak' => 'slk',
        'Slovenian' => 'slv',
        'Somali' => 'som',
        'Albanian' => 'sqi',
        'Serbian' => 'srp',
        'Sundanese' => 'sun',
        'Swedish' => 'swe',
        'Swahili' => 'swa',
        'Tamil' => 'tam',
        'Telugu' => 'tel',
        'Thai' => 'tha',
        'Tagalog' => 'tgl',
        'Turkish' => 'tur',
        'Uyghur' => 'uig',
        'Ukrainian' => 'ukr',
        'Urdu' => 'urd',
        'Uzbek' => 'uzb',
        'Vietnamese' => 'vie',
        'Xhosa' => 'xho',
        'Yiddish' => 'yid',
        'Chinese' => 'chi_sim',
    ];

    /**
     * Native boundary for marker.ocr.lang::replace_langs_with_codes.
     *
     * @param list<string>|null $langs
     * @return list<string>|null
     */
    public function replaceLangsWithCodes(?array $langs, string $ocrEngine = 'surya', string $defaultLang = 'English'): ?array
    {
        if ($this->isSurya($ocrEngine)) {
            if ($langs === null) {
                return null;
            }

            $languageToCode = $this->suryaLanguageToCode();
            foreach ($langs as $index => $lang) {
                $name = $this->titleCase((string) $lang);
                if (isset($languageToCode[$name])) {
                    $langs[$index] = $languageToCode[$name];
                }
            }

            return array_values($langs);
        }

        if ($langs === null) {
            $langs = [$defaultLang];
        }

        foreach ($langs as $index => $lang) {
            $lang = (string) $lang;
            if (isset(self::TESSERACT_LANGUAGE_TO_CODE[$lang])) {
                $langs[$index] = self::TESSERACT_LANGUAGE_TO_CODE[$lang];
            }
        }

        return array_values($langs);
    }

    /**
     * Native boundary for marker.ocr.lang::validate_langs.
     *
     * @param list<string>|null $langs
     */
    public function validateLangs(?array $langs, string $ocrEngine = 'surya'): void
    {
        if ($this->isSurya($ocrEngine)) {
            if ($langs === null) {
                return;
            }

            foreach ($langs as $lang) {
                $lang = (string) $lang;
                if (!isset(self::SURYA_CODE_TO_LANGUAGE[$lang])) {
                    throw new InvalidArgumentException("Invalid language code {$lang} for Surya OCR");
                }
            }

            return;
        }

        if ($langs === null) {
            throw new InvalidArgumentException('Languages are required for Tesseract OCR');
        }

        $codeToLanguage = $this->tesseractCodeToLanguage();
        foreach ($langs as $lang) {
            $lang = (string) $lang;
            if (!isset($codeToLanguage[$lang])) {
                throw new InvalidArgumentException("Invalid language code {$lang} for Tesseract");
            }
        }
    }

    /**
     * @param list<string>|null $langs
     * @return list<string>|null
     */
    public function normalizeAndValidate(?array $langs, string $ocrEngine = 'surya', string $defaultLang = 'English'): ?array
    {
        $normalized = $this->replaceLangsWithCodes($langs, $ocrEngine, $defaultLang);
        $this->validateLangs($normalized, $ocrEngine);

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public function suryaCodeToLanguage(): array
    {
        return self::SURYA_CODE_TO_LANGUAGE;
    }

    /**
     * @return array<string, string>
     */
    public function tesseractLanguageToCode(): array
    {
        return self::TESSERACT_LANGUAGE_TO_CODE;
    }

    private function isSurya(string $ocrEngine): bool
    {
        return $ocrEngine === 'surya';
    }

    /**
     * @return array<string, string>
     */
    private function suryaLanguageToCode(): array
    {
        static $languageToCode = null;
        if ($languageToCode === null) {
            $languageToCode = array_flip(self::SURYA_CODE_TO_LANGUAGE);
        }

        return $languageToCode;
    }

    /**
     * @return array<string, string>
     */
    private function tesseractCodeToLanguage(): array
    {
        static $codeToLanguage = null;
        if ($codeToLanguage === null) {
            $codeToLanguage = array_flip(self::TESSERACT_LANGUAGE_TO_CODE);
        }

        return $codeToLanguage;
    }

    private function titleCase(string $value): string
    {
        return ucwords(strtolower($value));
    }
}
