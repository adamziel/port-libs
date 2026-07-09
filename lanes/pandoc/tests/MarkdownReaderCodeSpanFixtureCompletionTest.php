<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

return [
    'maps upstream markdown reader more code span fixture' => static function (TestRunner $t): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-reader-more-code-spans.md');
        $document = (new MarkdownReader())->read($fixture);
        $native = (new NativeWriter())->write($document);

        $heading = $document->children[0] ?? new AstNode('missing');
        $escapedBackslash = $document->children[1]->children[0] ?? new AstNode('missing');
        $multiline = $document->children[2]->children[0] ?? new AstNode('missing');
        $longDelimiter = $document->children[3]->children[0] ?? new AstNode('missing');
        $unmatchedOpen = $document->children[4]->children[0] ?? new AstNode('missing');
        $unmatchedClose = $document->children[5]->children[0] ?? new AstNode('missing');

        $t->same(6, count($document->children));
        $t->same('heading', $heading->type);
        $t->same('code-spans', $heading->attr('id'));
        $t->same('code', $escapedBackslash->type);
        $t->same('hi\\', $escapedBackslash->attr('text'));
        $t->same('code', $multiline->type);
        $t->same('hi there', $multiline->attr('text'));
        $t->same('code', $longDelimiter->type);
        $t->same('hi````there', $longDelimiter->attr('text'));
        $t->same('text', $unmatchedOpen->type);
        $t->same('`hi', $unmatchedOpen->attr('text'));
        $t->same('text', $unmatchedClose->type);
        $t->same('there`', $unmatchedClose->attr('text'));
        $t->contains('Code ( "" , [  ] , [  ] ) "hi\\\\', $native);
        $t->contains('Code ( "" , [  ] , [  ] ) "hi there"', $native);
        $t->contains('Code ( "" , [  ] , [  ] ) "hi````there"', $native);
        $t->contains('Str "`hi"', $native);
        $t->contains('Str "there`"', $native);
    },
    'keeps block-like html tags literal inside markdown code spans' => static function (TestRunner $t): void {
        $tick = chr(96);
        $source = 'In HTML this is represented with '
            . $tick . '<dl>' . $tick
            . ', ' . $tick . '<dt>' . $tick
            . ', and ' . $tick . '<dd>' . $tick
            . ' tags.';

        $document = (new MarkdownReader(['format' => 'gfm']))->read($source);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $codes = array_values(array_filter(
            $paragraph->children,
            static fn (AstNode $node): bool => $node->type === 'code'
        ));
        $html = (new HtmlWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('paragraph', $paragraph->type);
        $t->same(['<dl>', '<dt>', '<dd>'], array_map(static fn (AstNode $node): string => $node->attr('text'), $codes));
        $t->same(
            '<p>In HTML this is represented with <code>&lt;dl&gt;</code>, <code>&lt;dt&gt;</code>, and <code>&lt;dd&gt;</code> tags.</p>',
            $html
        );
    },
];
