<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OcrLanguage;

return [
    'normalizes Surya language names to pinned OCR codes while preserving codes' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $normalized = $languages->normalizeAndValidate(['english', 'Spanish', 'zh'], 'surya');

        $t->same(['en', 'es', 'zh'], $normalized);
        $t->same(null, $languages->normalizeAndValidate(null, 'surya'));
    },
    'normalizes OCRmyPDF Tesseract names and defaults missing languages like upstream' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $t->same(['eng', 'spa', 'rus'], $languages->normalizeAndValidate(['English', 'Spanish', 'Russian'], 'ocrmypdf'));
        $t->same(['eng'], $languages->normalizeAndValidate(null, 'ocrmypdf'));
    },
    'rejects invalid engine-specific OCR language codes' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $t->throws(InvalidArgumentException::class, static fn () => $languages->validateLangs(['zz'], 'surya'));
        $t->throws(InvalidArgumentException::class, static fn () => $languages->validateLangs(['zzz'], 'ocrmypdf'));
    },
    'maps Surya language codes to locked tokenizer ids with unique language semantics' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $tokens = $languages->langTokenIds(['en', 'es', 'en', '_math']);

        $t->same([65555, 65557, 65632], $tokens);
        $t->same([65557, 65555], $languages->langTokenIds(['es', 'en']));
    },
    'rejects invalid Surya tokenizer language codes before OCR handoff' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $t->throws(InvalidArgumentException::class, static fn () => $languages->langTokenIds(['en', 'zzz']));
    },
    'keeps lower-case Tesseract language names invalid like marker ocr lang' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $languages->normalizeAndValidate(['english'], 'ocrmypdf')
        );
    },
    'builds a WordPress multilingual OCR metadata preflight' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();
        $metadata = [
            'migration-spanish-russian.pdf' => ['languages' => ['Spanish', 'Russian']],
            'migration-default-language.pdf' => ['languages' => null],
        ];

        $preflight = [];
        foreach ($metadata as $filename => $item) {
            $preflight[] = [
                'filename' => $filename,
                'ocr_engine' => 'ocrmypdf',
                'languages' => $languages->normalizeAndValidate($item['languages'], 'ocrmypdf'),
            ];
        }

        $t->same(
            [
                [
                    'filename' => 'migration-spanish-russian.pdf',
                    'ocr_engine' => 'ocrmypdf',
                    'languages' => ['spa', 'rus'],
                ],
                [
                    'filename' => 'migration-default-language.pdf',
                    'ocr_engine' => 'ocrmypdf',
                    'languages' => ['eng'],
                ],
            ],
            $preflight
        );
    },
    'adds Surya tokenizer metadata to a WordPress OCR preflight' => static function (TestRunner $t): void {
        $languages = new OcrLanguage();
        $codes = $languages->normalizeAndValidate(['English', 'Spanish'], 'surya');

        $t->same([
            'ocr_engine' => 'surya',
            'languages' => ['en', 'es'],
            'lang_token_ids' => [65555, 65557],
        ], [
            'ocr_engine' => 'surya',
            'languages' => $codes,
            'lang_token_ids' => $languages->langTokenIds($codes ?? []),
        ]);
    },
];
