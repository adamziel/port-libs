<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-spaced-reference-link-profile.md'
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps pandoc markdown spaced reference link profile fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown-shortcut_reference_links+spaced_reference_links']))->read($fixture);
            $shortcut = $document->children[0] ?? new AstNode('missing');
            $spacedReference = $document->children[1] ?? new AstNode('missing');
            $link = $spacedReference->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same(['text'], $inlineTypes($shortcut));
            $t->same('[shortcut only]', $shortcut->attr('text'));
            $t->same(['link'], $inlineTypes($spacedReference));
            $t->same('/target', $link->attr('url'));
            $t->same('Target title', $link->attr('title'));
            $t->same('visible text', $spacedReference->attr('text'));
            $t->contains('Str "[shortcut"', $native);
            $t->contains('Link ( "" , [  ] , [  ] ) [ Str "visible" , Space , Str "text" ] ( "/target" , "Target title" )', $native);
        },

    'keeps pandoc markdown spaced reference link fixture profile-gated' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes): void {
            $default = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
            $shortcut = $default->children[0] ?? new AstNode('missing');
            $spacedReference = $default->children[1] ?? new AstNode('missing');

            $t->same(['link'], $inlineTypes($shortcut));
            $t->same('/shortcut', ($shortcut->children[0] ?? new AstNode('missing'))->attr('url'));
            $t->same(['text', 'link'], $inlineTypes($spacedReference));
            $t->same('[visible text] target ref', $spacedReference->attr('text'));
        },
];
