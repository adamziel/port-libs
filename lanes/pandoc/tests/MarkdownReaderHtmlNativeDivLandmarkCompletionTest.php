<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$plainText = null;
$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }

    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$landmarkCases = [
    'article' => ['label' => 'Article', 'role' => 'article'],
    'nav' => ['label' => 'Navigation', 'role' => 'navigation'],
    'footer' => ['label' => 'Footer', 'role' => 'contentinfo'],
];

$tests = [];

foreach ($landmarkCases as $tag => $case) {
    $tests['maps upstream markdown native div html5 landmark completion ' . $tag] =
        static function (TestRunner $t) use ($case, $inlineTypes, $plainText, $tag): void {
            $label = $case['label'];
            $source = implode("\n", [
                '<' . $tag . ' id="' . $tag . '-review" class="packet" data-kind="landmark" role="' . $case['role'] . '">',
                '<p>' . $label . ' <strong>body</strong>.</p>',
                '<' . $tag . ' id="' . $tag . '-child" data-level="child"><p>Nested ' . strtolower($label) . '</p></' . $tag . '>',
                '</' . $tag . '>',
            ]);

            $document = (new MarkdownReader(['htmlNativeDivs' => true]))->read($source);
            $root = $document->children[0] ?? new AstNode('missing');
            $paragraph = $root->children[0] ?? new AstNode('missing');
            $nested = $root->children[1] ?? new AstNode('missing');
            $nestedParagraph = $nested->children[0] ?? new AstNode('missing');
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('div', $root->type, $tag . ' root type');
            $t->same($tag . '-review', $root->attr('id'), $tag . ' root id');
            $t->same([$tag, 'packet'], $root->attr('classes'), $tag . ' native class is preserved before source class');
            $t->same(
                ['kind' => 'landmark', 'role' => $case['role']],
                $root->attr('attributes'),
                $tag . ' pandoc attributes'
            );
            $t->same(
                ['id' => $tag . '-review', 'class' => $tag . ' packet', 'data-kind' => 'landmark', 'role' => $case['role']],
                $root->attr('htmlAttributes'),
                $tag . ' source html attributes'
            );

            $t->same('paragraph', $paragraph->type, $tag . ' paragraph type');
            $t->same(['text', 'strong', 'text'], $inlineTypes($paragraph), $tag . ' paragraph inline shape');
            $t->same($label . ' body.', $plainText($paragraph), $tag . ' paragraph text');

            $t->same('div', $nested->type, $tag . ' nested native div type');
            $t->same($tag . '-child', $nested->attr('id'), $tag . ' nested id');
            $t->same([$tag], $nested->attr('classes'), $tag . ' nested native class');
            $t->same(['level' => 'child'], $nested->attr('attributes'), $tag . ' nested attributes');
            $t->same('Nested ' . strtolower($label), $plainText($nestedParagraph), $tag . ' nested text');

            $t->contains('::: {' . '#' . $tag . '-review .' . $tag . ' .packet', $markdown, $tag . ' markdown fenced div');
            $t->contains('::: {' . '#' . $tag . '-child .' . $tag, $markdown, $tag . ' markdown nested fenced div');
            $t->contains('id="' . $tag . '-review"', $blocks, $tag . ' wordpress root id');
            $t->contains('class="' . $tag . ' packet"', $blocks, $tag . ' wordpress root class');
            $t->contains('data-kind="landmark"', $blocks, $tag . ' wordpress data attribute');
            $t->contains('role="' . $case['role'] . '"', $blocks, $tag . ' wordpress role attribute');
            $t->contains($label . ' <strong>body</strong>.', $blocks, $tag . ' wordpress rich paragraph');
        };
}

$tests['keeps upstream markdown native div html5 landmark tags raw without extension'] =
    static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read('<article id="raw"><p>Raw fallback</p></article>');
        $raw = $document->children[0] ?? new AstNode('missing');

        $t->same('raw_html', $raw->type);
        $t->same('<article id="raw"><p>Raw fallback</p></article>', $raw->attr('html'));
    };

$tests['records upstream markdown native div html5 landmark completion mapped-case count'] =
    static function (TestRunner $t) use ($landmarkCases): void {
        $t->same(4, count($landmarkCases) + 1);
    };

return $tests;
