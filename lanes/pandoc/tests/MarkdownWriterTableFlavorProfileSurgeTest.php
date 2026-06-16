<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$cell = static fn (string $value): AstNode => new AstNode('table_cell', ['text' => $value], [$text($value)]);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$table = static function (array $attrs = []) use ($row, $cell): AstNode {
    return new AstNode('table', array_replace([
        'alignments' => ['left', 'right'],
        'caption' => 'Profile caption',
    ], $attrs), [
        new AstNode('table_head', [], [
            $row([$cell('Metric'), $cell('Value')]),
        ]),
        new AstNode('table_body', [], [
            $row([$cell('Probe'), $cell('Ready')]),
        ]),
    ]);
};

$write = static fn (AstNode $table, array $options = []): string => (new MarkdownWriter($options))->write($document([$table]));

$assertPipe = static function (TestRunner $t, string $markdown): void {
    $t->contains('| Metric | Value |', $markdown);
    $t->contains('| Probe  | Ready |', $markdown);
    $t->contains(': Profile caption', $markdown);
    $t->true(!str_contains($markdown, '<table'), 'Pipe-capable profiles should not fall back to raw HTML tables');
};

$assertSimple = static function (TestRunner $t, string $markdown): void {
    $t->contains('Metric  Value', $markdown);
    $t->contains('------', $markdown);
    $t->contains(': Profile caption', $markdown);
    $t->true(!str_contains($markdown, '| Metric |'), 'Simple table fallback should not render pipe separators');
    $t->true(!str_contains($markdown, '<table'), 'Simple table fallback should not render raw HTML tables');
};

$assertGrid = static function (TestRunner $t, string $markdown): void {
    $t->contains('+--------+-------+', $markdown);
    $t->contains('| Metric | Value |', $markdown);
    $t->contains('+========+=======+', $markdown);
    $t->contains(': Profile caption', $markdown);
    $t->true(!str_starts_with($markdown, '<table'), 'Grid table fallback should not render raw HTML tables');
};

$assertHtml = static function (TestRunner $t, string $markdown): void {
    $t->contains('<table>', $markdown);
    $t->contains('<caption>Profile caption</caption>', $markdown);
    $t->contains('<th', $markdown);
    $t->contains('<td', $markdown);
    $t->true(!str_contains($markdown, '| Metric | Value |'), 'HTML table fallback should not leak pipe syntax');
};

$tests = [];

$unsupportedRequests = [
    'default pipe request' => [[], []],
    'node simple request' => [['markdownTableFormat' => 'simple'], []],
    'node grid request' => [['markdownTableFormat' => 'grid'], []],
    'option tableStyle simple request' => [[], ['tableStyle' => 'simple']],
    'option markdownTableFormat simple request' => [[], ['markdownTableFormat' => 'simple-table']],
    'option markdownTableFormat grid request' => [[], ['markdownTableFormat' => 'grid-table']],
];

foreach (['commonmark', 'markdown_strict', 'markdown-strict'] as $format) {
    foreach ($unsupportedRequests as $label => [$attrs, $options]) {
        $tests["maps upstream markdown writer table flavor profile {$format} html fallback {$label}"] =
            static function (TestRunner $t) use ($format, $attrs, $options, $table, $write, $assertHtml): void {
                $markdown = $write($table($attrs), array_replace(['format' => $format], $options));

                $assertHtml($t, $markdown);
            };
    }
}

$gfmRequests = [
    'default pipe request' => [[], []],
    'node simple falls back to pipe' => [['markdownTableFormat' => 'simple'], []],
    'node grid falls back to pipe' => [['markdownTableFormat' => 'grid'], []],
    'option simple falls back to pipe' => [[], ['tableStyle' => 'simple']],
    'option grid falls back to pipe' => [[], ['markdownTableFormat' => 'grid']],
];

foreach (['gfm', 'markdown_github', 'markdown-github'] as $format) {
    foreach ($gfmRequests as $label => [$attrs, $options]) {
        $tests["maps upstream markdown writer table flavor profile {$format} pipe fallback {$label}"] =
            static function (TestRunner $t) use ($format, $attrs, $options, $table, $write, $assertPipe): void {
                $markdown = $write($table($attrs), array_replace(['format' => $format], $options));

                $assertPipe($t, $markdown);
            };
    }
}

