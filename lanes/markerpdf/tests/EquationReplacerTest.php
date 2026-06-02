<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\EquationReplacer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\MarkerSettings;

$equationPage = static function (): array {
    return [
        'pnum' => 3,
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'layout' => [
            'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
            'bboxes' => [
                ['label' => 'Formula', 'bbox' => [120.0, 200.0, 560.0, 240.0]],
            ],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'bbox' => [60.0, 80.0, 280.0, 150.0],
                'lines' => [
                    ['prelim_text' => 'Before equation.', 'bbox' => [60.0, 82.0, 220.0, 96.0]],
                    ['prelim_text' => 'E = m c^2', 'bbox' => [62.0, 101.0, 276.0, 119.0]],
                    ['prelim_text' => 'After equation.', 'bbox' => [60.0, 124.0, 220.0, 140.0]],
                ],
            ],
        ],
    ];
};

return [
    'finds formula regions, rescales bboxes, and removes intersecting text lines' => static function (TestRunner $t) use ($equationPage): void {
        $found = (new EquationReplacer())->findEquationBlocks($equationPage());

        $t->same(
            [
                [
                    'block_index' => 0,
                    'line_index' => 1,
                    'token_count' => 4,
                    'block_text' => 'E = m c^2',
                    'bbox' => [60.0, 100.0, 280.0, 120.0],
                ],
            ],
            $found['equations']
        );
        $t->same(['Before equation.', 'After equation.'], array_column($found['page']['blocks'][0]['lines'], 'prelim_text'));
    },
    'keeps formula regions inside upstream table layout boundaries out of equation replacement' => static function (TestRunner $t) use ($equationPage): void {
        $page = $equationPage();
        array_unshift($page['layout']['bboxes'], ['label' => 'Table', 'bbox' => [100.0, 180.0, 580.0, 260.0]]);

        $found = (new EquationReplacer())->findEquationBlocks($page);

        $t->same([], $found['equations']);
        $t->same(
            ['Before equation.', 'E = m c^2', 'After equation.'],
            array_column($found['page']['blocks'][0]['lines'], 'prelim_text')
        );
    },
    'inserts accepted latex blocks by splitting the original text block' => static function (TestRunner $t) use ($equationPage): void {
        $replacer = new EquationReplacer();
        $found = $replacer->findEquationBlocks($equationPage());
        $inserted = $replacer->insertLatexBlocks($found['page'], $found['equations'], ['$$E=mc^2$$']);
        $blocks = $inserted['page']['blocks'];

        $t->same(1, $inserted['successful_ocr']);
        $t->same(0, $inserted['unsuccessful_ocr']);
        $t->same(['Text', 'Formula', 'Text'], array_column($blocks, 'type'));
        $t->same('Before equation.', $blocks[0]['lines'][0]['prelim_text']);
        $t->same('$$E=mc^2$$', $blocks[1]['lines'][0]['spans'][0]['text']);
        $t->same('Latex', $blocks[1]['lines'][0]['spans'][0]['font']);
        $t->same('After equation.', $blocks[2]['lines'][0]['prelim_text']);
        $t->same('$$E=mc^2$$', $inserted['converted_spans'][0]['text']);
    },
    'keeps original equation text when latex prediction validation fails' => static function (TestRunner $t) use ($equationPage): void {
        $replacer = new EquationReplacer();
        $found = $replacer->findEquationBlocks($equationPage());
        $inserted = $replacer->insertLatexBlocks($found['page'], $found['equations'], ['x']);
        $formula = $inserted['page']['blocks'][1];

        $t->same(0, $inserted['successful_ocr']);
        $t->same(1, $inserted['unsuccessful_ocr']);
        $t->same([], $inserted['converted_spans']);
        $t->same('E = m c^2', $formula['lines'][0]['spans'][0]['text']);
    },
    'falls back to the nearest block when formula text lines are missing' => static function (TestRunner $t): void {
        $page = [
            'pnum' => 4,
            'bbox' => [0.0, 0.0, 600.0, 800.0],
            'layout_boxes' => [
                ['label' => 'Formula', 'bbox' => [300.0, 400.0, 520.0, 460.0]],
            ],
            'blocks' => [
                ['type' => 'Text', 'bbox' => [70.0, 90.0, 260.0, 130.0], 'lines' => [['text' => 'Intro.', 'bbox' => [72.0, 96.0, 140.0, 110.0]]]],
                ['type' => 'Text', 'bbox' => [290.0, 390.0, 540.0, 470.0], 'lines' => []],
            ],
        ];

        $replacer = new EquationReplacer();
        $found = $replacer->findEquationBlocks($page);
        $inserted = $replacer->insertLatexBlocks($found['page'], $found['equations'], ['$$\\int x\\,dx$$']);

        $t->same(1, $found['equations'][0]['block_index']);
        $t->same('', $found['equations'][0]['block_text']);
        $t->same('Formula', $inserted['page']['blocks'][1]['type']);
        $t->same('$$\\int x\\,dx$$', $inserted['page']['blocks'][1]['lines'][0]['spans'][0]['text']);
    },
    'skips equations whose native token count reaches the texify model limit' => static function (TestRunner $t) use ($equationPage): void {
        $page = $equationPage();
        $page['blocks'][0]['lines'][1]['prelim_text'] = 'one two three four five';
        $found = (new EquationReplacer())->findEquationBlocks($page, modelMaxTokens: 5);

        $t->same([], $found['equations']);
        $t->same(
            ['Before equation.', 'one two three four five', 'After equation.'],
            array_column($found['page']['blocks'][0]['lines'], 'prelim_text')
        );
    },
    'reports upstream-style equation metadata and renders a WordPress math block scenario' => static function (TestRunner $t) use ($equationPage): void {
        $replaced = (new EquationReplacer())->replaceEquations([$equationPage()], ['$$E=mc^2$$']);

        $t->same(['successful_ocr' => 1, 'unsuccessful_ocr' => 0, 'equations' => 1], $replaced['metadata']);

        $merged = (new MarkdownPostProcessor())->mergeBlocks($replaced['pages']);
        $fullText = (new MarkdownPostProcessor())->getFullText($merged);
        preg_match('/\$\$.*?\$\$/', $fullText, $matches);
        $latex = htmlspecialchars($matches[0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = "<!-- wp:html -->\n<div class=\"wp-block-markerpdf-equation\">{$latex}</div>\n<!-- /wp:html -->\n";

        $t->contains('$$E=mc^2$$', $fullText);
        $t->same("<!-- wp:html -->\n<div class=\"wp-block-markerpdf-equation\">\$\$E=mc^2\$\$</div>\n<!-- /wp:html -->\n", $html);
    },
    'computes upstream Texify batch sizes for equation recognition' => static function (TestRunner $t): void {
        $t->same(2, (new EquationReplacer())->texifyBatchSize());
        $t->same(3, (new EquationReplacer())->texifyBatchSize(1.5));
        $t->same(6, (new EquationReplacer(null, new MarkerSettings(['TORCH_DEVICE' => 'cuda'])))->texifyBatchSize());
        $t->same(6, (new EquationReplacer(null, new MarkerSettings(['TORCH_DEVICE' => 'mps'])))->texifyBatchSize());
        $t->same(10, (new EquationReplacer(null, new MarkerSettings(['TEXIFY_BATCH_SIZE' => '5'])))->texifyBatchSize(2.0));
    },
    'filters supplied Texify batch outputs at the upstream max-token sentinel' => static function (TestRunner $t): void {
        $replacer = new EquationReplacer(null, new MarkerSettings([
            'TEXIFY_BATCH_SIZE' => 2,
            'TEXIFY_MODEL_MAX' => 10,
            'TEXIFY_TOKEN_BUFFER' => 3,
        ]));

        $plan = $replacer->getLatexBatchedFromSuppliedOutputs(
            ['eq-image-0', 'eq-image-1', 'eq-image-2'],
            [4, '12', 2],
            [
                '$$a+b$$',
                'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu',
                'one two three four',
            ]
        );

        $t->same(2, $plan['batch_size']);
        $t->same([
            ['start' => 0, 'end' => 2, 'image_count' => 2, 'token_counts' => [4, 12], 'max_tokens' => 13],
            ['start' => 2, 'end' => 3, 'image_count' => 1, 'token_counts' => [2], 'max_tokens' => 5],
        ], $plan['batches']);
        $t->same(['$$a+b$$', '', ''], $plan['predictions']);
        $t->same([1, 2], $plan['dropped_output_indexes']);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $replacer->getLatexBatchedFromSuppliedOutputs(['image'], [1, 2], ['output'])
        );
    },
];
