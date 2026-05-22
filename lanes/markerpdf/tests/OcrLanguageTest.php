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
];
