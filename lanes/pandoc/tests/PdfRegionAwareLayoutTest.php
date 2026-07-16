<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$invoke = static function (PdfReader $reader, string $method, mixed ...$arguments): mixed {
    return (new ReflectionMethod($reader, $method))->invoke($reader, ...$arguments);
};

$layout = static function (int $index, float $x1 = 72.0, int $page = 1): array {
    $y1 = 700.0 - ($index * 20.0);
    return [
        'page' => $page,
        'sourceStream' => 1,
        'x1' => $x1,
        'y1' => $y1,
        'x2' => $x1 + 300.0,
        'y2' => $y1 + 10.0,
        'fontSize' => 10.0,
    ];
};

$dialogueBlocks = static function (array $records) use ($invoke): array {
    $reader = new PdfReader();
    $marked = $invoke($reader, 'markPdfLineOrientedRegionRecords', $records);
    $merged = $invoke($reader, 'mergeRepairedPdfRecords', $marked);
    return $invoke($reader, 'blocksFromLines', $merged);
};

$dialogueBlockCount = static fn (array $blocks): int => count(array_filter(
    $blocks,
    static fn (AstNode $node): bool => $node->type === 'paragraph' && $node->attr('sourceRole') === 'dialogue'
));

$dialogueRecords = static function (array $lines, float $x1 = 72.0, int $page = 1) use ($layout): array {
    $records = [];
    foreach ($lines as $index => $line) {
        $records[] = ['text' => $line, 'layout' => $layout($index, $x1, $page)];
    }
    return $records;
};

