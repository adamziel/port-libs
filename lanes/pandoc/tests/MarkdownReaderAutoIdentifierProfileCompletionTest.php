<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$readAutoIdentifierFixture = static function (array $options): AstNode {
    return (new MarkdownReader($options))->read(implode("\n", [
        '# Auto Heading',
        '',
        '[Auto Heading]',
        '',
        '# Auto Heading',
        '',
        '## Manual Heading {#manual}',
        '',
        '[Manual Heading]',
    ]));
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream markdown auto identifiers opt out for generated heading ids' =>
        static function (TestRunner $t) use ($readAutoIdentifierFixture, $childTypes): void {
            $document = $readAutoIdentifierFixture(['format' => 'markdown-auto_identifiers']);
            $firstHeading = $document->children[0] ?? new AstNode('missing');
            $shortcut = $document->children[1] ?? new AstNode('missing');
            $duplicateHeading = $document->children[2] ?? new AstNode('missing');

            $t->same('heading', $firstHeading->type);
            $t->same('Auto Heading', $firstHeading->attr('text'));
            $t->same('', $firstHeading->attr('id', ''));
            $t->same(null, $firstHeading->attr('htmlAttributes', null));
            $t->same('heading', $duplicateHeading->type);
            $t->same('', $duplicateHeading->attr('id', ''));
            $t->same('paragraph', $shortcut->type);
            $t->same(['link'], $childTypes($shortcut));
            $t->same('#', $shortcut->children[0]->attr('url'));
        },
    'keeps explicit heading ids and references when auto identifiers are disabled' =>
        static function (TestRunner $t) use ($readAutoIdentifierFixture, $childTypes): void {
            $document = $readAutoIdentifierFixture(['format' => 'markdown-auto_identifiers']);
            $manualHeading = $document->children[3] ?? new AstNode('missing');
            $manualReference = $document->children[4] ?? new AstNode('missing');
            $link = $manualReference->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('heading', $manualHeading->type);
            $t->same('Manual Heading', $manualHeading->attr('text'));
            $t->same('manual', $manualHeading->attr('id'));
            $t->same(['id' => 'manual'], $manualHeading->attr('htmlAttributes'));
            $t->same(['link'], $childTypes($manualReference));
            $t->same('#manual', $link->attr('url'));
            $t->contains('<h2 id="manual">Manual Heading</h2>', $blocks);
            $t->contains('<a href="#manual">Manual Heading</a>', $blocks);
        },
    'accepts upstream auto identifier extension aliases' =>
        static function (TestRunner $t) use ($readAutoIdentifierFixture): void {
            $aliasFormats = [
                'singular suffix' => ['format' => 'markdown-auto_identifier'],
                'auto id suffix' => ['format' => 'markdown-auto_id'],
                'auto ids suffix' => ['format' => 'markdown-auto_ids'],
                'configured string override' => ['format' => 'markdown', 'extensions' => '-auto_identifier'],
                'configured map override' => ['format' => 'markdown', 'extensions' => ['auto_ids' => false]],
            ];

            foreach ($aliasFormats as $label => $options) {
                $document = $readAutoIdentifierFixture($options);
                $heading = $document->children[0] ?? new AstNode('missing');
                $shortcut = $document->children[1] ?? new AstNode('missing');

                $t->same('', $heading->attr('id', ''), $label);
                $t->same('#', ($shortcut->children[0] ?? new AstNode('missing'))->attr('url'), $label);
            }
        },
    'keeps default generated heading identifiers intact' =>
        static function (TestRunner $t) use ($readAutoIdentifierFixture, $childTypes): void {
            $document = $readAutoIdentifierFixture(['format' => 'markdown']);
            $firstHeading = $document->children[0] ?? new AstNode('missing');
            $shortcut = $document->children[1] ?? new AstNode('missing');
            $duplicateHeading = $document->children[2] ?? new AstNode('missing');
            $link = $shortcut->children[0] ?? new AstNode('missing');

            $t->same('auto-heading', $firstHeading->attr('id'));
            $t->same(['link'], $childTypes($shortcut));
            $t->same('#auto-heading', $link->attr('url'));
            $t->same('auto-heading-1', $duplicateHeading->attr('id'));
        },
    'maps upstream generated heading id and implicit reference flavor split' =>
        static function (TestRunner $t) use ($readAutoIdentifierFixture): void {
            $cases = [
                'markdown' => ['format' => 'markdown', 'id' => 'auto-heading', 'shortcut' => 'link', 'url' => '#auto-heading'],
                'commonmark' => ['format' => 'commonmark', 'id' => '', 'shortcut' => 'text'],
                'commonmark_x' => ['format' => 'commonmark_x', 'id' => 'auto-heading', 'shortcut' => 'link', 'url' => '#auto-heading'],
                'gfm' => ['format' => 'gfm', 'id' => 'auto-heading', 'shortcut' => 'text'],
                'markdown_github' => ['format' => 'markdown_github', 'id' => 'auto-heading', 'shortcut' => 'text'],
                'markdown_strict' => ['format' => 'markdown_strict', 'id' => '', 'shortcut' => 'text'],
                'markdown_phpextra' => ['format' => 'markdown_phpextra', 'id' => '', 'shortcut' => 'text'],
                'markdown_mmd' => ['format' => 'markdown_mmd', 'id' => 'auto-heading', 'shortcut' => 'link', 'url' => '#auto-heading'],
                'gfm generated ids off' => ['format' => 'gfm-gfm_auto_identifiers', 'id' => '', 'shortcut' => 'text'],
                'gfm implicit references on' => ['format' => 'gfm+implicit_header_references', 'id' => 'auto-heading', 'shortcut' => 'link', 'url' => '#auto-heading'],
                'markdown implicit references off' => ['format' => 'markdown-implicit_header_references', 'id' => 'auto-heading', 'shortcut' => 'text'],
            ];

            foreach ($cases as $label => $case) {
                $document = $readAutoIdentifierFixture(['format' => $case['format']]);
                $heading = $document->children[0] ?? new AstNode('missing');
                $shortcut = $document->children[1] ?? new AstNode('missing');
                $firstInline = $shortcut->children[0] ?? new AstNode('missing');

                $t->same('heading', $heading->type, $label . ' heading');
                $t->same($case['id'], $heading->attr('id', ''), $label . ' generated id');
                if ($case['shortcut'] === 'link') {
                    $t->same('link', $firstInline->type, $label . ' shortcut link');
                    $t->same($case['url'], $firstInline->attr('url'), $label . ' shortcut url');
                } else {
                    $t->same('text', $firstInline->type, $label . ' shortcut literal');
                    $t->same('[Auto Heading]', $shortcut->attr('text'), $label . ' shortcut text');
                }
            }
        },
    'records upstream markdown auto identifier profile mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(20, 1 + 1 + 5 + 2 + 11);
        },
];
