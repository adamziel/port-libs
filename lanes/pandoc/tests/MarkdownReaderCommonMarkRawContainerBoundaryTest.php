<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim($text);
};

$rawForms = [
    'comment marker' => [
        'source' => "<!--\nraw *markdown*\n-->",
        'blankTerminated' => false,
    ],
    'processing instruction marker' => [
        'source' => "<?review\npacket?>",
        'blankTerminated' => false,
    ],
    'declaration marker' => [
        'source' => '<!DOCTYPE html>',
        'blankTerminated' => false,
    ],
    'cdata marker' => [
        'source' => "<![CDATA[\n# raw heading\n]]>",
        'blankTerminated' => false,
    ],
    'script closing marker' => [
        'source' => "<script type=\"application/json\">\n{\"review\":true}\n</script>",
        'blankTerminated' => false,
    ],
    'section blank line' => [
        'source' => "<section data-review=\"1\">\nraw **markdown**",
        'blankTerminated' => true,
    ],
    'type7 span blank line' => [
        'source' => "<span data-review=\"type7\">\nraw **markdown**\n</span>",
        'blankTerminated' => true,
        'leadingBlank' => true,
    ],
    'type7 source blank line' => [
        'source' => '<source src="clip.webm" type="video/webm">',
        'blankTerminated' => true,
        'leadingBlank' => true,
    ],
];

$prefixLines = static function (string $source, string $prefix): string {
    return implode(
        "\n",
        array_map(static fn (string $line): string => $prefix . $line, explode("\n", $source))
    );
};

$cases = [];
foreach ($rawForms as $name => $rawForm) {
    $source = $rawForm['source'];
    $blankTerminated = $rawForm['blankTerminated'];
    $leadingBlank = $rawForm['leadingBlank'] ?? false;

    $cases['blockquote ' . $name] = [
        'markdown' => '> before'
            . ($leadingBlank ? "\n>" : '')
            . "\n" . $prefixLines($source, '> ')
            . ($blankTerminated ? "\n>" : '')
            . "\n> after",
        'containerType' => 'blockquote',
        'innerOwnerType' => 'blockquote',
        'source' => $source,
    ];

    $cases['list ' . $name] = [
        'markdown' => '- before'
            . ($leadingBlank ? "\n" : '')
            . "\n" . $prefixLines($source, '  ')
            . ($blankTerminated ? "\n" : '')
            . "\n  after",
        'containerType' => 'bullet_list',
        'innerOwnerType' => 'list_item',
        'source' => $source,
    ];
}

$innerOwner = static function (AstNode $document, string $containerType): AstNode {
    $container = $document->children[0] ?? new AstNode('missing');
    if ($containerType === 'bullet_list') {
        return $container->children[0] ?? new AstNode('missing');
    }

    return $container;
};

$firstRawHtml = static function (array $children): AstNode {
    foreach ($children as $child) {
        if ($child instanceof AstNode && $child->type === 'raw_html') {
            return $child;
        }
    }

    return new AstNode('missing');
};

$tests = [
    'records commonmark raw container boundary mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(16, count($cases));
        },
];

foreach ($cases as $name => $case) {
    $tests['maps upstream commonmark raw container boundary ' . $name] =
        static function (TestRunner $t) use ($name, $case, $plainText, $innerOwner, $firstRawHtml): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
            $container = $document->children[0] ?? new AstNode('missing');
            $owner = $innerOwner($document, $case['containerType']);
            $raw = $firstRawHtml($owner->children);
            $first = $owner->children[0] ?? new AstNode('missing');
            $last = $owner->children[array_key_last($owner->children)] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter(['format' => 'commonmark']))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);
            $firstRawLine = strtok($case['source'], "\n") ?: $case['source'];

            $t->same($case['containerType'], $container->type, $name . ' container');
            $t->same($case['innerOwnerType'], $owner->type, $name . ' owner');
            $t->same('raw_html', $raw->type, $name . ' raw block');
            $t->same($case['source'], $raw->attr('html'), $name . ' raw source');
            $t->same('before', $plainText($first), $name . ' leading text');
            $t->same('after', $plainText($last), $name . ' trailing text');
            $t->contains($firstRawLine, $markdown, $name . ' markdown output');
            $t->contains('after', $markdown, $name . ' markdown resumes');
            $t->contains($case['source'], $blocks, $name . ' wordpress raw handoff');
        };
}

return $tests;
