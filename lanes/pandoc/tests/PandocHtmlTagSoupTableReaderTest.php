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