$pipeOverrideCases = [
    'commonmark format plus pipe tables' => ['options' => ['format' => 'commonmark+pipe_tables']],
    'commonmark configured plus pipe tables' => ['options' => ['format' => 'commonmark', 'extensions' => ['+pipe_tables']]],
    'commonmark configured table alias' => ['options' => ['format' => 'commonmark', 'extensions' => ['table' => true]]],
    'strict format plus pipe tables' => ['options' => ['format' => 'markdown_strict+pipe_tables']],
    'strict configured tables alias' => ['options' => ['format' => 'markdown_strict', 'extensions' => ['+tables']]],
    'strict configured pipe table alias' => ['options' => ['format' => 'markdown_strict', 'extensions' => ['pipe_table' => true]]],
];

foreach ($pipeOverrideCases as $label => $case) {
    $tests["maps upstream markdown writer table flavor profile pipe override {$label}"] =
        static function (TestRunner $t) use ($case, $table, $write, $assertPipe): void {
            $markdown = $write($table(), $case['options']);

            $assertPipe($t, $markdown);
        };
}

$simpleFallbackCases = [
    'associative extension flags' => ['extensions' => ['pipe_tables' => false, 'simple_tables' => true]],
    'string extension flags' => ['extensions' => '-pipe_tables +simple_tables'],
    'comma extension flags' => ['extensions' => '-pipe_tables,+simple_tables'],
    'token extension flags' => ['extensions' => ['-pipe_tables', '+simple_tables']],
    'pipe alias disabled simple alias enabled' => ['extensions' => ['pipe_table' => false, 'simple_table' => true]],
    'tables alias disabled simple tables enabled' => ['extensions' => ['tables' => false, 'simple_tables' => true]],
    'format commonmark enables simple only' => ['format' => 'commonmark', 'extensions' => ['+simple_tables']],
    'format strict enables simple only' => ['format' => 'markdown_strict', 'extensions' => ['+simple_tables']],
];

foreach ($simpleFallbackCases as $label => $options) {
    $tests["maps upstream markdown writer table flavor profile simple fallback {$label}"] =
        static function (TestRunner $t) use ($options, $table, $write, $assertSimple): void {
            $markdown = $write($table(), $options);

            $assertSimple($t, $markdown);
        };
}

$gridFallbackCases = [
    'associative extension flags' => ['extensions' => ['pipe_tables' => false, 'simple_tables' => false, 'grid_tables' => true]],
    'string extension flags' => ['extensions' => '-pipe_tables -simple_tables +grid_tables'],
    'comma extension flags' => ['extensions' => '-pipe_tables,-simple_tables,+grid_tables'],
    'token extension flags' => ['extensions' => ['-pipe_tables', '-simple_tables', '+grid_tables']],
    'pipe simple aliases disabled grid alias enabled' => ['extensions' => ['pipe_table' => false, 'simple_table' => false, 'grid_table' => true]],
    'format commonmark enables grid only' => ['format' => 'commonmark', 'extensions' => ['+grid_tables']],
    'format strict enables grid only' => ['format' => 'markdown_strict', 'extensions' => ['+grid_tables']],
    'node default falls to grid when pipe and simple disabled' => ['markdownTableFormat' => 'pipe', 'options' => ['extensions' => ['-pipe_tables', '-simple_tables', '+grid_tables']]],
];

foreach ($gridFallbackCases as $label => $case) {
    $attrs = isset($case['markdownTableFormat']) ? ['markdownTableFormat' => $case['markdownTableFormat']] : [];
    $options = $case['options'] ?? array_diff_key($case, ['markdownTableFormat' => true]);
    $tests["maps upstream markdown writer table flavor profile grid fallback {$label}"] =
        static function (TestRunner $t) use ($attrs, $options, $table, $write, $assertGrid): void {
            $markdown = $write($table($attrs), $options);

            $assertGrid($t, $markdown);
        };
}

