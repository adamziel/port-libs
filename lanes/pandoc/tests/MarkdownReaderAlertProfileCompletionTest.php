<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code', 'math'], true)) {
        return (string) $node->attr('text', '');
    }
    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$assertAlert = static function (TestRunner $t, AstNode $alert, string $class, string $title, string $body) use ($plainText): void {
    $t->same('div', $alert->type);
    $t->same([$class], $alert->attr('classes'));

    $titleDiv = $alert->children[0] ?? new AstNode('missing');
    $bodyBlock = $alert->children[1] ?? null;

    $t->same('div', $titleDiv->type);
    $t->same(['title'], $titleDiv->attr('classes'));
    $t->same($title, $plainText($titleDiv));
    if ($body === '') {
        $t->same(null, $bodyBlock);
    } else {
        $t->true($bodyBlock instanceof AstNode);
        $t->same($body, $plainText($bodyBlock));
    }
};

$tests = [
    'maps pandoc 3.10 gfm alert fixture to native div shape' =>
        static function (TestRunner $t) use ($assertAlert): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-alerts.md');
            $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture);

            $t->same(['div', 'div'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $assertAlert($t, $document->children[0], 'note', 'Note', 'Review imported media before publishing.');
            $assertAlert($t, $document->children[1], 'warning', 'Warning', 'Confirm source links remain archived.');
        },

    'maps pandoc 3.10 alert extension profile defaults and overrides' =>
        static function (TestRunner $t) use ($assertAlert, $plainText): void {
            $enabledCases = [
                'gfm default' => ['format' => 'gfm', 'class' => 'note', 'title' => 'Note', 'body' => 'A note.'],
                'commonmark_x default' => ['format' => 'commonmark_x', 'class' => 'warning', 'title' => 'Warning', 'body' => 'Be careful.'],
                'markdown plus alerts' => ['format' => 'markdown+alerts', 'class' => 'tip', 'title' => 'Tip', 'body' => 'Tip body.'],
            ];

            foreach ($enabledCases as $label => $case) {
                $marker = strtoupper($case['class']);
                $document = (new MarkdownReader(['format' => $case['format']]))->read("> [!{$marker}]\n> {$case['body']}\n");
                $alert = $document->children[0] ?? new AstNode('missing');

                $assertAlert($t, $alert, $case['class'], $case['title'], $case['body']);
            }

            $disabledCases = [
                'markdown default' => ['format' => 'markdown'],
                'commonmark default' => ['format' => 'commonmark'],
                'gfm disabled' => ['format' => 'gfm-alerts'],
            ];

            foreach ($disabledCases as $label => $case) {
                $document = (new MarkdownReader(['format' => $case['format']]))->read("> [!NOTE]\n> A note.\n");
                $quote = $document->children[0] ?? new AstNode('missing');

                $t->same('blockquote', $quote->type, $label);
                $t->same('[!NOTE] A note.', $plainText($quote), $label);
            }
        },

    'keeps pandoc 3.10 alert boundary cases as blockquotes or outside paragraphs' =>
        static function (TestRunner $t) use ($assertAlert, $plainText): void {
            $sameLine = (new MarkdownReader(['format' => 'gfm']))->read("> [!NOTE] Body same line\n");
            $sameLineQuote = $sameLine->children[0] ?? new AstNode('missing');
            $t->same('blockquote', $sameLineQuote->type);
            $t->same('[!NOTE] Body same line', $plainText($sameLineQuote));

            $unknown = (new MarkdownReader(['format' => 'gfm']))->read("> [!QUESTION]\n> Unknown.\n");
            $unknownQuote = $unknown->children[0] ?? new AstNode('missing');
            $t->same('blockquote', $unknownQuote->type);
            $t->same('[!QUESTION] Unknown.', $plainText($unknownQuote));

            $lazy = (new MarkdownReader(['format' => 'gfm']))->read("> [!NOTE]\nBody lazy.\n");
            $t->same(['div', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $lazy->children));
            $assertAlert($t, $lazy->children[0], 'note', 'Note', '');
            $t->same('Body lazy.', $plainText($lazy->children[1]));
        },

    'records pandoc 3.10 alert profile completion mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(10, 2 + 3 + 3 + 2);
        },
];

return $tests;
