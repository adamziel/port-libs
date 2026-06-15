<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeText = static fn (string $value): string => (new MarkdownWriter())->write($document([$paragraph([$text($value)])]));
$reader = static fn (string $markdown): AstNode => (new MarkdownReader())->read($markdown);

$tests = [];

$htmlBlockTags = [
    'address',
    'article',
    'aside',
    'base',
    'basefont',
    'blockquote',
    'body',
    'caption',
    'center',
    'col',
    'colgroup',
    'dd',
    'details',
    'dialog',
    'dir',
    'div',
    'dl',
    'dt',
    'fieldset',
    'figcaption',
    'figure',
    'footer',
    'form',
    'frame',
    'frameset',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'head',
    'header',
    'hr',
    'html',
    'iframe',
    'legend',
    'li',
    'link',
    'main',
    'menu',
    'menuitem',
    'nav',
    'noframes',
    'ol',
    'optgroup',
    'option',
    'p',
    'param',
    'pre',
    'script',
    'search',
    'section',
    'summary',
    'style',
    'table',
    'tbody',
    'td',
    'tfoot',
    'th',
    'thead',
    'title',
    'tr',
    'track',
    'ul',
];

foreach ($htmlBlockTags as $tag) {
    $tests["maps upstream markdown writer raw html tag literal {$tag}"] =
        static function (TestRunner $t) use ($reader, $tag, $writeText): void {
            $source = '<' . $tag . '>';
            $markdown = $writeText($source);

            $t->same('&lt;' . $tag . '\\>', $markdown);

            $roundTrip = $reader($markdown);
            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            $t->same($source, $roundTrip->children[0]->attr('text'));
        };
}

$attributeTagCases = [
    'div class attribute' => ['<div class="review">', '&lt;div class=\\"review\\"\\>'],
    'table data attribute' => ['<table data-source="legacy">', '&lt;table data-source=\\"legacy\\"\\>'],
    'script type attribute' => ['<script type="text/plain">', '&lt;script type=\\"text/plain\\"\\>'],
    'style media attribute' => ['<style media="print">', '&lt;style media=\\"print\\"\\>'],
    'closing div tag' => ['</div>', '&lt;/div\\>'],
    'self closing hr tag' => ['<hr/>', '&lt;hr/\\>'],
];

foreach ($attributeTagCases as $label => [$source, $expected]) {
    $tests["maps upstream markdown writer raw html tag literal {$label}"] =
        static function (TestRunner $t) use ($expected, $reader, $source, $writeText): void {
            $markdown = $writeText($source);

            $t->same($expected, $markdown);

            $roundTrip = $reader($markdown);
            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            $t->same($source, $roundTrip->children[0]->attr('text'));
        };
}

$setextAndThematicCases = [
    'single equals underline' => ["Lead\n=", "Lead\n\\=", '='],
    'double equals underline' => ["Lead\n==", "Lead\n\\==", '=='],
    'triple equals underline' => ["Lead\n===", "Lead\n\\===", '==='],
    'wide equals underline' => ["Lead\n====", "Lead\n\\====", '===='],
    'triple dash underline' => ["Lead\n---", "Lead\n\\-\\-\\-", '---'],
    'quad dash underline' => ["Lead\n----", "Lead\n\\-\\-\\-\\-", '----'],
    'five dash underline' => ["Lead\n-----", "Lead\n\\-\\-\\-\\-\\-", '-----'],
    'seven dash underline' => ["Lead\n-------", "Lead\n\\-\\-\\-\\-\\-\\-\\-", '-------'],
];

foreach ($setextAndThematicCases as $label => [$source, $expected, $literalMarker]) {
    $tests["maps upstream markdown writer inline escape {$label}"] =
        static function (TestRunner $t) use ($expected, $literalMarker, $reader, $source, $writeText): void {
            $markdown = $writeText($source);

            $t->same($expected, $markdown);

            $roundTrip = $reader($markdown);
            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            $t->same(['text', 'softbreak', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $roundTrip->children[0]->children
            ));
            $t->same('Lead', $roundTrip->children[0]->children[0]->attr('text'));
            $t->same($literalMarker, $roundTrip->children[0]->children[2]->attr('text'));
        };
}

return $tests;