$htmlFallbackCases = [
    'all syntaxes disabled associative' => ['extensions' => ['pipe_tables' => false, 'simple_tables' => false, 'grid_tables' => false]],
    'all syntaxes disabled string' => ['extensions' => '-pipe_tables -simple_tables -grid_tables'],
    'all syntaxes disabled comma string' => ['extensions' => '-pipe_tables,-simple_tables,-grid_tables'],
    'all syntaxes disabled tokens' => ['extensions' => ['-pipe_tables', '-simple_tables', '-grid_tables']],
    'all syntaxes disabled aliases' => ['extensions' => ['pipe_table' => false, 'simple_table' => false, 'grid_table' => false]],
    'commonmark simple still disabled without override' => ['format' => 'commonmark', 'markdownTableFormat' => 'simple'],
    'strict grid still disabled without override' => ['format' => 'markdown_strict', 'markdownTableFormat' => 'grid'],
    'gfm simple and pipe disabled has no supported table syntax' => ['format' => 'gfm', 'extensions' => ['-pipe_tables'], 'markdownTableFormat' => 'simple'],
];

foreach ($htmlFallbackCases as $label => $case) {
    $attrs = isset($case['markdownTableFormat']) ? ['markdownTableFormat' => $case['markdownTableFormat']] : [];
    $options = array_diff_key($case, ['markdownTableFormat' => true]);
    $tests["maps upstream markdown writer table flavor profile html fallback {$label}"] =
        static function (TestRunner $t) use ($attrs, $options, $table, $write, $assertHtml): void {
            $markdown = $write($table($attrs), $options);

            $assertHtml($t, $markdown);
        };
}

$explicitSupportedCases = [
    'markdown node simple' => ['attrs' => ['markdownTableFormat' => 'simple'], 'options' => [], 'assert' => $assertSimple],
    'markdown node grid' => ['attrs' => ['markdownTableFormat' => 'grid'], 'options' => [], 'assert' => $assertGrid],
    'commonmark x node simple' => ['attrs' => ['markdownTableFormat' => 'simple'], 'options' => ['format' => 'commonmark_x'], 'assert' => $assertSimple],
    'commonmark x node grid' => ['attrs' => ['markdownTableFormat' => 'grid'], 'options' => ['format' => 'commonmark_x'], 'assert' => $assertGrid],
    'gfm plus simple tables honors node simple' => ['attrs' => ['markdownTableFormat' => 'simple'], 'options' => ['format' => 'gfm', 'extensions' => ['+simple_tables']], 'assert' => $assertSimple],
    'gfm plus grid tables honors node grid' => ['attrs' => ['markdownTableFormat' => 'grid'], 'options' => ['format' => 'gfm', 'extensions' => ['+grid_tables']], 'assert' => $assertGrid],
    'markdown option simple' => ['attrs' => [], 'options' => ['markdownTableFormat' => 'simple-table'], 'assert' => $assertSimple],
    'markdown option grid' => ['attrs' => [], 'options' => ['markdownTableFormat' => 'grid-table'], 'assert' => $assertGrid],
];

foreach ($explicitSupportedCases as $label => $case) {
    $tests["maps upstream markdown writer table flavor profile supported syntax {$label}"] =
        static function (TestRunner $t) use ($case, $table, $write): void {
            $markdown = $write($table($case['attrs']), $case['options']);

            $case['assert']($t, $markdown);
        };
}

$explicitHtmlCases = [
    'node html table format' => [['markdownTableFormat' => 'html'], []],
    'node raw html table format' => [['markdownTableFormat' => 'raw_html'], []],
    'node raw-html table format' => [['markdownTableFormat' => 'raw-html'], []],
    'option html table format' => [[], ['markdownTableFormat' => 'html']],
    'option raw html table format' => [[], ['tableStyle' => 'raw_html']],
];

foreach ($explicitHtmlCases as $label => [$attrs, $options]) {
    $tests["maps upstream markdown writer table flavor profile explicit html {$label}"] =
        static function (TestRunner $t) use ($attrs, $options, $table, $write, $assertHtml): void {
            $markdown = $write($table($attrs), $options);

            $assertHtml($t, $markdown);
        };
}

return $tests;
