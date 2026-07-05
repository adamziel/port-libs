<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownFormatProfile;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-table-attributes-disabled-profile.md'
);

return [
    'maps upstream markdown table attributes disabled profile fixture' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown-table_attributes']))->read($fixture());
            $table = $document->children[0] ?? new AstNode('missing');
            $captionInlines = $table->attr('captionInlines', []);
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('table', $table->type);
            $t->same('', $table->attr('id', ''));
            $t->same([], $table->attr('classes', []));
            $t->same([], $table->attr('attributes', []));
            $t->same('Reviewed **table** {#review-table .audited data-source="upstream"}', $table->attr('caption'));
            $t->same(['text', 'strong', 'text', 'quoted', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                is_array($captionInlines) ? $captionInlines : []
            ));
            $t->contains('Table ( "" , [  ] , [  ] )', $native);
            $t->contains('Str "{#review-table"', $native);
            $t->contains('Quoted DoubleQuote [ Str "upstream" ]', $native);
        },

    'keeps upstream markdown table attributes enabled by default' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $table = $document->children[0] ?? new AstNode('missing');

            $t->same('table', $table->type);
            $t->same('review-table', $table->attr('id'));
            $t->same(['audited'], $table->attr('classes'));
            $t->same(['data-source' => 'upstream'], $table->attr('attributes'));
            $t->same('Reviewed **table**', $table->attr('caption'));
        },

    'records upstream markdown table attributes profile mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(false, MarkdownFormatProfile::tableAttributesEnabled(['format' => 'markdown-table_attributes'], true));
            $t->same(true, MarkdownFormatProfile::tableAttributesEnabled(['format' => 'markdown+table_attributes'], false));
            $t->same(false, MarkdownFormatProfile::tableAttributesEnabled(['format' => 'gfm'], true));
            $t->same(1, count([
                'upstream-markdown-table-attributes-disabled-profile.md',
            ]));
        },
];
