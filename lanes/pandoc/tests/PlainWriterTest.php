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
        $t->same(5, $result['diagnostics']['outputLineCount']);
        $t->same(22, $result['diagnostics']['maxOutputDisplayWidth']);
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
            'maxSourceDisplayWidth' => 51,
            'maxOutputDisplayWidth' => 22,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 2,
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
            'maxSourceDisplayWidth' => 33,
            'maxOutputDisplayWidth' => 33,
            'overColumnLineCount' => 2,
            'maxOverColumnDisplayWidth' => 33,
            'wrapped' => false,
            'softWrapBreakCount' => 0,
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
            'maxSourceDisplayWidth' => 24,
            'maxOutputDisplayWidth' => 9,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 2,
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
            'maxSourceDisplayWidth' => 28,
            'maxOutputDisplayWidth' => 16,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 1,
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
            'maxSourceDisplayWidth' => 18,
            'maxOutputDisplayWidth' => 13,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 1,
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
            'maxSourceDisplayWidth' => 33,
            'maxOutputDisplayWidth' => 8,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 5,
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
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(1, $result['diagnostics']['lineFeedBreakCount']);
        $t->same(4, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same(2, $result['diagnostics']['blocks'][0]['sourceLineCount']);
        $t->same(4, $result['diagnostics']['blocks'][0]['outputLineCount']);
        $t->same(true, $result['diagnostics']['blocks'][0]['wrapped']);
        $t->same(2, $result['diagnostics']['blocks'][0]['softWrapBreakCount']);
        $t->same(1, $result['diagnostics']['blocks'][0]['lineFeedBreakCount']);
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
        $t->same(4, $result['diagnostics']['outputLineCount']);
        $t->same(10, $result['diagnostics']['maxOutputDisplayWidth']);
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
            'maxSourceDisplayWidth' => 20,
            'maxOutputDisplayWidth' => 10,
            'overColumnLineCount' => 0,
            'maxOverColumnDisplayWidth' => 0,
            'wrapped' => true,
            'softWrapBreakCount' => 0,
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
];
