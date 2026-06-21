<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\Fb2Reader;
use PortLibs\Pandoc\PandocConverter;

$fb2 = static function (string $inner): string {
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0" xmlns:l="http://www.w3.org/1999/xlink">'
        . $inner
        . '</FictionBook>';
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'linebreak') {
        return "\n";
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

return [
    'matches pinned fb2 emphasis fixture semantics' => static function (TestRunner $t) use ($fb2, $nodesOfType, $plainText): void {
        $document = (new Fb2Reader())->read($fb2('<body><section>'
            . '<p>Plain, <strong>strong</strong>, <emphasis>emphasis</emphasis>, <strong><emphasis>strong emphasis</emphasis></strong>, <emphasis><strong>emphasized strong</strong></emphasis>.</p>'
            . '<p>Strikethrough: <strikethrough>deleted</strikethrough></p>'
            . '<p><sub>Subscript</sub> and <sup>superscript</sup></p>'
            . '<p>Some <code>code</code></p>'
            . '</section></body>'));
        $section = $document->children[0];
        $paragraphs = $nodesOfType($document, 'paragraph');

        $t->same('fb2', $document->attr('sourceFormat'));
        $t->same(['section'], $section->attr('classes'));
        $t->same('strong', $paragraphs[0]->children[1]->type);
        $t->same('emph', $paragraphs[0]->children[3]->type);
        $t->same('strong', $paragraphs[0]->children[5]->type);
        $t->same('emph', $paragraphs[0]->children[5]->children[0]->type);
        $t->same('emph', $paragraphs[0]->children[7]->type);
        $t->same('strong', $paragraphs[0]->children[7]->children[0]->type);
        $t->same('strikeout', $paragraphs[1]->children[1]->type);
        $t->same('subscript', $paragraphs[2]->children[0]->type);
        $t->same('superscript', $paragraphs[2]->children[2]->type);
        $t->same('code', $paragraphs[3]->children[1]->type);
        $t->same('Plain, strong, emphasis, strong emphasis, emphasized strong.', $plainText($paragraphs[0]));
    },

    'matches pinned fb2 titles and epigraph fixture semantics' => static function (TestRunner $t) use ($fb2, $nodesOfType, $plainText): void {
        $titles = (new Fb2Reader())->read($fb2('<body>'
            . '<title><p>Body title</p></title>'
            . '<section><title><p>Section title</p></title>'
            . '<section><title><p>Subsection title</p><p>with multiple paragraphs</p></title></section>'
            . '<section><title><p>Another subsection title</p></title></section>'
            . '</section></body>'));
        $headings = $nodesOfType($titles, 'heading');

        $t->same([1, 2, 3, 3], array_map(static fn (AstNode $heading): int => (int) $heading->attr('level'), $headings));
        $t->same("Subsection title\nwith multiple paragraphs", $plainText($headings[2]));
        $t->same('linebreak', $headings[2]->children[1]->type);

        $epigraphs = (new Fb2Reader())->read($fb2('<body>'
            . '<epigraph><p>Body epigraph</p></epigraph>'
            . '<section><epigraph><p>Section epigraph</p></epigraph>'
            . '<section><epigraph><p>Subsection epigraph</p></epigraph></section>'
            . '</section></body>'));
        $divs = array_values(array_filter(
            $nodesOfType($epigraphs, 'div'),
            static fn (AstNode $node): bool => in_array('epigraph', $node->attr('classes', []), true)
        ));

        $t->same(3, count($divs));
        $t->same('Body epigraph', $plainText($divs[0]));
        $t->same('Section epigraph', $plainText($divs[1]));
        $t->same('Subsection epigraph', $plainText($divs[2]));
    },

    'matches pinned fb2 poem fixture semantics' => static function (TestRunner $t) use ($fb2, $nodesOfType, $plainText): void {
        $document = (new Fb2Reader())->read($fb2('<body><section><poem>'
            . '<title><p>Poem title</p></title>'
            . '<epigraph><p>Poem epigraph</p></epigraph>'
            . '<stanza><subtitle>Subtitle</subtitle><title><p>First stanza title</p></title><v>Verse</v><v><emphasis>More</emphasis> verse</v></stanza>'
            . '<stanza><v>One more stanza</v></stanza>'
            . '<text-author>Author</text-author><date>April 2018</date>'
            . '</poem></section></body>'));
        $headings = $nodesOfType($document, 'heading');
        $lineBlocks = $nodesOfType($document, 'line_block');
        $paragraphs = $nodesOfType($document, 'paragraph');

        $t->same('Poem title', $plainText($headings[0]));
        $t->same(['unnumbered'], $headings[1]->attr('classes'));
        $t->same('Subtitle', $plainText($headings[1]));
        $t->same('First stanza title', $plainText($headings[2]));
        $t->same(2, count($lineBlocks));
        $t->same(2, count($lineBlocks[0]->children));
        $t->same('Verse', $plainText($lineBlocks[0]->children[0]));
        $t->same('More verse', $plainText($lineBlocks[0]->children[1]));
        $t->same('emph', $lineBlocks[0]->children[1]->children[0]->type);
        $t->same('Author', $plainText($paragraphs[count($paragraphs) - 2]));
        $t->same('April 2018', $plainText($paragraphs[count($paragraphs) - 1]));
    },

    'matches pinned fb2 metadata and notes fixture semantics' => static function (TestRunner $t) use ($fb2, $nodesOfType, $plainText): void {
        $metaDoc = (new Fb2Reader())->read($fb2('<description><title-info>'
            . '<author><first-name>First</first-name><middle-name>Middle</middle-name><last-name>Last</last-name></author>'
            . '<author><first-name>Another</first-name><last-name>Author</last-name></author>'
            . '<book-title>Book title</book-title>'
            . '<annotation><p>Book annotation</p><p>Second paragraph of book annotation</p></annotation>'
            . '<keywords>foo, bar, baz</keywords><date>2018</date>'
            . '</title-info></description><body><title><p>Body title</p></title></body>'));
        $meta = $metaDoc->attr('meta');

        $t->same('Book title', $meta['title']);
        $t->same(['First Middle Last', 'Another Author'], $meta['author']);
        $t->same('2018', $meta['date']);
        $t->same('MetaList', $meta['keywords']['type']);
        $t->same('foo', $meta['keywords']['value'][0]['value']);
        $t->same('MetaBlocks', $meta['abstract']['type']);
        $t->same('Book annotation', $plainText($meta['abstract']['value'][0]));
        $t->same('Second paragraph of book annotation', $plainText($meta['abstract']['value'][1]));

        $notesDoc = (new Fb2Reader())->read($fb2('<body><section>'
            . '<p>Note <a l:href="#n1" type="note">1</a>.</p>'
            . '<p>Second note <a l:href="#n2" type="note">2</a>.</p>'
            . '</section></body><body name="notes">'
            . '<section id="n1"><title><p>1</p></title><p>Note contents</p></section>'
            . '<section id="n2"><title><p>2</p></title><p>Second note contents.</p></section>'
            . '</body>'));
        $notes = $nodesOfType($notesDoc, 'note');

        $t->same(2, count($notes));
        $t->same('Note contents', $plainText($notes[0]));
        $t->same('Second note contents.', $plainText($notes[1]));
    },

    'reads fb2 through converter and renders shared ast outputs' => static function (TestRunner $t) use ($fb2): void {
        $document = PandocConverter::read($fb2('<body><title><p>Body title</p></title></body>'), 'fb2');
        $native = PandocConverter::write($document, 'native', ['standalone' => true]);
        $html = PandocConverter::write($document, 'html');
        $blocks = PandocConverter::write($document, 'wordpress');

        $t->same('fb2', $document->attr('sourceFormat'));
        $t->contains('Header 1', $native);
        $t->contains('Str "Body" , Space , Str "title"', $native);
        $t->contains('<h1>Body title</h1>', $html);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
    },
];
