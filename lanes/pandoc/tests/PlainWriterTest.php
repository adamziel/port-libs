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
        $t->same(5, $result['diagnostics']['outputLineCount']);
        $t->same(22, $result['diagnostics']['maxOutputDisplayWidth']);
        $t->same(1, $result['diagnostics']['hardBreakCount']);
        $t->same(9, $result['diagnostics']['softBreakOpportunityCount']);
        $t->same([
            'blockIndex' => 0,
            'blockType' => 'paragraph',
            'sourceLineCount' => 1,
            'outputLineCount' => 3,
            'maxSourceDisplayWidth' => 51,
            'maxOutputDisplayWidth' => 22,
            'wrapped' => true,
        ], $result['diagnostics']['blocks'][0]);
        $t->same(false, $result['diagnostics']['blocks'][1]['wrapped']);
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
];
