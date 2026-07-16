<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$collectTypes = static function (AstNode $node) use (&$collectTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$collectTypes($child));
    }

    return $types;
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

$cellText = static function (AstNode $table, string $section, int $row, int $column) use ($plainText): string {
    $sectionNode = $section === 'head' ? ($table->children[0] ?? new AstNode('missing')) : ($table->children[1] ?? new AstNode('missing'));
    $rowNode = $sectionNode->children[$row] ?? new AstNode('missing');
    $cell = $rowNode->children[$column] ?? new AstNode('missing');

    return $plainText($cell);
};

return [
    'maps selected upstream markdown pipe-table escaped-cell fixture' =>
        static function (TestRunner $t) use ($cellText): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-pipe-table-escaped-cell.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $table = $document->children[0] ?? new AstNode('missing');
            $head = $table->children[0] ?? new AstNode('missing');
            $body = $table->children[1] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('table', $table->type);
            $t->same(['left', 'right'], $table->attr('alignments'));
            $t->same(1, count($head->children));
            $t->same(2, count($body->children));
            $t->same('Metric', $cellText($table, 'head', 0, 0));
            $t->same('Value', $cellText($table, 'head', 0, 1));
            $t->same('Queue', $cellText($table, 'body', 0, 0));
            $t->same('12', $cellText($table, 'body', 0, 1));
            $t->same('Slug', $cellText($table, 'body', 1, 0));
            $t->same('a|b', $cellText($table, 'body', 1, 1));
        },

    'keeps selected upstream markdown pipe-table fixture behind extension gate' =>
        static function (TestRunner $t) use ($collectTypes): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-pipe-table-escaped-cell.md');
            $strict = (new MarkdownReader(['format' => 'markdown_strict']))->read($source);
            $strictWithPipeTables = (new MarkdownReader(['format' => 'markdown_strict+pipe_tables']))->read($source);

            $t->same(false, in_array('table', $collectTypes($strict), true));
            $t->same('table', ($strictWithPipeTables->children[0] ?? new AstNode('missing'))->type);
        },

    'records selected upstream markdown pipe-table fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-pipe-table-escaped-cell.md');
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($source)) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(4, count($rows));
            $t->same('|:-------|------:|', $rows[1]);
            $t->contains('a\\|b', $rows[3]);
        },
];
