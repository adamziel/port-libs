<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$paragraphText = static function (AstNode $document, int $index): string {
    $paragraph = $document->children[$index] ?? new AstNode('missing');
    $text = '';
    foreach ($paragraph->children as $child) {
        if ($child->type === 'text') {
            $text .= (string) $child->attr('text', '');
            continue;
        }
        if ($child->type === 'space') {
            $text .= ' ';
        }
    }

    return $text;
};

return [
    'maps pandoc markdown old dashes smart profile fixture' =>
        static function (TestRunner $t) use ($paragraphText): void {
            $fixture = (string) file_get_contents(
                dirname(__DIR__) . '/fixtures/upstream-markdown-z-old-dashes-profile.md'
            );
            $document = (new MarkdownReader(['format' => 'markdown+old_dashes+smart']))->read($fixture);

            $t->same(6, count($document->children));
            $t->same("a\u{2014}b", $paragraphText($document, 0));
            $t->same("a\u{2014}-b", $paragraphText($document, 1));
            $t->same("a\u{2014}\u{2014}b", $paragraphText($document, 2));
            $t->same("a\u{2014}\u{2014}-b", $paragraphText($document, 3));
            $t->same("a \u{2014} b", $paragraphText($document, 4));
            $t->same("a \u{2014}- b", $paragraphText($document, 5));
        },

    'keeps modern smart dash profile unchanged when old dashes disabled' =>
        static function (TestRunner $t) use ($paragraphText): void {
            $document = (new MarkdownReader(['format' => 'markdown+smart']))->read("a--b\n\na---b\n");

            $t->same("a\u{2013}b", $paragraphText($document, 0));
            $t->same("a\u{2014}b", $paragraphText($document, 1));
        },
];
