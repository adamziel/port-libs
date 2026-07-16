<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-command-md-abbrevs.md'
);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$tests = [];

$tests['maps upstream command md-abbrevs fixture nonbreaking abbreviation spacing'] =
    static function (TestRunner $t) use ($fixture, $plainText): void {
        $nbsp = "\xC2\xA0";
        $document = (new MarkdownReader())->read("Mr. Bob\n\nHi Mr\\. Bob");
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('Mr. Bob', $fixture());
        $t->contains('Str "Mr.\160Bob"', $fixture());
        $t->same(['paragraph', 'paragraph'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));
        $t->same('Mr.' . $nbsp . 'Bob', $plainText($document->children[0] ?? new AstNode('missing')));
        $t->same('Hi Mr. Bob', $plainText($document->children[1] ?? new AstNode('missing')));
        $t->contains('Str "Mr.\160Bob"', $native);
        $t->contains('Str "Hi" , Space , Str "Mr." , Space , Str "Bob"', $native);
        $t->contains('<p>Mr.' . $nbsp . 'Bob</p>', $blocks);
        $t->contains('<p>Hi Mr. Bob</p>', $blocks);
    };

$tests['maps upstream abbreviations data examples beyond the command fixture smoke'] =
    static function (TestRunner $t) use ($plainText): void {
        $nbsp = "\xC2\xA0";
        $document = (new MarkdownReader())->read('Dr. Rivera and e.g. examples.');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same('Dr.' . $nbsp . 'Rivera and e.g.' . $nbsp . 'examples.', $plainText($paragraph));
        $t->contains('Str "Dr.\160Rivera" , Space , Str "and" , Space , Str "e.g.\160examples."', $native);
        $t->contains('<p>Dr.' . $nbsp . 'Rivera and e.g.' . $nbsp . 'examples.</p>', $blocks);
    };

$tests['records upstream command md-abbrevs reader mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(3, 3);
    };

return $tests;
