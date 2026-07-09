<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MediaWikiReader;
use PortLibs\Pandoc\PandocConverter;

$read = static fn (string $source): AstNode => (new MediaWikiReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code', 'raw_inline'], true)) {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'linebreak' || $node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'image') {
        return (string) $node->attr('alt', '');
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
            $t->same('title', $blocks[0]->attr('id'));
            $t->same(['text', 'emph', 'text', 'strong', 'text', 'strong', 'text', 'code', 'text', 'link', 'text', 'link', 'text', 'link', 'text'], $types($blocks[1]));
            $t->same('emph', $blocks[1]->children[5]->children[0]->type);
            $t->same('Intro with emphasis, strong, both, literal, Target label, Example and https://pandoc.org.', $plainText($blocks[1]));
            $t->same('Target_Page', $links[0]->attr('url'));
            $t->same(['wikilink'], $links[0]->attr('classes'));
            $t->same('Target label', $links[0]->attr('title'));
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
            $t->same('Orphan definition', $plainText($blocks[4]->children[0]->children[2]));

            $t->same("pre line one\npre line two", $blocks[5]->attr('text'));
            $t->same('Caption text', $blocks[7]->attr('caption'));
            $t->same('Name', $plainText($blocks[7]->children[0]->children[0]->children[0]));
            $t->same('Value', $plainText($blocks[7]->children[0]->children[0]->children[1]));
            $t->same('Alpha', $plainText($blocks[7]->children[1]->children[0]->children[0]));
            $t->same('Delta', $plainText($blocks[7]->children[1]->children[1]->children[1]));

            $t->same(1, count($images));
            $t->same('cover.png', $images[0]->attr('url'));
            $t->same('Cover image', $images[0]->attr('alt'));
            $t->same('Cover image', $images[0]->attr('title'));
            $t->same([], $images[0]->attr('classes', []));
        },

    'handles comments nowiki math refs templates and heading bounds' =>
        static function (TestRunner $t) use ($read, $plainText, $nodesOfType, $types): void {
            $notHeading = str_repeat('=', 7) . ' not heading ' . str_repeat('=', 7);
            $document = $read(<<<WIKI
= Title =
====== Deep ======
{$notHeading}

Before <!-- hidden ''markup'' --> after &amp;.

<nowiki>''raw'' [[x]] &amp;</nowiki> <math>E=mc^2</math> <ref>Note ''body''</ref> A<br />B {{tmpl|x}} {{#if:y|a|b}}

{{CURRENTYEAR}}

Unexpanded {{outer|{{inner}}}} {{{parameter|fallback}}}.
WIKI);

            $blocks = $document->children;
            $t->same(['heading', 'heading', 'paragraph', 'paragraph', 'paragraph', 'paragraph'], $types($document));
            $t->same('title', $blocks[0]->attr('id'));
            $t->same('deep', $blocks[1]->attr('id'));
            $t->same($notHeading, $plainText($blocks[2]));
            $t->same('Before after &.', $plainText($blocks[3]));
            $t->same('E=mc^2', $nodesOfType($blocks[4], 'math')[0]->attr('text'));
            $t->same('Note body', $plainText($nodesOfType($blocks[4], 'note')[0]));
            $t->same(1, count($nodesOfType($blocks[4], 'linebreak')));
            $t->same("''raw'' [[x]] & E=mc^2 Note body A B {{#if:y|a|b}}", $plainText($blocks[4]));
            $t->same([], $nodesOfType($document, 'raw_inline'));
            $t->same([], $nodesOfType($document, 'raw_block'));
            $t->same('Unexpanded .', $plainText($blocks[5]));
        },

    'maps mediawiki table attributes and multiline rows' =>
        static function (TestRunner $t) use ($read, $plainText): void {
            $document = $read(<<<'WIKI'
{|
|+ Caption ''text''
! scope="col" style="text-align:left" | Name
! style="text-align:right" | Value
|-
| style="text-align:center" | Alpha || 42
|-
! Row head || Beta
|}
WIKI);

            $table = $document->children[0];
            $headRow = $table->children[0]->children[0];
            $bodyRows = $table->children[1]->children;

            $t->same('Caption text', $plainText(new AstNode('span', [], $table->attr('captionInlines'))));
            $t->same('Caption text', $table->attr('caption'));
            $t->same('Name', $plainText($headRow->children[0]));
            $t->same(['scope' => 'col', 'style' => 'text-align:left'], $headRow->children[0]->attr('attributes'));
            $t->same(['style' => 'text-align:right'], $headRow->children[1]->attr('attributes'));
            $t->same(['style' => 'text-align:center'], $bodyRows[0]->children[0]->attr('attributes'));
            $t->same('Row head', $plainText($bodyRows[1]->children[0]));
            $t->same(false, $bodyRows[1]->children[0]->attr('header'));
        },

    'reads html pre and syntaxhighlight code blocks' =>
        static function (TestRunner $t) use ($read, $types): void {
            $document = $read(<<<'WIKI'
<pre>
raw ''markup''
[[link]]
</pre>

<syntaxhighlight lang="php">
echo '<x>';
</syntaxhighlight>
WIKI);

            $t->same(['code_block', 'code_block'], $types($document));
            $t->same("raw ''markup''\n[[link]]", $document->children[0]->attr('text'));
            $t->same([], $document->children[0]->attr('classes'));
            $t->same("echo '<x>';", $document->children[1]->attr('text'));
            $t->same(['php'], $document->children[1]->attr('classes'));
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
            $t->contains('Header 2 ( "heading" , [  ] , [  ] ) [ Str "Heading" ]', $native);
            $t->contains('Link ( "" , [ "wikilink" ] , [  ] ) [ Str "main" ] ( "Main_Page" , "main" )', $native);
            $t->contains('<h2 id="heading">Heading</h2>', $html);
            $t->contains('<a href="Main_Page" title="main" class="wikilink">main</a>', $html);
        },
];
