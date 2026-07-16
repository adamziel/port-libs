<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps checked-in upstream markdown autolink attribute fixture' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-autolink-attributes.md');
        $document = (new MarkdownReader())->read($source);
        $attributed = $document->children[0]->children[0] ?? new AstNode('missing');
        $spaced = $document->children[1] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $attributed->type);
        $t->same('http://foo.bar', $attributed->attr('url'));
        $t->same('i', $attributed->attr('id'));
        $t->same(['j', 'z'], $attributed->attr('classes'));
        $t->same(['k' => 'v'], $attributed->attr('attributes'));
        $t->same('http://foo.bar', $attributed->children[0]->attr('text'));

        $t->same('paragraph', $spaced->type);
        $t->same(2, count($spaced->children));
        $t->same('link', $spaced->children[0]->type);
        $t->same(['uri'], $spaced->children[0]->attr('classes'));
        $t->same('http://foo.bar', $spaced->children[0]->attr('url'));
        $t->same('text', $spaced->children[1]->type);
        $t->same(' {#i .j .z k=v}', $spaced->children[1]->attr('text'));
        $t->same('http://foo.bar {#i .j .z k=v}', $spaced->attr('text'));

        $t->contains('<p><a href="http://foo.bar" id="i" class="j z">http://foo.bar</a></p>', $blocks);
        $t->contains('<p><a href="http://foo.bar">http://foo.bar</a> {#i .j .z k=v}</p>', $blocks);
    },
];
