<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\RstReader;

$read = static fn (string $source): AstNode => (new RstReader())->read($source);

$upstreamRstCsvTableFixture = static function (): array {
    $root = dirname(__DIR__) . '/fixtures/upstream-current-rst-csv-table';
    $markdownPath = $root . '/3533-rst-csv-tables.md';
    $csvPath = $root . '/command/3533-rst-csv-tables.csv';
    $markdown = (string) file_get_contents($markdownPath);

    if (preg_match_all('/% pandoc -f rst -t native\n(?P<rst>.*?)\n\^D/s', $markdown, $matches) !== 3) {
        throw new RuntimeException('Unable to parse checked-in upstream RST csv-table command fixture');
    }

    return [
        'root' => $root,
        'markdownPath' => $markdownPath,
        'csvPath' => $csvPath,
        'markdown' => $markdown,
        'rst' => $matches['rst'],
    ];
};

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
            $geometry = $table->attr('tableGeometry');

            $t->same('table', $table->type);
            $t->same('rst-csv-table', $table->attr('sourceFormat'));
            $t->same('Stock counts', $table->attr('caption'));
            $t->same('csv-table', $table->attr('rstDirective'));
            $t->same(['Name', 'Qty', 'Note'], $table->attr('columnNames'));
            $t->same('Stock counts', $geometry['caption'] ?? null);
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

    'matches upstream rst csv-table file widths and dialect options' =>
        static function (TestRunner $t) use ($upstreamRstCsvTableFixture, $plainText): void {
            $fixture = $upstreamRstCsvTableFixture();
            $reader = new RstReader(['resourceBasePath' => $fixture['root']]);

            $t->same(6305, filesize($fixture['markdownPath']));
            $t->same('57ad43778058547f8d1bcf7f1a5d2f87d97aa1f70cc88e0408201233aff78340', hash_file('sha256', $fixture['markdownPath']));
            $t->same(122, filesize($fixture['csvPath']));
            $t->same('78734675435efeb84bbc7b182bdb5b454de689b49b8acc67509f9260614b2005', hash_file('sha256', $fixture['csvPath']));

            $fileTable = $reader->read($fixture['rst'][0])->children[0] ?? new AstNode('missing');
            $fileHead = $fileTable->children[0] ?? new AstNode('missing');
            $fileBody = $fileTable->children[1] ?? new AstNode('missing');
            $filePacket = $fileTable->attr('delimitedText');
            $fileRstPacket = $fileTable->attr('rstCsvTable');

            $t->same('table', $fileTable->type);
            $t->same('rst-csv-table', $fileTable->attr('sourceFormat'));
            $t->same('Test', $fileTable->attr('caption'));
            $t->same(['Flavor', 'Price', 'Slogan'], $fileTable->attr('columnNames'));
            $t->same([0.4, 0.2, 0.4], $fileTable->attr('widths'));
            $t->same('Flavor', $plainText($fileHead->children[0]->children[0]));
            $t->same('Slogan', $plainText($fileHead->children[0]->children[2]));
            $t->same('Albatross', $plainText($fileBody->children[0]->children[0]));
            $t->same('On a stick!', $plainText($fileBody->children[0]->children[2]));
            $t->same('Crunchy Frog', $plainText($fileBody->children[1]->children[0]));
            $t->same("If we took the bones out, it wouldn't be\ncrunchy, now would it?", $fileBody->children[1]->children[2]->attr('text'));
            $t->same('softbreak', $fileBody->children[1]->children[2]->children[0]->children[1]->type);
            $t->same(true, $fileRstPacket['headerOption'] ?? null);
            $t->same(0, $fileRstPacket['headerRowsOption'] ?? null);
            $t->same('command/3533-rst-csv-tables.csv', $fileRstPacket['fileOption'] ?? null);
            $t->same(true, $fileRstPacket['file']['present'] ?? null);
            $t->same('78734675435efeb84bbc7b182bdb5b454de689b49b8acc67509f9260614b2005', $fileRstPacket['file']['sha256'] ?? null);
            $t->same(122, $fileRstPacket['file']['bytes'] ?? null);
            $t->same('csv', $filePacket['format'] ?? null);
            $t->same('explicit', $filePacket['formatInference']['source'] ?? null);
            $t->same('command/3533-rst-csv-tables.csv', $filePacket['inputPrefix']['formatContext']['sourcePath'] ?? null);
            $t->same('csv', $filePacket['inputPrefix']['formatContext']['sourcePathFormat'] ?? null);

            $headerRowsTable = $reader->read($fixture['rst'][1])->children[0] ?? new AstNode('missing');
            $headerRowsHead = $headerRowsTable->children[0] ?? new AstNode('missing');
            $headerRowsBody = $headerRowsTable->children[1] ?? new AstNode('missing');
            $headerRowsPacket = $headerRowsTable->attr('delimitedText');
            $headerRowsRstPacket = $headerRowsTable->attr('rstCsvTable');

            $t->same(['', 'a', 'b'], $headerRowsTable->attr('columnNames'));
            $t->same('', $plainText($headerRowsHead->children[0]->children[0]));
            $t->same('a', $plainText($headerRowsHead->children[0]->children[1]));
            $t->same("cat's", $plainText($headerRowsBody->children[0]->children[0]));
            $t->same("dog's", $plainText($headerRowsBody->children[1]->children[0]));
            $t->same(1, $headerRowsRstPacket['headerRowsOption'] ?? null);
            $t->same(false, $headerRowsRstPacket['headerOption'] ?? null);
            $t->same(' ', $headerRowsPacket['delimiter'] ?? null);
            $t->same("'", $headerRowsPacket['quote'] ?? null);
            $t->same('first-row', $headerRowsPacket['headerOption'] ?? null);

            $escapeTable = $reader->read($fixture['rst'][2])->children[0] ?? new AstNode('missing');
            $escapeHead = $escapeTable->children[0] ?? new AstNode('missing');
            $escapeBody = $escapeTable->children[1] ?? new AstNode('missing');
            $escapePacket = $escapeTable->attr('delimitedText');
            $escapeRstPacket = $escapeTable->attr('rstCsvTable');

            $t->same([], $escapeHead->children);
            $t->same(['column1', 'column2'], $escapeTable->attr('columnNames'));
            $t->same('1', $plainText($escapeBody->children[0]->children[0]));
            $t->same('"', $plainText($escapeBody->children[0]->children[1]));
            $t->same('\\', $escapePacket['escape'] ?? null);
            $t->same(0, $escapeRstPacket['headerRowsOption'] ?? null);
            $t->same(null, $escapeRstPacket['fileOption'] ?? null);
        },
];
