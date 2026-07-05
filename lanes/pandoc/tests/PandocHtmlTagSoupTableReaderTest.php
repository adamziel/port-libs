<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PandocHtmlTagSoupTableReader;

return [
    'parses structured table fixtures from tagsoup token slices' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $harness = new HtmlNativeAstComparisonHarness();

        foreach ([
            'upstream-html-implicit-tbody-table',
            'upstream-html-colgroup-width-table',
            'upstream-html-table-row-col-span',
            'upstream-html-table-foot',
            'upstream-html-multi-tbody-row-header-table',
            'upstream-native-html-row-header-table',
            'upstream-html-doc-noteref-table-placement',
        ] as $basename) {
            $tokens = $reader->tokenize(tagSoupTableFixture($basename, 'html'));
            $result = $reader->parseFirstTable($tokens);
            $expected = tagSoupTableFirstNodeOfType(
                (new NativeReader())->read(tagSoupTableFixture($basename, 'native')),
                'table'
            );

            $t->true(is_array($result), "{$basename} should expose a table parse result");
            $t->same(true, $result['structured'] ?? null, "{$basename} should parse as a structured table");
            $t->true(($result['table'] ?? null) instanceof AstNode, "{$basename} should return a table node");
            $t->true(($result['nextIndex'] ?? 0) > ($result['startIndex'] ?? 0), "{$basename} should report consumed token range");
            $t->same(
                $harness->normalizedDocument(new AstNode('document', [], [$expected])),
                $harness->normalizedDocument(new AstNode('document', [], [$result['table']])),
                "{$basename} table AST should match native fixture shape"
            );
        }
    },

    'drops empty table fixtures without emitting table blocks' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $tokens = $reader->tokenize(tagSoupTableFixture('upstream-html-empty-tables', 'html'));
        $results = $reader->parseTables($tokens);

        $t->same(2, count($results), 'empty table fixture should contain two table parse attempts');
        $t->same(['empty-table', 'empty-table'], array_map(static fn (array $result): string => $result['reason'], $results));
        $t->same([], $reader->parseTableBlocks($tokens), 'empty tables should not emit AST blocks');
    },

    'promotes first implicit all-th row to table head like upstream' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $result = $reader->parseFirstTable($reader->tokenize(
            '<table><tr><th>X</th><th>Y</th></tr><tr><td>1</td><td>2</td></tr></table>'
        ));

        $t->true(is_array($result));
        $table = $result['table'] ?? null;
        $t->true($table instanceof AstNode);
        $t->same('table_head', $table->children[0]->type);
        $t->same(1, count($table->children[0]->children));
        $t->same('X', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same('table_body', $table->children[1]->type);
        $t->same(1, count($table->children[1]->children));
        $t->same('1', $table->children[1]->children[0]->children[0]->attr('text'));
    },

    'stores leading tbody all-th rows as body head rows like upstream' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $result = $reader->parseFirstTable($reader->tokenize(
            '<table><tbody><tr><th>X</th><th>Y</th></tr><tr><td>1</td><td>2</td></tr></tbody></table>'
        ));

        $t->true(is_array($result));
        $table = $result['table'] ?? null;
        $t->true($table instanceof AstNode);
        $body = $table->children[1];
        $t->same('table_body', $body->type);
        $t->same(1, count($body->attr('headRows')));
        $t->same('X', $body->attr('headRows')[0]->children[0]->attr('text'));
        $t->same(1, count($body->children));
        $t->same('1', $body->children[0]->children[0]->attr('text'));
    },

    'preserves explicit paragraph blocks inside table cells like upstream' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $result = $reader->parseFirstTable($reader->tokenize(
            '<table><tr><td>1</td><td><p>2</p></td><td>3</td></tr></table>'
        ));

        $t->true(is_array($result));
        $table = $result['table'] ?? null;
        $t->true($table instanceof AstNode);
        $cell = $table->children[1]->children[0]->children[1];
        $t->same('table_cell', $cell->type);
        $t->same('paragraph', $cell->children[0]->type);
        $t->same('2', $cell->children[0]->children[0]->attr('text'));
    },

    'preserves table section row and cell attributes like upstream' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $result = $reader->parseFirstTable($reader->tokenize(<<<'HTML'
<table id="attrib-test-table">
  <thead class="table-head"><tr class="table-head-row"><th abbr="x" colspan="3">Cat X</th></tr></thead>
  <tbody data-part="body" class="main"><tr data-part="row"><td data-part="cell">1</td><td valign="bottom">2</td><td style="color: #151950">3</td></tr></tbody>
  <tfoot class="summary"><tr bgcolor="#ccc"><td data-square="true">4</td><td>5</td><td>6</td></tr></tfoot>
</table>
HTML));

        $t->true(is_array($result));
        $table = $result['table'] ?? null;
        $t->true($table instanceof AstNode);
        $t->same(['table-head'], $table->children[0]->attr('classes'));
        $t->same(['table-head-row'], $table->children[0]->children[0]->attr('classes'));
        $t->same(['part' => 'row'], $table->children[1]->children[0]->attr('attributes'));
        $t->same(['valign' => 'bottom'], $table->children[1]->children[0]->children[1]->attr('attributes'));
        $t->same(null, $table->children[1]->children[0]->children[1]->attr('valign'));
        $t->same(['summary'], $table->children[2]->attr('classes'));
        $t->same(['bgcolor' => '#ccc'], $table->children[2]->children[0]->attr('attributes'));
    },

    'degrades invalid table children and foster-parent text fixtures to paragraphs' => static function (TestRunner $t): void {
        $reader = new PandocHtmlTagSoupTableReader();
        $harness = new HtmlNativeAstComparisonHarness();

        foreach ([
            'upstream-html-invalid-table-children' => 4,
            'upstream-html-foster-parent-table-text' => 2,
        ] as $basename => $expectedBlockCount) {
            $tokens = $reader->tokenize(tagSoupTableFixture($basename, 'html'));
            $result = $reader->parseFirstTable($tokens);
            $nativeDocument = (new NativeReader())->read(tagSoupTableFixture($basename, 'native'));
            $expectedBlocks = array_slice($nativeDocument->children, 0, $expectedBlockCount);

            $t->true(is_array($result), "{$basename} should expose a table parse result");
            $t->same(false, $result['structured'] ?? null, "{$basename} should degrade rather than emit a table");
            $t->same('invalid-table-children', $result['reason'] ?? null, "{$basename} should record invalid-table reason");
            $t->same($expectedBlockCount, count($result['blocks'] ?? []), "{$basename} fallback block count");
            $t->same(
                $harness->normalizedDocument(new AstNode('document', [], $expectedBlocks)),
                $harness->normalizedDocument(new AstNode('document', [], $result['blocks'])),
                "{$basename} fallback paragraphs should match native fixture prefix"
            );
        }
    },
];

function tagSoupTableFixtureRoot(): string
{
    return dirname(__DIR__) . '/fixtures';
}

function tagSoupTableFixture(string $basename, string $extension): string
{
    $contents = file_get_contents(tagSoupTableFixtureRoot() . '/' . $basename . '.' . $extension);
    if (!is_string($contents)) {
        throw new RuntimeException("Unable to read {$basename}.{$extension}");
    }

    return $contents;
}

function tagSoupTableFirstNodeOfType(AstNode $node, string $type): AstNode
{
    $found = tagSoupTableFindNodeOfType($node, $type);
    if ($found instanceof AstNode) {
        return $found;
    }

    throw new RuntimeException("Unable to find {$type} node");
}

function tagSoupTableFindNodeOfType(AstNode $node, string $type): ?AstNode
{
    if ($node->type === $type) {
        return $node;
    }
    foreach ($node->children as $child) {
        $found = tagSoupTableFindNodeOfType($child, $type);
        if ($found instanceof AstNode) {
            return $found;
        }
    }

    return null;
}
