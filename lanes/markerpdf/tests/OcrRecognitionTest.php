<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\OcrRecognition;

$page = static function (string $text, array $bbox = [72.0, 96.0, 540.0, 120.0]): array {
    return [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'prelim_text' => $text,
        'text_lines' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['bbox' => $bbox],
            ],
        ],
        'blocks' => [
            [
                'lines' => [
                    ['text' => $text, 'bbox' => $bbox],
                ],
            ],
        ],
    ];
};

return [
    'uses upstream recognition batch size defaults and overrides' => static function (TestRunner $t): void {
        $t->same(32, (new OcrRecognition())->batchSize());
        $t->same(7, (new OcrRecognition(new MarkerSettings(['RECOGNITION_BATCH_SIZE' => '7'])))->batchSize());
        $t->same(32, (new OcrRecognition(new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->batchSize());
    },
    'selects OCR pages with upstream run_ocr triage rules' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();
        $pages = [
            $page('@@@ ### !!!'),
            $page('Clean extracted WordPress import text.'),
        ];

        $t->same([0], $recognition->ocrPageIndexes($pages));
        $t->same([0, 1], $recognition->ocrPageIndexes($pages, ocrAllPages: true));
    },
    'replaces successful supplied OCR pages and reports upstream stats' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();
        $pages = [
            $page('@@@ ### !!!'),
            $page('Clean extracted WordPress import text.'),
        ];
        $recognized = $page('Recovered OCR text for a WordPress paragraph.');

        $result = $recognition->runWithSuppliedPages($pages, [$recognized]);

        $t->same([
            'ocr_pages' => 1,
            'ocr_failed' => 0,
            'ocr_success' => 1,
            'ocr_engine' => 'surya',
        ], $result['stats']);
        $t->same('Recovered OCR text for a WordPress paragraph.', $result['pages'][0]['prelim_text']);
        $t->same('surya', $result['pages'][0]['ocr_method']);
        $t->same('Clean extracted WordPress import text.', $result['pages'][1]['prelim_text']);
    },
    'counts bad supplied OCR output as failed and preserves original page text' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();

        $result = $recognition->runWithSuppliedPages(
            [$page('@@@ ### !!!')],
            [$page('     ')]
        );

        $t->same([
            'ocr_pages' => 1,
            'ocr_failed' => 1,
            'ocr_success' => 0,
            'ocr_engine' => 'surya',
        ], $result['stats']);
        $t->same('@@@ ### !!!', $result['pages'][0]['prelim_text']);
        $t->true(!isset($result['pages'][0]['ocr_method']));
    },
    'returns the upstream none engine stats when OCR is disabled' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();

        $result = $recognition->runWithSuppliedPages(
            [$page('@@@ ### !!!')],
            [$page('Recovered OCR text for a WordPress paragraph.')],
            ocrEngine: 'None'
        );

        $t->same([
            'ocr_pages' => 0,
            'ocr_failed' => 0,
            'ocr_success' => 0,
            'ocr_engine' => 'none',
        ], $result['stats']);
        $t->same('@@@ ### !!!', $result['pages'][0]['prelim_text']);
    },
    'requires a supplied OCR page for every selected upstream OCR page' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $recognition->runWithSuppliedPages(
                [$page('@@@ ### !!!'), $page('More @@@ ###')],
                [$page('Recovered first page only.')],
                ocrAllPages: true
            )
        );
    },
    'drives a WordPress OCR handoff scenario with supplied recognition pages' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();
        $result = $recognition->runWithSuppliedPages(
            [$page('@@@ ### !!!')],
            [$page('Recovered OCR text for a migrated PDF page.')]
        );

        $paragraph = htmlspecialchars((string) $result['pages'][0]['blocks'][0]['lines'][0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = "<!-- wp:paragraph -->\n<p>{$paragraph}</p>\n<!-- /wp:paragraph -->\n";

        $t->same(1, $result['stats']['ocr_success']);
        $t->contains('Recovered OCR text for a migrated PDF page.', $html);
    },
];
