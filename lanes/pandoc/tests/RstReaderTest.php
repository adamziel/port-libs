<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\RstReader;

$read = static fn (string $source): AstNode => (new RstReader())->read($source);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    return implode('', array_map($plainText, $node->children));
};

$types = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps core restructuredtext blocks and inline semantics' =>
        static function (TestRunner $t) use ($read, $plainText, $types): void {
            $document = $read(<<<'RST'
Title
=====

Intro with *emphasis*, **strong**, ``literal``, `Pandoc <https://pandoc.org>`_ and https://example.com.

- first item
- second item

1. one
2. two

Term
  Definition body with **strong** text.

:Author: Jane Doe

Paragraph before literal::

  print("rst")
  print("reader")

.. code:: php

   echo "code";

.. image:: images/cover.png
   :alt: Cover image
   :width: 200px

  Quoted paragraph.
RST);

            $blocks = $document->children;

            $t->same('rst', $document->attr('sourceFormat'));
            $t->same([
                'heading',
                'paragraph',
                'bullet_list',
                'ordered_list',
                'definition_list',
                'definition_list',
                'paragraph',
                'code_block',
                'code_block',
                'paragraph',
                'blockquote',
            ], $types($document));
            $t->same('Title', $plainText($blocks[0]));
            $t->same(['text', 'emph', 'text', 'strong', 'text', 'code', 'text', 'link', 'text', 'link', 'text'], $types($blocks[1]));
            $t->same('Intro with emphasis, strong, literal, Pandoc and https://example.com.', $plainText($blocks[1]));
            $t->same('https://pandoc.org', $blocks[1]->children[7]->attr('url'));
            $t->same('https://example.com', $blocks[1]->children[9]->attr('url'));

            $t->same('first item', $plainText($blocks[2]->children[0]));
            $t->same('second item', $plainText($blocks[2]->children[1]));
            $t->same('ordered_list', $blocks[3]->type);
            $t->same('one', $plainText($blocks[3]->children[0]));
            $t->same('two', $plainText($blocks[3]->children[1]));

            $t->same('Term', $blocks[4]->children[0]->attr('term'));
            $t->same('Definition body with strong text.', $plainText($blocks[4]->children[0]->children[1]));
            $t->same('Author', $blocks[5]->children[0]->attr('term'));
            $t->same('Jane Doe', $plainText($blocks[5]->children[0]->children[1]));

            $t->same('Paragraph before literal:', $plainText($blocks[6]));
            $t->same("print(\"rst\")\nprint(\"reader\")", $blocks[7]->attr('text'));
            $t->same(["php"], $blocks[8]->attr('classes'));
            $t->same('echo "code";', $blocks[8]->attr('text'));
            $t->same('image', $blocks[9]->children[0]->type);
            $t->same('images/cover.png', $blocks[9]->children[0]->attr('url'));
            $t->same('Cover image', $blocks[9]->children[0]->attr('alt'));
            $t->same(['width' => '200px'], $blocks[9]->children[0]->attr('attributes'));
            $t->same('blockquote', $blocks[10]->type);
            $t->same('Quoted paragraph.', $plainText($blocks[10]));
        },

    'reads rst through converter and renders shared outputs' =>
        static function (TestRunner $t): void {
            $source = <<<'RST'
Heading
-------

See `Example <https://example.org>`_.
RST;

            $document = PandocConverter::read($source, 'rst');
            $native = PandocConverter::write($document, 'native');
            $html = PandocConverter::write($document, 'html');

            $t->same('rst', $document->attr('sourceFormat'));
            $t->contains('Header 1', $native);
            $t->contains('Link ( "" , [  ] , [  ] ) [ Str "Example" ] ( "https://example.org" , "" )', $native);
            $t->contains('<h1>Heading</h1>', $html);
            $t->contains('<a href="https://example.org">Example</a>', $html);
        },

    'maps rst csv table directives through the native delimited text reader' =>
        static function (TestRunner $t) use ($read, $plainText): void {
            $document = $read(<<<'RST'
Inventory
=========

.. csv-table:: Stock counts
   :header: "Name", "Qty", "Note"

   "Apple", 2, "Fresh, green"
   "Orange", 3, "Citrus"
RST);

            $table = $document->children[1] ?? new AstNode('missing');
            $head = $table->children[0] ?? new AstNode('missing');
            $body = $table->children[1] ?? new AstNode('missing');
            $packet = $table->attr('delimitedText');
            $rstPacket = $table->attr('rstCsvTable');

            $t->same('table', $table->type);
            $t->same('rst-csv-table', $table->attr('sourceFormat'));
            $t->same('Stock counts', $table->attr('caption'));
            $t->same('csv-table', $table->attr('rstDirective'));
            $t->same(['Name', 'Qty', 'Note'], $table->attr('columnNames'));
            $t->same(true, $rstPacket['headerOption'] ?? null);
            $t->same(2, $rstPacket['bodyLineCount'] ?? null);
            $t->same('csv', $packet['format'] ?? null);
            $t->same('first-row', $packet['headerOption'] ?? null);
            $t->same(3, $packet['columnCount'] ?? null);
            $t->same('Name', $plainText($head->children[0]->children[0]));
            $t->same('Note', $plainText($head->children[0]->children[2]));
            $t->same('Apple', $plainText($body->children[0]->children[0]));
            $t->same('Fresh, green', $plainText($body->children[0]->children[2]));
            $t->same('Orange', $plainText($body->children[1]->children[0]));
            $t->same('Citrus', $plainText($body->children[1]->children[2]));
        },
];
