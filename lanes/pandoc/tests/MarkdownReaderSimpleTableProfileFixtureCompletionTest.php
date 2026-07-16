<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-simple-table-profile.md'
);

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
    'maps pandoc markdown simple-table profile fixture' =>
        static function (TestRunner $t) use ($fixture, $cellText): void {
            $document = (new MarkdownReader(['format' => 'markdown+simple_tables']))->read($fixture());
            $table = $document->children[0] ?? new AstNode('missing');
            $native = (new NativeWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('table', $table->type);
            $t->same(['left', 'default'], $table->attr('alignments'));
            $t->same('Item', $cellText($table, 'head', 0, 0));
            $t->same('Count', $cellText($table, 'head', 0, 1));
            $t->same('Posts', $cellText($table, 'body', 0, 0));
            $t->same('42', $cellText($table, 'body', 0, 1));
            $t->contains('Table', $native);
            $t->contains('Str "Posts"', $native);
        },

    'keeps pandoc markdown simple-table profile fixture behind extension gate' =>
        static function (TestRunner $t) use ($fixture, $collectTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown-simple_tables']))->read($fixture());

            $t->same(false, in_array('table', $collectTypes($document), true));
        },

    'records pandoc markdown simple-table profile fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($fixture())) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(3, count($rows));
            $t->same('Item    Count', $rows[0]);
            $t->same('------  -----', $rows[1]);
            $t->same('Posts   42', $rows[2]);
        },
];
