<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

return [
    'decodes mixed null dictionary and reference DecodeParms arrays without unmatched-group warnings' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $method = new ReflectionMethod($extractor, 'streamDecodeParmsFromValue');
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];
            return true;
        });
        try {
            $decoded = $method->invoke(
                $extractor,
                'null << /Predictor 12 /Columns 3 >> 5 0 R',
                [5 => '<< /Predictor 2 /Columns 4 >>']
            );
        } finally {
            restore_error_handler();
        }

        $t->same([], $warnings);
        $t->same([
            [],
            ['Predictor' => 12, 'Columns' => 3],
            ['Predictor' => 2, 'Columns' => 4],
        ], $decoded);
    },

    'ignores an unresolved DecodeParms reference without reading an absent regex capture' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $method = new ReflectionMethod($extractor, 'streamDecodeParmsFromValue');
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];
            return true;
        });
        try {
            $decoded = $method->invoke($extractor, 'null 99 0 R', []);
        } finally {
            restore_error_handler();
        }

        $t->same([], $warnings);
        $t->same([[]], $decoded);
    },
];
