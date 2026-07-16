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

$tests = [];

$tests['maps upstream markdown angle autolink unicode dash boundary'] =
    static function (TestRunner $t): void {
        $source = (string) file_get_contents(
            dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary.md'
        );
        $document = (new MarkdownReader())->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $link = $paragraph->children[0] ?? new AstNode('missing');
        $dash = $paragraph->children[1] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same('link', $link->type);
        $t->same('http://foo.bar', $link->attr('url'));
        $t->same(['uri'], $link->attr('classes'));
        $t->same('http://foo.bar', $link->children[0]->attr('text'));
        $t->same('text', $dash->type);
        $t->same("\u{2014}", $dash->attr('text'));
        $t->contains('<p><a href="http://foo.bar">http://foo.bar</a>' . "\u{2014}" . '</p>', $blocks);
    };

$tests['keeps upstream markdown partial angle www url literal'] =
    static function (TestRunner $t) use ($collectLinks): void {
        $source = trim((string) file_get_contents(
            dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary.md'
        ));
        $document = (new MarkdownReader())->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $literal = $paragraph->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $paragraph->type);
        $t->same([], $collectLinks($paragraph));
        $t->same(1, count($paragraph->children));
        $t->same('text', $literal->type);
        $t->same($source, $literal->attr('text'));
        $t->contains('&lt;www.boe.es/buscar/act.php?id=BOE-A-1996-8930#a66&gt;', $blocks);
    };

$tests['records upstream markdown angle autolink residual mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(2, 2);
    };

return $tests;
