<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

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

return [
    'maps selected upstream markdown backslash escaped link fixture' =>
        static function (TestRunner $t) use ($plainText): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-backslash-escaped-links.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $expectedLinks = [
                ['text' => 'hi', 'url' => '/there)', 'title' => null],
                ['text' => 'hi', 'url' => '/there', 'title' => 'a"a'],
                ['text' => 'ref-title', 'url' => '/there', 'title' => 'a)a'],
                ['text' => 'ref-url', 'url' => '/there.0', 'title' => null],
            ];

            $t->same(4, count($document->children));
            foreach ($expectedLinks as $index => $expected) {
                $paragraph = $document->children[$index] ?? new AstNode('missing');
                $link = $paragraph->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, 'paragraph ' . $index);
                $t->same('link', $link->type, 'link ' . $index);
                $t->same($expected['text'], $plainText($link), 'text ' . $index);
                $t->same($expected['url'], $link->attr('url'), 'url ' . $index);
                $t->same($expected['title'], $link->attr('title'), 'title ' . $index);
            }
        },
];
