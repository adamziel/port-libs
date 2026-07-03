<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MediaWikiReader;
use PortLibs\Pandoc\PandocConverter;

$read = static fn (string $source): AstNode => (new MediaWikiReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    return implode('', array_map($plainText, $node->children));
};

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($nodes, ...$nodesOfType($child, $type));
    }

    return $nodes;
};

$types = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps core mediawiki blocks tables and inline semantics' =>
        static function (TestRunner $t) use ($read, $plainText, $nodesOfType, $types): void {
            $document = $read(<<<'WIKI'
= Title =

Intro with ''emphasis'', '''strong''', '''''both''''', <code>literal</code>, [[Target Page|Target label]], [https://example.org Example] and https://pandoc.org.

* first item
* second with [[Nested]]

# one
# two

; Term : Definition body with '''strong''' text.
: Orphan definition

 pre line one
 pre line two

----

{|
|+ Caption text
! Name !! Value
|-
| Alpha || Beta
|-
| Gamma || Delta
|}

Image [[File:cover.png|thumb|Cover image]]
WIKI);

            $blocks = $document->children;
            $links = $nodesOfType($document, 'link');
            $images = $nodesOfType($document, 'image');

            $t->same('mediawiki', $document->attr('sourceFormat'));
            $t->same([
                'heading',
                'paragraph',
                'bullet_list',
                'ordered_list',
                'definition_list',
                'code_block',
                'horizontal_rule',
                'table',
                'paragraph',
            ], $types($document));
            $t->same('Title', $plainText($blocks[0]));
            $t->same(['text', 'emph', 'text', 'strong', 'text', 'strong', 'text', 'code', 'text', 'link', 'text', 'link', 'text', 'link', 'text'], $types($blocks[1]));
            $t->same('Intro with emphasis, strong, both, literal, Target label, Example and https://pandoc.org.', $plainText($blocks[1]));
            $t->same('Target_Page', $links[0]->attr('url'));
            $t->same('Target label', $plainText($links[0]));
            $t->same('https://example.org', $links[1]->attr('url'));
            $t->same('https://pandoc.org', $links[2]->attr('url'));

            $t->same('first item', $plainText($blocks[2]->children[0]));
            $t->same('second with Nested', $plainText($blocks[2]->children[1]));
            $t->same('ordered_list', $blocks[3]->type);
            $t->same('one', $plainText($blocks[3]->children[0]));
            $t->same('two', $plainText($blocks[3]->children[1]));

            $t->same('Term', $blocks[4]->children[0]->attr('term'));
            $t->same('Definition body with strong text.', $plainText($blocks[4]->children[0]->children[1]));
            $t->same('', $blocks[4]->children[1]->attr('term'));
            $t->same('Orphan definition', $plainText($blocks[4]->children[1]->children[1]));

            $t->same("pre line one\npre line two", $blocks[5]->attr('text'));
            $t->same('Caption text', $blocks[7]->attr('caption'));
            $t->same('Name', $plainText($blocks[7]->children[0]->children[0]->children[0]));
            $t->same('Value', $plainText($blocks[7]->children[0]->children[0]->children[1]));
            $t->same('Alpha', $plainText($blocks[7]->children[1]->children[0]->children[0]));
            $t->same('Delta', $plainText($blocks[7]->children[1]->children[1]->children[1]));

            $t->same(1, count($images));
            $t->same('cover.png', $images[0]->attr('url'));
            $t->same('Cover image', $images[0]->attr('alt'));
            $t->same(['thumb'], $images[0]->attr('classes'));
        },

    'reads mediawiki through converter and renders shared outputs' =>
        static function (TestRunner $t): void {
            $source = <<<'WIKI'
== Heading ==

See [[Main Page|main]].
WIKI;

            $document = PandocConverter::read($source, 'mediawiki');
            $native = PandocConverter::write($document, 'native');
            $html = PandocConverter::write($document, 'html');

            $t->same('mediawiki', $document->attr('sourceFormat'));
            $t->contains('Header 2', $native);
            $t->contains('Link ( "" , [  ] , [  ] ) [ Str "main" ] ( "Main_Page" , "" )', $native);
            $t->contains('<h2>Heading</h2>', $html);
            $t->contains('<a href="Main_Page">main</a>', $html);
        },
];
