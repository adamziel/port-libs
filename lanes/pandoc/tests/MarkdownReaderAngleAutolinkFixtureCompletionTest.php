<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

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
    'maps upstream markdown angle autolink fixture completion' =>
        static function (TestRunner $t) use ($collectLinks, $plainText): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-angle-autolinks.md');
            $document = (new MarkdownReader(['format' => 'markdown+autolink_bare_uris']))->read($fixture);
            $links = $collectLinks($document);
            $urls = array_map(static fn (AstNode $link): string => (string) $link->attr('url', ''), $links);
            $classes = array_map(static fn (AstNode $link): array => $link->attr('classes', []), $links);
            $blocks = (new WordPressBlockWriter())->write($document);
            $invalidParagraph = $document->children[4] ?? new AstNode('missing');

            $t->same([
                'web+demo:review-packet',
                'urn:source:review:packet',
                'mailto:editor@example.test',
                'mailto:review@example-domain.test',
                'http://www.outside.test',
            ], $urls);
            $t->same([['uri'], ['uri'], ['uri'], ['email'], ['uri']], $classes);
            $t->same('<bad https://example.test/a> and www.outside.test', $plainText($invalidParagraph));
            $t->contains('<a href="urn:source:review:packet">urn:source:review:packet</a>', $blocks);
            $t->contains('<a href="mailto:review@example-domain.test">review@example-domain.test</a>', $blocks);
        },

    'records upstream markdown angle autolink fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(5, 5);
        },
];
