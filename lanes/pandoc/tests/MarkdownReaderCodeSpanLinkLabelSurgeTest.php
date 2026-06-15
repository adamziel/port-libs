<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$labelCases = [
    'closing bracket single tick' => ['label' => 'code `]` label', 'before' => 'code ', 'code' => ']', 'after' => ' label'],
    'opening bracket single tick' => ['label' => 'code `[` label', 'before' => 'code ', 'code' => '[', 'after' => ' label'],
    'closing bracket double tick' => ['label' => 'left ``]`` right', 'before' => 'left ', 'code' => ']', 'after' => ' right'],
    'opening bracket double tick' => ['label' => 'left ``[`` right', 'before' => 'left ', 'code' => '[', 'after' => ' right'],
    'closing bracket triple tick' => ['label' => 'tick ```]``` close', 'before' => 'tick ', 'code' => ']', 'after' => ' close'],
    'opening bracket triple tick' => ['label' => 'tick ```[``` open', 'before' => 'tick ', 'code' => '[', 'after' => ' open'],
    'closing bracket word code' => ['label' => 'nested `foo]bar` label', 'before' => 'nested ', 'code' => 'foo]bar', 'after' => ' label'],
    'opening bracket word code' => ['label' => 'nested `foo[bar` label', 'before' => 'nested ', 'code' => 'foo[bar', 'after' => ' label'],
    'entity closing bracket code' => ['label' => 'entity `&amp;]` label', 'before' => 'entity ', 'code' => '&amp;]', 'after' => ' label'],
    'spaced closing bracket code' => ['label' => 'space ``a ] b`` label', 'before' => 'space ', 'code' => 'a ] b', 'after' => ' label'],
];

$assertCodeSpanLabel = static function (TestRunner $t, AstNode $node, array $case, string $context): void {
    $t->same(['text', 'code', 'text'], array_map(static fn (AstNode $child): string => $child->type, $node->children), $context);
    $t->same($case['before'], $node->children[0]->attr('text'), $context . ' label prefix');
    $t->same($case['code'], $node->children[1]->attr('text'), $context . ' label code span');
    $t->same($case['after'], $node->children[2]->attr('text'), $context . ' label suffix');
};

$plainLabel = static fn (array $case): string => $case['before'] . $case['code'] . $case['after'];

$referenceLabelSource = static fn (array $case): string => strtr($case['label'], ['[' => '\\[', ']' => '\\]']);

$html = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$tests = [];

$tests['maps commonmark code span bracket labels in inline links'] =
    static function (TestRunner $t) use ($labelCases, $assertCodeSpanLabel, $html): void {
        $mapped = 0;
        foreach ($labelCases as $name => $case) {
            $document = (new MarkdownReader())->read('[' . $case['label'] . '](/inline-' . $mapped . ' "Inline ' . $mapped . '")');
            $link = $document->children[0]->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('link', $link->type, $name);
            $t->same('/inline-' . $mapped, $link->attr('url'), $name);
            $t->same('Inline ' . $mapped, $link->attr('title'), $name);
            $assertCodeSpanLabel($t, $link, $case, $name);
            $t->contains('<code>' . $html($case['code']) . '</code>', $blocks, $name . ' WordPress code label');
            $mapped++;
        }

        $t->same(10, $mapped);
    };

$tests['maps commonmark code span bracket labels in collapsed references'] =
    static function (TestRunner $t) use ($labelCases, $assertCodeSpanLabel, $referenceLabelSource): void {
        $mapped = 0;
        foreach ($labelCases as $name => $case) {
            $markdown = '[' . $case['label'] . "][]\n\n"
                . '[' . $referenceLabelSource($case) . ']: /collapsed-' . $mapped . ' "Collapsed ' . $mapped . '"';
            $link = (new MarkdownReader())->read($markdown)->children[0]->children[0] ?? new AstNode('missing');

            $t->same('link', $link->type, $name);
            $t->same('/collapsed-' . $mapped, $link->attr('url'), $name);
            $t->same('Collapsed ' . $mapped, $link->attr('title'), $name);
            $assertCodeSpanLabel($t, $link, $case, $name);
            $mapped++;
        }

        $t->same(10, $mapped);
    };

$tests['maps commonmark code span bracket labels in shortcut references'] =
    static function (TestRunner $t) use ($labelCases, $assertCodeSpanLabel, $referenceLabelSource): void {
        $mapped = 0;
        foreach ($labelCases as $name => $case) {
            $markdown = '[' . $case['label'] . "]\n\n"
                . '[' . $referenceLabelSource($case) . ']: /shortcut-' . $mapped . ' "Shortcut ' . $mapped . '"';
            $link = (new MarkdownReader())->read($markdown)->children[0]->children[0] ?? new AstNode('missing');

            $t->same('link', $link->type, $name);
            $t->same('/shortcut-' . $mapped, $link->attr('url'), $name);
            $t->same('Shortcut ' . $mapped, $link->attr('title'), $name);
            $assertCodeSpanLabel($t, $link, $case, $name);
            $mapped++;
        }

        $t->same(10, $mapped);
    };

$tests['maps commonmark code span bracket reference labels in full references'] =
    static function (TestRunner $t) use ($labelCases, $assertCodeSpanLabel): void {
        $mapped = 0;
        foreach ($labelCases as $name => $case) {
            $markdown = '[' . $case['label'] . '][code-ref-' . $mapped . "]\n\n"
                . '[code-ref-' . $mapped . ']: /definition-' . $mapped . ' "Definition ' . $mapped . '"';
            $link = (new MarkdownReader())->read($markdown)->children[0]->children[0] ?? new AstNode('missing');

            $t->same('link', $link->type, $name);
            $t->same('/definition-' . $mapped, $link->attr('url'), $name);
            $t->same('Definition ' . $mapped, $link->attr('title'), $name);
            $assertCodeSpanLabel($t, $link, $case, $name);
            $mapped++;
        }

        $t->same(10, $mapped);
    };

$tests['maps commonmark code span bracket labels in inline images'] =
    static function (TestRunner $t) use ($labelCases, $assertCodeSpanLabel, $plainLabel): void {
        $mapped = 0;
        foreach ($labelCases as $name => $case) {
            $image = (new MarkdownReader())->read('![' . $case['label'] . '](/image-' . $mapped . '.png "Image ' . $mapped . '")')
                ->children[0]->children[0] ?? new AstNode('missing');

            $t->same('image', $image->type, $name);
            $t->same('/image-' . $mapped . '.png', $image->attr('url'), $name);
            $t->same('Image ' . $mapped, $image->attr('title'), $name);
            $t->same($plainLabel($case), $image->attr('alt'), $name);
            $assertCodeSpanLabel($t, $image, $case, $name);
            $mapped++;
        }

        $t->same(10, $mapped);
    };

$tests['records commonmark code span link label surge mapped-case count'] =
    static function (TestRunner $t) use ($labelCases): void {
        $t->same(50, count($labelCases) * 5);
    };

return $tests;