return [
    'turns recurring numbered character cues into editable dialogue paragraphs' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'CHARACTER 1 First spoken sentence.',
            'CHARACTER 2 A second spoken sentence.',
            'CHARACTER 1 Another spoken sentence.',
            'CHARACTER 2 The final spoken sentence.',
        ]));

        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $blocks));
        $t->same('dialogue', $blocks[0]->attr('sourceRole'));
        $t->same('strong', $blocks[0]->children[0]->type);
        $t->same('CHARACTER 1', $blocks[0]->children[0]->children[0]->attr('text'));
        $t->same('linebreak', $blocks[0]->children[1]->type);
        $t->same('First spoken sentence.', $blocks[0]->children[2]->attr('text'));
    },

    'recognizes recurring named speakers without a character-name dictionary' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'ALICE Where should we begin?',
            'BOB We can begin right here.',
            'ALICE Then let us continue.',
            'BOB This is the last reply.',
        ]));

        $t->same(4, count($blocks));
        $t->same('paragraph', $blocks[0]->type);
        $t->same('dialogue', $blocks[0]->attr('sourceRole'));
        $t->same('ALICE', $blocks[0]->children[0]->children[0]->attr('text'));
    },

    'joins an indented visual continuation to the dialogue body rather than the speaker cue' => static function (TestRunner $t) use ($dialogueBlocks, $layout): void {
        $records = [
            ['text' => 'SPEAKER 1 This sentence begins here', 'layout' => $layout(0)],
            ['text' => 'and wraps on an indented line.', 'layout' => $layout(1, 100.0)],
            ['text' => 'SPEAKER 2 This answer is complete.', 'layout' => $layout(2)],
            ['text' => 'SPEAKER 1 Here is another turn.', 'layout' => $layout(3)],
            ['text' => 'SPEAKER 2 Here is the last turn.', 'layout' => $layout(4)],
        ];
        $blocks = $dialogueBlocks($records);

        $t->same(4, count($blocks));
        $t->same('This sentence begins here and wraps on an indented line.', $blocks[0]->children[2]->attr('text'));
    },

    'does not infer a dialogue region from only three cue-shaped records' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueBlockCount, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'ALICE One sentence.',
            'BOB Another sentence.',
            'ALICE A third sentence.',
        ]));

        $t->same(0, $dialogueBlockCount($blocks));
    },

    'does not combine cue-like labels from separate visual columns' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueBlockCount, $layout): void {
        $records = [
            ['text' => 'LEFT 1 First panel sentence.', 'layout' => $layout(0, 72.0)],
            ['text' => 'LEFT 1 Second panel sentence.', 'layout' => $layout(1, 72.0)],
            ['text' => 'RIGHT 1 First other-panel sentence.', 'layout' => $layout(2, 330.0)],
            ['text' => 'RIGHT 1 Second other-panel sentence.', 'layout' => $layout(3, 330.0)],
        ];
        $blocks = $dialogueBlocks($records);

        $t->same(0, $dialogueBlockCount($blocks));
    },

    'does not treat ordinary all-capital headings and prose as theatre dialogue' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueBlockCount, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'ACT ONE',
            'This is ordinary introductory prose.',
            'SCENE TWO',
            'This is another ordinary paragraph.',
        ]));

        $t->same(0, $dialogueBlockCount($blocks));
    },

    'does not treat tabular uppercase labels with numeric values as dialogue' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueBlockCount, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'REVENUE 1200',
            'EXPENSES 900',
            'REVENUE 1400',
            'EXPENSES 800',
        ]));

        $t->same(0, $dialogueBlockCount($blocks));
    },

    'repairs arbitrary inter-glyph spaces only when the positioned candidate proves identical characters' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'Y o u r article', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'Your article',
        ]);

        $t->same('Your article', $actual);
    },

    'repairs a different sustained fragment run without a word-specific rule' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'T h e a t r e notes', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'Theatre notes',
        ]);

        $t->same('Theatre notes', $actual);
    },

    'repairs sustained fragment spacing around punctuation from character-equivalent geometry' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'H e l l o , world', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'Hello, world',
        ]);

        $t->same('Hello, world', $actual);
    },

    'keeps source text when a positioned candidate would change a character' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'T h e title', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'Te title',
        ]);

        $t->same('T h e title', $actual);
    },

    'keeps intentional display tracking when the positioned candidate retains it' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'W I D E title', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'W I D E title',
        ]);

        $t->same('W I D E title', $actual);
    },

    'does not rewrite a short two-boundary fragment as sustained spacing damage' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $actual = $invoke($reader, 'repairPdfInterGlyphSpacingCandidate', 'A B C label', [
            'page' => 1,
            'sourceStream' => 2,
            'positionedTextCandidate' => 'ABC label',
        ]);

        $t->same('A B C label', $actual);
    },

    'adds a readable foreground only for a dark PDF table fill' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();

        $t->same('#ffffff', $invoke($reader, 'pdfTableCellContrastColor', '#000000'));
        $t->same(null, $invoke($reader, 'pdfTableCellContrastColor', '#f6f7fa'));
    },

    'does not replace an explicit table-cell foreground when applying a dark PDF fill' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $rows = [[[
            'x1' => 10.0,
            'y1' => 10.0,
            'x2' => 110.0,
            'y2' => 40.0,
            'htmlAttributes' => ['style' => 'color:#ff0000'],
        ]]];
        $rectangles = [[
            'page' => 1,
            'x1' => 10.0,
            'y1' => 10.0,
            'x2' => 110.0,
            'y2' => 40.0,
            'fillColor' => '#000000',
        ]];

        $actual = $invoke($reader, 'withPositionedCellBackgrounds', $rows, $rectangles);

        $t->same('color:#ff0000; background-color:#000000', $actual[0][0]['htmlAttributes']['style']);
        $t->same(false, isset($actual[0][0]['htmlAttributes']['data-pdf-contrast-color']));
    },

    'does not override an explicit continuation flag from gap size alone' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();

        $t->same('', $invoke($reader, 'positionedBoundarySeparator', 7.0, 9.0, false, false, true, false, 'none'));
        $t->same('', $invoke($reader, 'positionedBoundarySeparator', 3.0, 9.0, false, false, true, false, 'none'));
        $t->same(' ', $invoke($reader, 'positionedBoundarySeparator', 3.0, 9.0, false, false, true, false, 'text-position-continuation'));
    },

    'keeps a same-font section label separate from sentence-case body text' => static function (TestRunner $t) use ($invoke, $layout): void {
        $reader = new PdfReader();
        $records = [
            ['text' => 'Stainless Steel Self-Locking Nut', 'layout' => $layout(0)],
            ['text' => 'The body begins on the next visual line and continues as prose.', 'layout' => $layout(1)],
        ];
        $merged = $invoke($reader, 'mergeRepairedPdfRecords', $records);
        $blocks = $invoke($reader, 'blocksFromLines', $merged);

        $t->same(['heading', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $blocks));
    },

    'splits an exact positioned heading prefix from an inlined source body' => static function (TestRunner $t) use ($invoke, $layout): void {
        $reader = new PdfReader();
        $geometry = $layout(0);
        $geometry['positionedTextCandidate'] = 'Methods and Materials';
        $parts = $invoke(
            $reader,
            'splitPdfPositionedHeadingLead',
            'Methods and Materials The article body begins here with enough words.',
            $geometry
        );

        $t->same(2, count($parts));
        $t->same('Methods and Materials', $parts[0]['text']);
        $t->same(true, $parts[0]['layout']['sourcePdfDisplayHeading']);
        $t->same('The article body begins here with enough words.', $parts[1]['text']);
    },

    'removes aligned numeric page furniture only after it repeats across pages' => static function (TestRunner $t) use ($invoke, $layout): void {
        $reader = new PdfReader();
        $footer = static function (int $page, string $text) use ($layout): array {
            $geometry = $layout(0, 300.0, $page);
            $geometry['x2'] = 310.0;
            $geometry['y1'] = 80.0;
            $geometry['y2'] = 90.0;
            return ['text' => $text, 'layout' => $geometry];
        };
        $records = [
            ['text' => 'First page body text.', 'layout' => $layout(1, 72.0, 1)],
            $footer(1, '1'),
            ['text' => 'Second page body text.', 'layout' => $layout(1, 72.0, 2)],
            $footer(2, '2'),
        ];

        $filtered = $invoke($reader, 'removeRepeatedPdfPageNumberRecords', $records);

        $t->same(['First page body text.', 'Second page body text.'], array_column($filtered, 'text'));
    },

    'keeps a lone numeric body or footer record without cross-page evidence' => static function (TestRunner $t) use ($invoke, $layout): void {
        $reader = new PdfReader();
        $number = $layout(0, 300.0, 1);
        $number['x2'] = 310.0;
        $number['y1'] = 80.0;
        $number['y2'] = 90.0;
        $records = [
            ['text' => 'Body text.', 'layout' => $layout(1)],
            ['text' => '7', 'layout' => $number],
        ];

        $filtered = $invoke($reader, 'removeRepeatedPdfPageNumberRecords', $records);

        $t->same(['Body text.', '7'], array_column($filtered, 'text'));
    },

    'accepts reverse source-order adjacency for overlapping RTL baseline fragments' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $left = ['runs' => [['text' => 'Python', 'order' => 28, 'lastOrder' => 32]]];
        $right = ['runs' => [['text' => 'و', 'order' => 27, 'lastOrder' => 27]]];

        $t->same(true, $invoke($reader, 'positionedProseRowsAreContiguousInSourceOrder', $left, $right));
    },

    'does not accept reverse source order on an ordinary LTR baseline' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $left = ['runs' => [['text' => 'later', 'order' => 28, 'lastOrder' => 32]]];
        $right = ['runs' => [['text' => 'earlier', 'order' => 27, 'lastOrder' => 27]]];

        $t->same(false, $invoke($reader, 'positionedProseRowsAreContiguousInSourceOrder', $left, $right));
    },

    'detects visual RTL storage from sustained source-order movement across geometry' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $runs = [];
        foreach (['ةي', 'جات', 'نلإا', ' نيسحت'] as $order => $text) {
            $runs[] = [
                'text' => $text,
                'order' => $order,
                'textX1' => 100.0 + ($order * 18.0),
                'textX2' => 115.0 + ($order * 18.0),
            ];
        }

        $t->same(true, $invoke($reader, 'positionedRunsUseVisualRtlOrder', $runs));
    },

    'keeps logical RTL source order when geometry advances toward the left' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $runs = [];
        foreach (['تح', 'سين', ' الإ', 'نتاجية'] as $order => $text) {
            $runs[] = [
                'text' => $text,
                'order' => $order,
                'textX1' => 180.0 - ($order * 18.0),
                'textX2' => 195.0 - ($order * 18.0),
            ];
        }

        $t->same(false, $invoke($reader, 'positionedRunsUseVisualRtlOrder', $runs));
    },

    'turns a visual Arabic line into logical text while preserving embedded Latin' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();

        $t->same(
            'تحسين الإنتاجية من خلال البرمجة بلغة R و Python',
            $invoke($reader, 'logicalTextFromVisualRtlLine', 'Python و R ةغلب ةجمربلا لالخ نم ةيجاتنلإا نيسحت', ['لإ'])
        );
    },

    'keeps multiword Latin phrases and numbers intact inside visual RTL text' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();

        $t->same(
            'الإصدار Data Science 2026 جاهز.',
            $invoke($reader, 'logicalTextFromVisualRtlLine', '.زهاج Data Science 2026 رادصلإا', ['لإ'])
        );
    },

    'keeps an internal PDF destination available for page-anchor resolution' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $blocks = [new AstNode('paragraph', ['text' => 'Read this passage.'], [
            new AstNode('text', ['text' => 'Read this passage.']),
        ])];

        $annotations = $invoke($reader, 'unambiguousLinkAnnotations', [[
            'text' => 'this passage',
            'uri' => '#pdf-page-1',
            'targetPage' => 1,
        ]], $blocks);

        $t->same('#pdf-page-1', $annotations[0]['uri'] ?? null);
    },

    'normalizes the RTL corpus fixture from visual glyph order through the public converter' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-docling-right-to-left-right_to_left_01.pdf';
        $html = PandocConverter::convertFile($path, 'pdf', 'html', [
            'readerOptions' => [
                'maxTextBytes' => 100000,
                'pdfGeometryTables' => true,
                'pdfRepairProseText' => true,
            ],
        ]);

        $t->contains('تحسين الإنتاجية', $html);
        $t->true(!str_contains($html, 'ةيجاتنلإا نيسحت'));
    },

    'pairs internal links with generated page anchors in the multicolumn corpus fixture' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/pandoc-showcase/samples/pdf-layout-unstructured-multicolumn-multi-column-2p.pdf';
        $html = PandocConverter::convertFile($path, 'pdf', 'html', [
            'readerOptions' => [
                'maxTextBytes' => 100000,
                'pdfGeometryTables' => true,
                'pdfRepairProseText' => true,
            ],
        ]);

        $t->contains('href="#pdf-page-1"', $html);
        $t->contains('id="pdf-page-1"', $html);
    },

    'accepts a compact four-line monospaced code band with strong syntax evidence' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $signature = static fn (float $pitch, int $evidence, float $coverage, int $leadingEvidence): array => [
            'center' => 100.0,
            'x1' => 72.0,
            'y1' => 90.0,
            'y2' => 100.0,
            'fontSize' => 9.0,
            'pitch' => $pitch,
            'codeEvidence' => $evidence,
            'leadingCodeEvidence' => $leadingEvidence,
            'codeCoverage' => $coverage,
        ];

        $t->same(true, $invoke($reader, 'positionedBandLooksLikeCode', [
            $signature(5.5, 2, 1.0, 2),
            $signature(5.6, 1, 0.5, 1),
            $signature(5.5, 1, 1.0, 1),
            $signature(5.6, 2, 1.0, 2),
        ]));
    },

    'rejects a short monospaced prose band without programming syntax' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $signatures = array_fill(0, 4, [
            'center' => 100.0,
            'x1' => 72.0,
            'y1' => 90.0,
            'y2' => 100.0,
            'fontSize' => 9.0,
            'pitch' => 5.5,
            'codeEvidence' => 0,
            'leadingCodeEvidence' => 0,
            'codeCoverage' => 0.0,
        ]);

        $t->same(false, $invoke($reader, 'positionedBandLooksLikeCode', $signatures));
    },

    'rejects a short syntax-bearing band that jumps between visual columns' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $signatures = [];
        foreach ([72.0, 320.0, 72.0, 72.0] as $x1) {
            $signatures[] = [
                'center' => 100.0,
                'x1' => $x1,
                'y1' => 90.0,
                'y2' => 100.0,
                'fontSize' => 9.0,
                'pitch' => 5.5,
                'codeEvidence' => 2,
                'leadingCodeEvidence' => 2,
                'codeCoverage' => 1.0,
            ];
        }

        $t->same(false, $invoke($reader, 'positionedBandLooksLikeCode', $signatures));
    },

    'rejects a short prose band when punctuation appears only after long sentence prefixes' => static function (TestRunner $t) use ($invoke): void {
        $reader = new PdfReader();
        $signatures = array_fill(0, 4, [
            'center' => 100.0,
            'x1' => 72.0,
            'y1' => 90.0,
            'y2' => 100.0,
            'fontSize' => 9.0,
            'pitch' => 5.5,
            'codeEvidence' => 3,
            'leadingCodeEvidence' => 0,
            'codeCoverage' => 1.0,
        ]);

        $t->same(false, $invoke($reader, 'positionedBandLooksLikeCode', $signatures));
    },

    'renders inferred dialogue as a WordPress paragraph instead of a preformatted block' => static function (TestRunner $t) use ($dialogueBlocks, $dialogueRecords): void {
        $blocks = $dialogueBlocks($dialogueRecords([
            'NARRATOR First line.',
            'CHORUS Second line.',
            'NARRATOR Third line.',
            'CHORUS Fourth line.',
        ]));
        $wordpress = PandocConverter::write(new AstNode('document', [], $blocks), 'wordpress');

        $t->contains('<!-- wp:paragraph -->', $wordpress);
        $t->contains('<strong>NARRATOR</strong><br/>First line.', $wordpress);
        $t->true(!str_contains($wordpress, '<!-- wp:verse -->'));
        $t->true(!str_contains($wordpress, '<!-- wp:code -->'));
        $t->true(!str_contains($wordpress, '<pre'));
    },
];
