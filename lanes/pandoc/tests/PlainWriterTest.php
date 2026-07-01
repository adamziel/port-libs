<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PlainWriter;

return [
    'renders plain text with bounded wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Reviewer queue needs wrapping before import review.']),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Existing']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'source']),
                new AstNode('linebreak'),
                new AstNode('text', ['text' => 'line stays explicit.']),
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 24]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Reviewer queue needs',
            'wrapping before import',
            'review.',
            '',
            'Existing source',
            'line stays explicit.',
        ]), $result['text']);
        $t->same($result['text'], (new PlainWriter(['columns' => 24]))->write($document));
        $t->same('plain', $result['diagnostics']['writer']);
        $t->same('auto', $result['diagnostics']['wrapMode']);
        $t->same(24, $result['diagnostics']['columns']);
        $t->same(2, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(2, $result['diagnostics']['softWrapBreakCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(2, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(5, $result['diagnostics']['outputLineCount']);
        $t->same(22, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(0, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(9, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(9, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(0, $result['diagnostics']['tabBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxTabDisplayAdvance']);
        $t->same(0, $result['diagnostics']['zeroWidthSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['softHyphenBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['visibleBreakAfterOpportunityCount']);
        $t->same(0, $result['diagnostics']['protectedSeparatorCount']);
        $t->same(0, $result['diagnostics']['lineEndingNormalizationCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'paragraph',
            'sourceLineCount' => 1,
            'outputLineCount' => 3,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 51,
            'maxOutputDisplayWidth' => 22,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 2,
            'wrapSplitLineCount' => 1,
            'generatedWrapBreakCount' => 2,
            'lineFeedBreakCount' => 0,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 6,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
        $t->same(false, $result['diagnostics']['blocks'][1]['wrapped']);
    },
    'renders native table captions and rows as plain text' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $cell = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
        $document = new AstNode('document', [], [
            new AstNode('table', [
                'captionInlines' => [
                    $text('Migration'),
                    new AstNode('space'),
                    new AstNode('emph', [], [$text('queue')]),
                ],
            ], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        $cell([$text('Format')]),
                        $cell([$text('State')]),
                        $cell([$text('Notes')]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        $cell([new AstNode('strong', [], [$text('plain')])]),
                        $cell([$text('partial')]),
                        $cell([
                            new AstNode('paragraph', [], [$text('keeps caption')]),
                            new AstNode('paragraph', [], [$text('keeps row text')]),
                        ]),
                    ]),
                ]),
                new AstNode('table_foot', [], [
                    new AstNode('table_row', [], [
                        $cell([$text('Total')]),
                        $cell([$text('1')]),
                        $cell([$text('gap closed')]),
                    ]),
                ]),
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 80]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Migration queue',
            'Format | State | Notes',
            'plain | partial | keeps caption keeps row text',
            'Total | 1 | gap closed',
        ]), $result['text']);
        $t->same($result['text'], (new PlainWriter(['columns' => 80]))->write($document));
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(46, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same('table', $result['diagnostics']['blocks'][0]['blockType']);
        $t->same(false, $result['diagnostics']['blocks'][0]['wrapped']);
    },
    'separates multiple native table body groups in plain text' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $cell = static fn (array $children = []): AstNode => new AstNode('table_cell', [], $children);
        $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
        $document = new AstNode('document', [], [
            new AstNode('table', ['caption' => 'Body groups'], [
                new AstNode('table_head', [], [
                    $row([$cell([$text('Lane')]), $cell([$text('State')])]),
                ]),
                new AstNode('table_body', [
                    'headRows' => [
                        $row([$cell([$text('Core')]), $cell([$text('Ready')])]),
                    ],
                ], [
                    $row([$cell([$text('A')]), $cell([$text('1')])]),
                ]),
                new AstNode('table_body', [
                    'headRows' => [
                        $row([$cell([$text('Addon')]), $cell([$text('Ready')])]),
                    ],
                ], [
                    $row([$cell([$text('B')]), $cell([$text('2')])]),
                ]),
                new AstNode('table_foot', [], [
                    $row([$cell([$text('Total')]), $cell([$text('3')])]),
                ]),
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 80]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Body groups',
            'Lane | State',
            'Core | Ready',
            'A | 1',
            '',
            'Addon | Ready',
            'B | 2',
            'Total | 3',
        ]), $result['text']);
        $t->same($result['text'], (new PlainWriter(['columns' => 80]))->write($document));
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(7, $result['diagnostics']['outputLineCount']);
        $t->same(1, $result['diagnostics']['blankOutputLineCount']);
        $t->same(8, $result['diagnostics']['blocks'][0]['outputLineCount']);
        $t->same(1, $result['diagnostics']['blocks'][0]['blankOutputLineCount']);
    },
    'reports generated wrap breaks by source line in plain writer diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha beta gamma delta epsilon\nOne two three four\nShort"]),
        ]);

        $result = (new PlainWriter(['columns' => 12]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Alpha beta',
            'gamma delta',
            'epsilon',
            'One two',
            'three four',
            'Short',
        ]), $result['text']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(3, $result['diagnostics']['softWrapBreakCount']);
        $t->same(2, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(3, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(6, $result['diagnostics']['outputLineCount']);
        $t->same(11, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(2, $result['diagnostics']['hardBreakCount']);
        $t->same(7, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(3, $result['diagnostics']['blocks'][0]['sourceLineCount']);
        $t->same(6, $result['diagnostics']['blocks'][0]['outputLineCount']);
        $t->same(2, $result['diagnostics']['blocks'][0]['wrapSplitLineCount']);
        $t->same(3, $result['diagnostics']['blocks'][0]['generatedWrapBreakCount']);
        $t->same(30, $result['diagnostics']['blocks'][0]['maxSourceDisplayWidth']);
    },
    'reports wrapped source line records in plain writer diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha beta gamma delta\nShort\nReviewerIdentifier"]),
        ]);

        $result = (new PlainWriter(['columns' => 10]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Alpha beta',
            'gamma',
            'delta',
            'Short',
            'ReviewerId',
            'entifier',
        ]), $result['text']);
        $t->same(2, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(3, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(2, $result['diagnostics']['maxGeneratedWrapBreaksPerSourceLine']);
        $t->same(2, $result['diagnostics']['wrappedSourceLineCount']);
        $t->same(16, $result['diagnostics']['wrappedSourceLineSampleLimit']);
        $t->same(false, $result['diagnostics']['wrappedSourceLinesTruncated']);
        $t->same([
            [
                'blockIndex' => 0,
                'lineIndex' => 0,
                'sourceDisplayWidth' => 22,
                'outputLineCount' => 3,
                'generatedBreakCount' => 2,
                'maxOutputDisplayWidth' => 10,
                'forcedWrapBreakCount' => 0,
                'text' => 'Alpha beta gamma delta',
                'truncated' => false,
            ],
            [
                'blockIndex' => 0,
                'lineIndex' => 2,
                'sourceDisplayWidth' => 18,
                'outputLineCount' => 2,
                'generatedBreakCount' => 1,
                'maxOutputDisplayWidth' => 10,
                'forcedWrapBreakCount' => 1,
                'text' => 'ReviewerIdentifier',
                'truncated' => false,
            ],
        ], $result['diagnostics']['wrappedSourceLines']);
    },
    'reports wrapped source line sample truncation in plain writer diagnostics' => static function (TestRunner $t): void {
        $sourceLines = array_fill(0, 18, 'Alpha beta gamma delta');
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => implode("\n", $sourceLines)]),
        ]);

        $result = (new PlainWriter(['columns' => 10]))->writeWithDiagnostics($document);

        $t->same(18, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(36, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(2, $result['diagnostics']['maxGeneratedWrapBreaksPerSourceLine']);
        $t->same(18, $result['diagnostics']['wrappedSourceLineCount']);
        $t->same(16, $result['diagnostics']['wrappedSourceLineSampleLimit']);
        $t->same(true, $result['diagnostics']['wrappedSourceLinesTruncated']);
        $t->same(16, count($result['diagnostics']['wrappedSourceLines']));
        $t->same(0, $result['diagnostics']['wrappedSourceLines'][0]['lineIndex']);
        $t->same(15, $result['diagnostics']['wrappedSourceLines'][15]['lineIndex']);
        $t->same(2, $result['diagnostics']['wrappedSourceLines'][15]['generatedBreakCount']);
        $t->same(18, $result['diagnostics']['blocks'][0]['wrapSplitLineCount']);
        $t->same(36, $result['diagnostics']['blocks'][0]['generatedWrapBreakCount']);
    },
    'reports over column lines in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "LongIdentifierWithoutBreaks short\nTiny\nReviewer diagnostics overflow"]),
        ]);

        $result = (new PlainWriter(['columns' => 12, 'wrap' => 'none']))->writeWithDiagnostics($document);

        $t->same("LongIdentifierWithoutBreaks short\nTiny\nReviewer diagnostics overflow", $result['text']);
        $t->same('none', $result['diagnostics']['wrapMode']);
        $t->same(12, $result['diagnostics']['columns']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(0, $result['diagnostics']['wrappedBlockCount']);
        $t->same(0, $result['diagnostics']['softWrapBreakCount']);
        $t->same(0, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(0, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(3, $result['diagnostics']['outputLineCount']);
        $t->same(33, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(2, $result['diagnostics']['overColumnLineCount']);
        $t->same(33, $result['diagnostics']['maxOverColumnDisplayWidth']);
        $t->same(2, $result['diagnostics']['hardBreakCount']);
        $t->same(3, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 3,
            'outputLineCount' => 3,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 33,
            'maxOutputDisplayWidth' => 33,
            'overColumnLineCount' => 2,
            'maxOverColumnDisplayWidth' => 33,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => false,
            'softWrapBreakCount' => 0,
            'wrapSplitLineCount' => 0,
            'generatedWrapBreakCount' => 0,
            'lineFeedBreakCount' => 2,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 3,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports tab break opportunities in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "A\tBeta reviewer\tTail\nPlain\trow"]),
        ]);

        $result = (new PlainWriter(['columns' => 12]))->writeWithDiagnostics($document);

        $t->same("A Beta\nreviewer\nTail\nPlain row", $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(2, $result['diagnostics']['softWrapBreakCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(2, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(9, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(4, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(1, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(3, $result['diagnostics']['tabBreakOpportunityCount']);
        $t->same(3, $result['diagnostics']['maxTabDisplayAdvance']);
        $t->same(0, $result['diagnostics']['overColumnLineCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 2,
            'outputLineCount' => 4,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 24,
            'maxOutputDisplayWidth' => 9,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 2,
            'wrapSplitLineCount' => 1,
            'generatedWrapBreakCount' => 2,
            'lineFeedBreakCount' => 1,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 1,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 3,
            'maxTabDisplayAdvance' => 3,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports unicode space break opportunities in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha Beta\u{2009}Gamma Delta\u{3000}Tail"]),
        ]);

        $result = (new PlainWriter(['columns' => 18]))->writeWithDiagnostics($document);

        $t->same("Alpha Beta\u{2009}Gamma\nDelta\u{3000}Tail", $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(1, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(2, $result['diagnostics']['outputLineCount']);
        $t->same(16, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(4, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(0, $result['diagnostics']['tabBreakOpportunityCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 1,
            'outputLineCount' => 2,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 28,
            'maxOutputDisplayWidth' => 16,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 1,
            'wrapSplitLineCount' => 1,
            'generatedWrapBreakCount' => 1,
            'lineFeedBreakCount' => 0,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 2,
            'unicodeSpaceBreakOpportunityCount' => 2,
            'maxUnicodeSpaceDisplayAdvance' => 2,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
    'uses unicode display width for plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => "漢字 queue"]),
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 8]))->writeWithDiagnostics($document);

        $t->same("漢字\nqueue", $result['text']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(1, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(5, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(10, $result['diagnostics']['blocks'][0]['maxSourceDisplayWidth']);
        $t->same(5, $result['diagnostics']['blocks'][0]['maxOutputDisplayWidth']);
    },
    'reports protected separators and normalized line endings in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha beta\r\nLocked\u{00A0}phrase tail"]),
        ]);

        $result = (new PlainWriter(['columns' => 16]))->writeWithDiagnostics($document);

        $t->same("Alpha beta\nLocked\u{00A0}phrase\ntail", $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(1, $result['diagnostics']['softWrapBreakCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(1, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(3, $result['diagnostics']['outputLineCount']);
        $t->same(13, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(2, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(0, $result['diagnostics']['zeroWidthSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['softHyphenBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['visibleBreakAfterOpportunityCount']);
        $t->same(1, $result['diagnostics']['protectedSeparatorCount']);
        $t->same(1, $result['diagnostics']['lineEndingNormalizationCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 2,
            'outputLineCount' => 3,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 18,
            'maxOutputDisplayWidth' => 13,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 1,
            'wrapSplitLineCount' => 1,
            'generatedWrapBreakCount' => 1,
            'lineFeedBreakCount' => 1,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 2,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 1,
            'lineEndingNormalizationCount' => 1,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports overlong unbreakable spans in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => "review\u{00A0}packet"]),
                new AstNode('space'),
                new AstNode('text', ['text' => 'supercalifragilistic']),
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 10]))->writeWithDiagnostics($document);

        $t->same("review\u{00A0}pac\nket\nsupercalif\nragilistic", $result['text']);
        $t->same(1, $result['diagnostics']['protectedSeparatorCount']);
        $t->same(20, $result['diagnostics']['maxUnbreakableDisplayWidth']);
        $t->same(2, $result['diagnostics']['overlongUnbreakableSpanCount']);
        $t->same(2, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(20, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same([
            [
                'blockIndex' => 0,
                'lineIndex' => 0,
                'displayWidth' => 13,
                'columns' => 10,
                'text' => "review\u{00A0}packet",
                'truncated' => false,
            ],
            [
                'blockIndex' => 0,
                'lineIndex' => 0,
                'displayWidth' => 20,
                'columns' => 10,
                'text' => 'supercalifragilistic',
                'truncated' => false,
            ],
        ], $result['diagnostics']['overlongUnbreakableSpans']);
    },
    'reports wrap control break opportunities in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha\u{00AD}Beta Gamma\u{200B}Delta Tibetan\u{0F0B}mark"]),
        ]);

        $result = (new PlainWriter(['columns' => 8]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Alpha-',
            'Beta',
            'Gamma',
            'Delta',
            "Tibetan\u{0F0B}",
            'mark',
        ]), $result['text']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(5, $result['diagnostics']['softWrapBreakCount']);
        $t->same(1, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(5, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(6, $result['diagnostics']['outputLineCount']);
        $t->same(8, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(5, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(1, $result['diagnostics']['zeroWidthSpaceBreakOpportunityCount']);
        $t->same(1, $result['diagnostics']['softHyphenBreakOpportunityCount']);
        $t->same(1, $result['diagnostics']['visibleBreakAfterOpportunityCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 1,
            'outputLineCount' => 6,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 33,
            'maxOutputDisplayWidth' => 8,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 5,
            'wrapSplitLineCount' => 1,
            'generatedWrapBreakCount' => 5,
            'lineFeedBreakCount' => 0,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 2,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 1,
            'softHyphenBreakOpportunityCount' => 1,
            'visibleBreakAfterOpportunityCount' => 1,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports inserted soft wrap breaks in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha beta gamma\nWide tail piece"]),
        ]);

        $result = (new PlainWriter(['columns' => 10]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'Alpha beta',
            'gamma',
            'Wide tail',
            'piece',
        ]), $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(2, $result['diagnostics']['softWrapBreakCount']);
        $t->same(2, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(2, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(0, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(1, $result['diagnostics']['lineFeedBreakCount']);
        $t->same(4, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['blocks'][0]['sourceLineCount']);
        $t->same(4, $result['diagnostics']['blocks'][0]['outputLineCount']);
        $t->same(true, $result['diagnostics']['blocks'][0]['wrapped']);
        $t->same(2, $result['diagnostics']['blocks'][0]['softWrapBreakCount']);
        $t->same(2, $result['diagnostics']['blocks'][0]['wrapSplitLineCount']);
        $t->same(2, $result['diagnostics']['blocks'][0]['generatedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['blocks'][0]['forcedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['blocks'][0]['maxForcedWrapSegmentDisplayWidth']);
        $t->same(1, $result['diagnostics']['blocks'][0]['lineFeedBreakCount']);
    },
    'reports blank lines in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha queue\n\nBeta tail"]),
        ]);

        $result = (new PlainWriter(['columns' => 8]))->writeWithDiagnostics($document);

        $t->same("Alpha\nqueue\n\nBeta\ntail", $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(2, $result['diagnostics']['softWrapBreakCount']);
        $t->same(2, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(2, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(1, $result['diagnostics']['blankSourceLineCount']);
        $t->same(1, $result['diagnostics']['blankOutputLineCount']);
        $t->same(5, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(0, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same(2, $result['diagnostics']['hardBreakCount']);
        $t->same(2, $result['diagnostics']['lineFeedBreakCount']);
        $t->same(2, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 3,
            'outputLineCount' => 5,
            'blankSourceLineCount' => 1,
            'blankOutputLineCount' => 1,
            'maxSourceDisplayWidth' => 11,
            'maxOutputDisplayWidth' => 5,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 2,
            'wrapSplitLineCount' => 2,
            'generatedWrapBreakCount' => 2,
            'lineFeedBreakCount' => 2,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 2,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports unicode hard separator counts in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', ['text' => "Alpha\u{2028}Beta Gamma\u{2029}Delta\r\nEpsilon"]),
        ]);

        $result = (new PlainWriter(['columns' => 20]))->writeWithDiagnostics($document);

        $t->same("Alpha\nBeta Gamma\nDelta\nEpsilon", $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(0, $result['diagnostics']['softWrapBreakCount']);
        $t->same(0, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(0, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(10, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(0, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(0, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same(3, $result['diagnostics']['hardBreakCount']);
        $t->same(1, $result['diagnostics']['lineFeedBreakCount']);
        $t->same(1, $result['diagnostics']['lineSeparatorBreakCount']);
        $t->same(1, $result['diagnostics']['paragraphSeparatorBreakCount']);
        $t->same(1, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(1, $result['diagnostics']['spaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['unicodeSpaceBreakOpportunityCount']);
        $t->same(0, $result['diagnostics']['maxUnicodeSpaceDisplayAdvance']);
        $t->same(1, $result['diagnostics']['lineEndingNormalizationCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 2,
            'outputLineCount' => 4,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 20,
            'maxOutputDisplayWidth' => 10,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 0,
            'maxForcedWrapSegmentDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 0,
            'wrapSplitLineCount' => 0,
            'generatedWrapBreakCount' => 0,
            'lineFeedBreakCount' => 1,
            'lineSeparatorBreakCount' => 1,
            'paragraphSeparatorBreakCount' => 1,
            'spaceBreakOpportunityCount' => 1,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 0,
            'lineEndingNormalizationCount' => 1,
        ], $result['diagnostics']['blocks'][0]);
    },
    'reports forced token splits in plain writer wrapping diagnostics' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('code_block', [
                'text' => "ReviewerQueueIdentifierNeedsManualInspection short\nLocked\u{00A0}phrase\u{00A0}without\u{00A0}breaks",
            ]),
        ]);

        $result = (new PlainWriter(['columns' => 10]))->writeWithDiagnostics($document);

        $t->same(implode("\n", [
            'ReviewerQu',
            'eueIdentif',
            'ierNeedsMa',
            'nualInspec',
            'tion short',
            "Locked\u{00A0}phr",
            "ase\u{00A0}withou",
            "t\u{00A0}breaks",
        ]), $result['text']);
        $t->same(1, $result['diagnostics']['blockCount']);
        $t->same(1, $result['diagnostics']['wrappedBlockCount']);
        $t->same(8, $result['diagnostics']['outputLineCount']);
        $t->same(10, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(0, $result['diagnostics']['overColumnLineCount']);
        $t->same(0, $result['diagnostics']['maxOverColumnDisplayWidth']);
        $t->same(6, $result['diagnostics']['forcedWrapBreakCount']);
        $t->same(44, $result['diagnostics']['maxForcedWrapSegmentDisplayWidth']);
        $t->same(6, $result['diagnostics']['softWrapBreakCount']);
        $t->same(2, $result['diagnostics']['wrapSplitLineCount']);
        $t->same(6, $result['diagnostics']['generatedWrapBreakCount']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(1, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(3, $result['diagnostics']['protectedSeparatorCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'code_block',
            'sourceLineCount' => 2,
            'outputLineCount' => 8,
            'blankSourceLineCount' => 0,
            'blankOutputLineCount' => 0,
            'maxSourceDisplayWidth' => 50,
            'maxOutputDisplayWidth' => 10,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'forcedWrapBreakCount' => 6,
            'maxForcedWrapSegmentDisplayWidth' => 44,
            'wrapped' => true,
            'softWrapBreakCount' => 6,
            'wrapSplitLineCount' => 2,
            'generatedWrapBreakCount' => 6,
            'lineFeedBreakCount' => 1,
            'lineSeparatorBreakCount' => 0,
            'paragraphSeparatorBreakCount' => 0,
            'spaceBreakOpportunityCount' => 1,
            'unicodeSpaceBreakOpportunityCount' => 0,
            'maxUnicodeSpaceDisplayAdvance' => 0,
            'tabBreakOpportunityCount' => 0,
            'maxTabDisplayAdvance' => 0,
            'zeroWidthSpaceBreakOpportunityCount' => 0,
            'softHyphenBreakOpportunityCount' => 0,
            'visibleBreakAfterOpportunityCount' => 0,
            'protectedSeparatorCount' => 3,
            'lineEndingNormalizationCount' => 0,
        ], $result['diagnostics']['blocks'][0]);
    },
];
