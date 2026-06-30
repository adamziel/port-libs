<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$firstTable = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'table') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

$tests = [];

$tests['keeps leading inline link table caption out of short caption parsing'] =
    static function (TestRunner $t) use ($firstTable, $inlineTypes): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '| Term | Count |',
            '|:-----|------:|',
            '| Docs | 2 |',
            '',
            ': [Docs](/docs) caption {#tbl-link-caption .review data-source="upstream"}',
        ]));
        $table = $firstTable($document);

        $t->same('table', $table->type);
        $t->same('[Docs](/docs) caption', $table->attr('caption'));
        $t->same(null, $table->attr('shortCaption', null));
        $t->same(['link', 'text'], $inlineTypes($table->attr('captionInlines', [])));
        $t->same('tbl-link-caption', $table->attr('id'));
        $t->same(['review'], $table->attr('classes'));
        $t->same(['data-source' => 'upstream'], $table->attr('attributes'));
        $t->same('after-table', $table->attr('captionSource')['position'] ?? null);
    };

$tests['accepts upstream numbered table caption labels'] =
    static function (TestRunner $t) use ($firstTable): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            'Tbl. IV. [Queue] Numbered table caption {#tbl-numbered-caption .numbered}',
            '',
            '| Term | Count |',
            '|:-----|------:|',
            '| Docs | 2 |',
        ]));
        $table = $firstTable($document);

        $t->same('table', $table->type);
        $t->same('Numbered table caption', $table->attr('caption'));
        $t->same('Queue', $table->attr('shortCaption'));
        $t->same('tbl-numbered-caption', $table->attr('id'));
        $t->same(['numbered'], $table->attr('classes'));
        $t->same('Tbl. IV.', $table->attr('captionSource')['marker'] ?? null);
        $t->same('before-table', $table->attr('captionSource')['position'] ?? null);
    };

return $tests;
