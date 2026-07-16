<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$nodeText = static function (AstNode $node) use (&$nodeText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }
    if (($node->children ?? []) === [] && is_scalar($node->attr('text', null))) {
        return (string) $node->attr('text');
    }

    $parts = [];
    foreach ($node->children as $child) {
        $text = $nodeText($child);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
};

$quoteOpeners = [
    'plain paragraph' => ['markdown' => '> quoted', 'text' => 'quoted'],
    'strong inline paragraph' => ['markdown' => '> **strong** quoted', 'text' => 'strong quoted'],
    'link inline paragraph' => ['markdown' => '> quoted [link](https://example.test/audit)', 'text' => 'quoted link'],
    'one-space marker indent' => ['markdown' => ' > one-space quote', 'text' => 'one-space quote'],
    'two-space marker indent' => ['markdown' => '  > two-space quote', 'text' => 'two-space quote'],
    'three-space marker indent' => ['markdown' => '   > three-space quote', 'text' => 'three-space quote'],
    'tab after marker' => ['markdown' => ">\ttabbed quote", 'text' => 'tabbed quote'],
    'marked continuation paragraph' => ['markdown' => "> quoted line\n> continuation", 'text' => 'quoted line continuation'],
    'heading block' => ['markdown' => '> # Quoted Heading', 'text' => 'Quoted Heading'],
    'nested bullet block' => ['markdown' => '> - nested item', 'text' => 'nested item'],
];

$outsideParagraphs = [
    'plain outside' => ['markdown' => 'after paragraph', 'text' => 'after paragraph'],
    'strong outside' => ['markdown' => 'continued outside with **strong** markup', 'text' => 'continued outside with strong markup'],
    'link outside' => ['markdown' => 'lazy outside [link](https://example.test/next)', 'text' => 'lazy outside link'],
    'code outside' => ['markdown' => 'outside with `code` span', 'text' => 'outside with code span'],
    'numeric outside' => ['markdown' => 'outside plain 123', 'text' => 'outside plain 123'],
];

$cases = [];
foreach ($quoteOpeners as $quoteName => $quote) {
    foreach ($outsideParagraphs as $outsideName => $outside) {
        $cases[$quoteName . ' / ' . $outsideName] = [
            'markdown' => $quote['markdown'] . "\n>\n" . $outside['markdown'],
            'quoteText' => $quote['text'],
            'outsideText' => $outside['text'],
        ];
    }
}

$tests = [
    'records markdown reader block quote explicit blank surge mapped-case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    },
];

foreach ($cases as $name => $case) {
    $tests['maps commonmark block quote explicit blank termination surge ' . $name] = static function (TestRunner $t) use ($case, $nodeText): void {
        $document = (new MarkdownReader())->read($case['markdown']);
        $quote = $document->children[0] ?? new AstNode('missing');
        $outside = $document->children[1] ?? new AstNode('missing');

        $t->same(['blockquote', 'paragraph'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ), $case['markdown']);
        $t->same('blockquote', $quote->type);
        $t->same($case['quoteText'], $nodeText($quote), $case['markdown']);
        $t->same('paragraph', $outside->type);
        $t->same($case['outsideText'], $nodeText($outside), $case['markdown']);
    };
}

return $tests;
