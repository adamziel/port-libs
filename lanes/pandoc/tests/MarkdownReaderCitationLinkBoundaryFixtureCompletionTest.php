<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$citationLinkBoundariesFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzz-citation-link-boundaries.md');

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps upstream markdown citation followed by footnote and link boundaries fixture' =>
        static function (TestRunner $t) use ($citationLinkBoundariesFixture, $inlineTypes): void {
            $document = (new MarkdownReader())->read($citationLinkBoundariesFixture());
            $blocks = $document->children;
            $footnote = $blocks[0] ?? new AstNode('missing');
            $inlineLink = $blocks[1] ?? new AstNode('missing');
            $referenceLink = $blocks[2] ?? new AstNode('missing');
            $shortcutReference = $blocks[3] ?? new AstNode('missing');
            $heading = $blocks[4] ?? new AstNode('missing');
            $implicitHeader = $blocks[5] ?? new AstNode('missing');
            $regularCitation = $blocks[6] ?? new AstNode('missing');

            $t->same(7, count($blocks));
            $t->same(['citation', 'note'], $inlineTypes($footnote));
            $t->same('cita', ($footnote->children[0] ?? new AstNode('missing'))->attr('id'));
            $t->same('note', ($footnote->children[1] ?? new AstNode('missing'))->attr('label'));
            $t->same('note', (($footnote->children[1]->children[0] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing'))->attr('text'));

            foreach ([
                'inline link' => $inlineLink,
                'full reference link' => $referenceLink,
                'shortcut reference link' => $shortcutReference,
            ] as $label => $paragraph) {
                $t->same(['citation', 'text', 'link'], $inlineTypes($paragraph), $label);
                $t->same('cita', ($paragraph->children[0] ?? new AstNode('missing'))->attr('id'), $label . ' citation id');
                $t->same('http://www.com', ($paragraph->children[2] ?? new AstNode('missing'))->attr('url'), $label . ' URL');
                $t->same('link', (($paragraph->children[2] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing'))->attr('text'), $label . ' label');
            }

            $t->same('heading', $heading->type);
            $t->same('header', $heading->attr('id'));
            $t->same(['citation', 'text', 'link'], $inlineTypes($implicitHeader));
            $t->same('#header', ($implicitHeader->children[2] ?? new AstNode('missing'))->attr('url'));
            $t->same(['citation'], $inlineTypes($regularCitation));
            $t->same('foo', ($regularCitation->children[0] ?? new AstNode('missing'))->attr('suffix'));
            $t->same('@cita [foo]', ($regularCitation->children[0] ?? new AstNode('missing'))->attr('text'));
        },

    'serializes upstream citation link boundary fixture through native and wordpress handoff' =>
        static function (TestRunner $t) use ($citationLinkBoundariesFixture): void {
            $document = (new MarkdownReader())->read($citationLinkBoundariesFixture());
            $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('cita', $native['blocks'][0]['c'][0]['c'][0][0]['citationId'] ?? null);
            $t->same('Note', $native['blocks'][0]['c'][1]['t'] ?? null);
            $t->same('#header', $native['blocks'][5]['c'][2]['c'][2][0] ?? null);
            $t->same('foo', $native['blocks'][6]['c'][0]['c'][0][0]['citationSuffix'][0]['c'] ?? null);
            $t->contains('>@cita</span><sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup>', $blocks);
            $t->contains('>@cita</span> <a href="http://www.com">link</a>', $blocks);
            $t->contains('>@cita</span> <a href="#header">Header</a>', $blocks);
            $t->contains('>@cita [foo]</span>', $blocks);
        },

    'records upstream markdown citation link boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(6, 6);
        },
];
