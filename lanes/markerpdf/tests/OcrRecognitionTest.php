<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\OcrLanguage;
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
    'plans surya recognition polygons with upstream scaling and zero-area skip' => static function (TestRunner $t): void {
        $recognition = new OcrRecognition(new MarkerSettings([
            'SURYA_OCR_DPI' => '192',
            'SURYA_DETECTOR_DPI' => '96',
            'RECOGNITION_BATCH_SIZE' => '5',
        ]));
        $pages = [[
            'text_lines' => [
                'image_bbox' => [0.0, 0.0, 600.0, 800.0],
                'bboxes' => [
                    ['polygon' => [[10, 10], [30, 10], [30, 20], [10, 20]]],
                    ['bbox' => [50.0, 50.0, 50.0, 72.0]],
                    ['bbox' => [100.0, 80.0, 140.0, 100.0]],
                ],
            ],
        ]];

        $plan = $recognition->suryaRecognitionPlan($pages, [0], 2);

        $t->same(10, $plan['batch_size']);
        $t->same(2.0, $plan['box_scale']);
        $t->same([
            [
                [[20, 20], [60, 20], [60, 40], [20, 40]],
                [[200, 160], [280, 160], [280, 200], [200, 200]],
            ],
        ], $plan['polygons']);
    },
    'builds surya recognized pages with upstream bbox rescaling' => static function (TestRunner $t) use ($page): void {
        $recognition = new OcrRecognition();
        $pages = [$page('@@@ ### !!!')];
        $recognizedPages = $recognition->buildSuryaRecognitionPages(
            $pages,
            [0],
            [[
                ['text' => 'Recovered OCR text for a WordPress paragraph.', 'bbox' => [120.0, 192.0, 540.0, 232.0]],
            ]],
            [['width' => 1200, 'height' => 1600]]
        );

        $t->same('surya', $recognizedPages[0]['ocr_method']);
        $t->same([60.0, 96.0, 270.0, 116.0], $recognizedPages[0]['blocks'][0]['bbox']);
        $t->same('0_0', $recognizedPages[0]['blocks'][0]['lines'][0]['spans'][0]['span_id']);
        $t->same('', $recognizedPages[0]['blocks'][0]['lines'][0]['spans'][0]['font']);
        $t->same('Recovered OCR text for a WordPress paragraph.', $recognizedPages[0]['blocks'][0]['lines'][0]['spans'][0]['text']);

        $result = $recognition->runWithSuppliedPages($pages, $recognizedPages);
        $t->same(1, $result['stats']['ocr_success']);
        $t->same('Recovered OCR text for a WordPress paragraph.', $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text']);
    },
    'preserves OCR language and confidence review while triaging recognized pages by upstream text quality' => static function (TestRunner $t) use ($page): void {
        $languages = new OcrLanguage();
        $suryaLanguages = $languages->normalizeAndValidate(['English', 'Spanish'], 'surya');
        $recognition = new OcrRecognition();
        $pages = [
            $page('@@@ ### !!!', [72.0, 96.0, 540.0, 120.0]),
            $page('### !!! @@@', [72.0, 150.0, 540.0, 174.0]),
        ];

        $recognizedPages = $recognition->buildSuryaRecognitionPages(
            $pages,
            [0, 1],
            [
                [
                    [
                        'text' => 'Recovered multilingual WordPress import text.',
                        'bbox' => [120.0, 192.0, 540.0, 232.0],
                        'confidence' => 0.08,
                    ],
                ],
                [
                    [
                        'text' => '@@@ ### !!!',
                        'bbox' => [120.0, 300.0, 540.0, 348.0],
                        'confidence' => 0.99,
                    ],
                ],
            ],
            [
                ['width' => 1200, 'height' => 1600],
                ['width' => 1200, 'height' => 1600],
            ],
            $suryaLanguages
        );

        $result = $recognition->runWithSuppliedPages($pages, $recognizedPages);
        $paragraph = htmlspecialchars(
            (string) $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $html = "<!-- wp:paragraph -->\n<p>{$paragraph}</p>\n<!-- /wp:paragraph -->\n";

        $t->same(['en', 'es'], $suryaLanguages);
        $t->same([65555, 65557], $languages->langTokenIds($suryaLanguages ?? []));
        $t->same([
            'ocr_pages' => 2,
            'ocr_failed' => 1,
            'ocr_success' => 1,
            'ocr_engine' => 'surya',
        ], $result['stats']);
        $t->same(['en', 'es'], $result['pages'][0]['ocr_languages']);
        $t->same(['count' => 1, 'min' => 0.08, 'max' => 0.08, 'average' => 0.08], $result['pages'][0]['ocr_confidence']);
        $t->same(0.08, $result['pages'][0]['blocks'][0]['lines'][0]['confidence']);
        $t->same(0.08, $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['confidence']);
        $t->same('Recovered multilingual WordPress import text.', $result['pages'][0]['blocks'][0]['lines'][0]['spans'][0]['text']);
        $t->same('### !!! @@@', $result['pages'][1]['prelim_text']);
        $t->true(!isset($result['pages'][1]['ocr_method']));
        $t->contains('Recovered multilingual WordPress import text.', $html);
        $t->true(!str_contains($html, '@@@ ### !!!'));
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
