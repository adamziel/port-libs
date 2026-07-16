<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PandocConverter;

$fixturePath = dirname(__DIR__) . '/fixtures/upstream-markdown-reader-more-grid-table-spans.md';
$nativePath = dirname(__DIR__) . '/fixtures/upstream-markdown-reader-more-grid-table-spans.native';

$nodesOfType = static function (AstNode $node, string $type) use (&$nodesOfType): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        $matches = array_merge($matches, $nodesOfType($child, $type));
    }

    return $matches;
};

return [
    'maps upstream markdown reader more grid table span fixture' =>
        static function (TestRunner $t) use ($fixturePath, $nativePath, $nodesOfType): void {
            $source = file_get_contents($fixturePath);
            $expectedNative = file_get_contents($nativePath);
            if (!is_string($source) || !is_string($expectedNative)) {
                throw new RuntimeException('Unable to read checked-in grid table span fixture');
            }

            $document = (new MarkdownReader(['format' => 'markdown+grid_tables']))->read($source);
            $native = PandocConverter::write($document, 'native');
            $tables = $nodesOfType($document, 'table');

            $t->same(2, count($tables));
            $t->contains('Table with cells spanning multiple rows or columns:', $source);
            $t->contains('Table with complex header:', $source);
            $t->contains('(RowSpan 3)', $native);
            $t->contains('(ColSpan 2)', $native);
            $t->contains('(RowSpan 2)', $native);
            $t->contains('(ColSpan 3)', $native);
            $t->contains('[ Str "Temperature" , SoftBreak , Str "1961-1990" ]', $native);
            $t->contains('Str "Antarctica"', $native);
            $t->contains('(RowSpan 3)', $expectedNative);
            $t->contains('(ColSpan 3)', $expectedNative);
        },
];
