<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$nodeText = static function (AstNode $node) use (&$nodeText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }
    if ($node->children === [] && is_scalar($node->attr('text', null))) {
        return (string) $node->attr('text');
    }

    $parts = [];
    foreach ($node->children as $child) {
        $text = $nodeText($child);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/[ \t]+/', ' ', implode('', $parts)) ?? '');
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$documentTypes = static fn (AstNode $document): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $document->children
);

$lazyParagraphCases = [
    'single unmarked continuation' => [
        'markdown' => "> quoted\nlazy continuation",
        'text' => 'quoted lazy continuation',
        'inlineTypes' => ['text', 'softbreak', 'text'],
    ],
    'two unmarked continuations' => [
        'markdown' => "> quoted\nlazy one\nlazy two",
        'text' => 'quoted lazy one lazy two',
        'inlineTypes' => ['text', 'softbreak', 'text', 'softbreak', 'text'],
    ],
    'unmarked line between marked quote lines' => [
        'markdown' => "> quoted\nlazy middle\n> marked tail",
        'text' => 'quoted lazy middle marked tail',
        'inlineTypes' => ['text', 'softbreak', 'text', 'softbreak', 'text'],
    ],
    'inline markup across lazy lines' => [
        'markdown' => "> **strong**\nplain [link](/target)\n`code`",
        'text' => 'strong plain link code',
        'inlineTypes' => ['strong', 'softbreak', 'text', 'link', 'softbreak', 'code'],
    ],
    'backslash hard break before lazy line' => [
        'markdown' => "> first\\\nsecond",
        'text' => "first\nsecond",
        'inlineTypes' => ['text', 'linebreak', 'text'],
    ],
    'trailing-space hard break before lazy line' => [
        'markdown' => "> first  \nsecond",
        'text' => "first\nsecond",
        'inlineTypes' => ['text', 'linebreak', 'text'],
    ],
];

$boundaryCases = [
    'atx heading starts outside' => [
        'markdown' => "> quoted\n# outside",
        'types' => ['blockquote', 'heading'],
        'outsideType' => 'heading',
        'outsideText' => 'outside',
    ],
    'bullet list starts outside' => [
        'markdown' => "> quoted\n- outside",
        'types' => ['blockquote', 'bullet_list'],
        'outsideType' => 'bullet_list',
        'outsideText' => 'outside',
    ],
    'indented code starts outside' => [
        'markdown' => "> quoted\n    code",
        'types' => ['blockquote', 'code_block'],
        'outsideType' => 'code_block',
        'outsideText' => 'code',
    ],
    'fenced code starts outside' => [
        'markdown' => "> quoted\n```\ncode\n```",
        'types' => ['blockquote', 'code_block'],
        'outsideType' => 'code_block',
        'outsideText' => 'code',
    ],
    'thematic break starts outside' => [
        'markdown' => "> quoted\n***",
        'types' => ['blockquote', 'horizontal_rule'],
        'outsideType' => 'horizontal_rule',
        'outsideText' => '',
    ],
];

$tests = [];

foreach ($lazyParagraphCases as $name => $case) {
    $tests['maps upstream commonmark block quote lazy continuation completion ' . $name] =
        static function (TestRunner $t) use ($case, $childTypes, $nodeText): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
            $quote = $document->children[0] ?? new AstNode('missing');
            $paragraph = $quote->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['blockquote'], array_map(static fn (AstNode $node): string => $node->type, $document->children), $case['markdown']);
            $t->same('blockquote', $quote->type, $case['markdown']);
            $t->same('paragraph', $paragraph->type, $case['markdown']);
            $t->same($case['inlineTypes'], $childTypes($paragraph), $case['markdown']);
            $t->same($case['text'], $nodeText($paragraph), $case['markdown']);
            $t->contains('<blockquote', $blocks, $case['markdown']);
        };
}

foreach ($boundaryCases as $name => $case) {
    $tests['keeps upstream commonmark block quote lazy continuation boundary ' . $name] =
        static function (TestRunner $t) use ($case, $documentTypes, $nodeText): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
            $quote = $document->children[0] ?? new AstNode('missing');
            $outside = $document->children[1] ?? new AstNode('missing');

            $t->same($case['types'], $documentTypes($document), $case['markdown']);
            $t->same('blockquote', $quote->type, $case['markdown']);
            $t->same('quoted', $nodeText($quote), $case['markdown']);
            $t->same($case['outsideType'], $outside->type, $case['markdown']);
            $t->same($case['outsideText'], $nodeText($outside), $case['markdown']);
        };
}

$tests['records upstream commonmark block quote lazy continuation completion mapped-case count'] =
    static function (TestRunner $t) use ($lazyParagraphCases, $boundaryCases): void {
        $t->same(11, count($lazyParagraphCases) + count($boundaryCases));
    };

return $tests;
