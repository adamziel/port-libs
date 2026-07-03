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

$collectLinks = static function (AstNode $node) use (&$collectLinks): array {
    $links = [];
    if ($node->type === 'link') {
        $links[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($links, ...$collectLinks($child));
    }

    return $links;
};

return [
    'maps selected upstream markdown link label boundary fixture' =>
        static function (TestRunner $t) use ($collectLinks, $plainText): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-link-label-boundaries.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $expectedLabels = [
                '<https://example.org>',
                '[a](url2)',
                'https://example.org(',
            ];

            $t->same(3, count($document->children));
            $t->same(3, count($collectLinks($document)), 'only the outer links should be parsed');
            foreach ($expectedLabels as $index => $expectedLabel) {
                $paragraph = $document->children[$index] ?? new AstNode('missing');
                $link = $paragraph->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, 'paragraph ' . $index);
                $t->same('link', $link->type, 'link ' . $index);
                $t->same('url', $link->attr('url'), 'url ' . $index);
                $t->same($expectedLabel, $plainText($link), 'literal label ' . $index);
                $t->same(['text'], array_map(
                    static fn (AstNode $child): string => $child->type,
                    $link->children
                ), 'label child types ' . $index);
            }
        },

    'records upstream markdown link label boundary fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-link-label-boundaries.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(3, count($cases));
            $t->same('[<https://example.org>](url)', $cases[0]);
            $t->same('[[a](url2)](url)', $cases[1]);
            $t->same('[https://example.org(](url)', $cases[2]);
        },
];
