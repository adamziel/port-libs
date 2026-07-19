<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\WordPressBlockWriter;

function pandoc_wordpress_core_fixture_path(string $file): string
{
    $fixtures = [
        'class-wp-block-parser.php' => [
            'url' => 'https://raw.githubusercontent.com/WordPress/wordpress-develop/6.8/src/wp-includes/class-wp-block-parser.php',
            'sha256' => '5034b45720731204a1ae4d869b06815c344c91b4844292ade9d6642b2dd02e7c',
        ],
        'class-wp-block-parser-block.php' => [
            'url' => 'https://raw.githubusercontent.com/WordPress/wordpress-develop/6.8/src/wp-includes/class-wp-block-parser-block.php',
            'sha256' => '159e4fbbf3b4721d46a25d6eab0a5b4c620b9453f3cf12c9a6b314f11c6f12e2',
        ],
        'class-wp-block-parser-frame.php' => [
            'url' => 'https://raw.githubusercontent.com/WordPress/wordpress-develop/6.8/src/wp-includes/class-wp-block-parser-frame.php',
            'sha256' => 'e4e085bbd0411ff48b4d531732c8bd153ad0c95c9f90ef371ac21c7d5572e1de',
        ],
        'blocks.php' => [
            'url' => 'https://raw.githubusercontent.com/WordPress/wordpress-develop/6.8/src/wp-includes/blocks.php',
            'sha256' => '70dac489210bccce271a3c2385f505ffabd6740cfec3c34efe289ab0e1148e25',
        ],
    ];
    if (!isset($fixtures[$file])) {
        throw new RuntimeException('Unknown WordPress fixture file: ' . $file);
    }

    $dir = sys_get_temp_dir() . '/port-libs-wordpress-core-6.8-block-parser';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create WordPress fixture cache: ' . $dir);
    }

    $path = $dir . '/' . $file;
    $expectedHash = $fixtures[$file]['sha256'];
    if (is_file($path) && hash_file('sha256', $path) === $expectedHash) {
        return $path;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'port-libs-wordpress-block-roundtrip-test',
        ],
    ]);
    $contents = @file_get_contents($fixtures[$file]['url'], false, $context);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Unable to download official WordPress fixture: ' . $fixtures[$file]['url']);
    }
    if (hash('sha256', $contents) !== $expectedHash) {
        throw new RuntimeException('Downloaded WordPress fixture hash mismatch for ' . $file);
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write WordPress fixture cache: ' . $path);
    }

    return $path;
}

function pandoc_require_wordpress_core_block_parser(): void
{
    if (!function_exists('apply_filters')) {
        function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
        {
            return $value;
        }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string
        {
            $json = json_encode($value, $flags, $depth);
            if (!is_string($json)) {
                throw new RuntimeException('Unable to JSON encode WordPress block attributes');
            }

            return $json;
        }
    }

    pandoc_wordpress_core_fixture_path('class-wp-block-parser-block.php');
    pandoc_wordpress_core_fixture_path('class-wp-block-parser-frame.php');
    require_once pandoc_wordpress_core_fixture_path('class-wp-block-parser.php');
    require_once pandoc_wordpress_core_fixture_path('blocks.php');
}

/**
 * @param list<array<string, mixed>> $blocks
 * @return list<string>
 */
function pandoc_wordpress_block_names(array $blocks): array
{
    $names = [];
    foreach ($blocks as $block) {
        $name = $block['blockName'] ?? null;
        if (is_string($name)) {
            $names[] = $name;
        }
        $innerBlocks = $block['innerBlocks'] ?? [];
        if (is_array($innerBlocks) && $innerBlocks !== []) {
            array_push($names, ...pandoc_wordpress_block_names($innerBlocks));
        }
    }

    return $names;
}

/**
 * @param list<array<string, mixed>> $blocks
 */
function pandoc_assert_no_non_whitespace_classic_blocks(TestRunner $t, array $blocks): void
{
    foreach ($blocks as $block) {
        $name = $block['blockName'] ?? null;
        if ($name === null) {
            $html = (string) ($block['innerHTML'] ?? '');
            $t->same('', trim($html), 'WordPress parser found non-whitespace classic HTML outside block comments');
        }
        $innerBlocks = $block['innerBlocks'] ?? [];
        if (is_array($innerBlocks) && $innerBlocks !== []) {
            pandoc_assert_no_non_whitespace_classic_blocks($t, $innerBlocks);
        }
    }
}

/**
 * @param list<string> $expectedNames
 */
function pandoc_assert_wordpress_round_trip(TestRunner $t, string $blocks, array $expectedNames): void
{
    pandoc_require_wordpress_core_block_parser();
    $parsed = parse_blocks($blocks);
    $t->true(is_array($parsed) && $parsed !== [], 'WordPress parse_blocks should parse at least one block');
    pandoc_assert_no_non_whitespace_classic_blocks($t, $parsed);

    $names = pandoc_wordpress_block_names($parsed);
    foreach ($expectedNames as $name) {
        $t->true(in_array($name, $names, true), 'Expected parsed WordPress block: ' . $name);
    }

    $serialized = serialize_blocks($parsed);
    $reparsed = parse_blocks($serialized);
    pandoc_assert_no_non_whitespace_classic_blocks($t, $reparsed);
    $t->same($names, pandoc_wordpress_block_names($reparsed), 'WordPress block names should survive serialize_blocks round trip');
    $t->same($serialized, serialize_blocks($reparsed), 'WordPress serialize_blocks output should be stable');
}

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $value])]);
$listItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$paragraph($value)]);
$tableCell = static fn (string $value, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, [$paragraph($value)]);
$tableRow = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

return [
    'round trips representative writer output through WordPress core parser and serializer' =>
        static function (TestRunner $t) use ($text, $paragraph, $listItem, $tableCell, $tableRow): void {
            $document = new AstNode('document', [], [
                new AstNode('heading', ['level' => 2, 'id' => 'imported-heading'], [$text('Imported heading')]),
                new AstNode('heading', ['level' => 3, 'align' => 'right'], [$text('Right aligned heading')]),
                new AstNode('paragraph', ['align' => 'center'], [$text('Centered source paragraph.')]),
                new AstNode('paragraph', [], [
                    $text('Body paragraph with '),
                    new AstNode('strong', [], [$text('strong')]),
                    $text(' and '),
                    new AstNode('link', ['url' => 'https://example.test/source'], [$text('link')]),
                    $text('.'),
                ]),
                new AstNode('bullet_list', [], [$listItem('First bullet'), $listItem('Second bullet')]),
                new AstNode('ordered_list', ['start' => 3], [$listItem('Third step'), $listItem('Fourth step')]),
                new AstNode('table', ['caption' => 'Imported table', 'alignments' => ['left', 'right']], [
                    new AstNode('table_body', [], [
                        $tableRow([$tableCell('Name', ['header' => true]), $tableCell('Value', ['header' => true])]),
                        $tableRow([$tableCell('Alpha'), $tableCell('42')]),
                    ]),
                ]),
                new AstNode('paragraph', [], [
                    new AstNode('image', [
                        'url' => 'media/example.png',
                        'alt' => 'Example image',
                        'title' => 'Example title',
                        'width' => 320,
                        'height' => 180,
                    ]),
                ]),
                new AstNode('blockquote', [], [$paragraph('Quoted import text.')]),
                new AstNode('code_block', ['text' => "echo 'import';\n", 'classes' => ['language-php']]),
                new AstNode('line_block', [], [
                    new AstNode('line', [], [$text('First verse line')]),
                    new AstNode('line', [], [$text('Second verse line')]),
                ]),
                new AstNode('horizontal_rule'),
                new AstNode('raw_html', ['html' => '<aside class="source-note">Raw source island</aside>']),
            ]);

            $blocks = (new WordPressBlockWriter())->write($document);
            $t->contains('<!-- wp:heading {"level":3,"textAlign":"right"} -->', $blocks);
            $t->contains('<!-- wp:paragraph {"align":"center"} -->', $blocks);

            pandoc_assert_wordpress_round_trip($t, $blocks, [
                'core/heading',
                'core/paragraph',
                'core/list',
                'core/table',
                'core/image',
                'core/quote',
                'core/code',
                'core/verse',
                'core/separator',
                'core/html',
            ]);
        },

    'renders wordpress page primitives for anchors media captions tables and mathml' =>
        static function (TestRunner $t) use ($text, $paragraph, $tableCell, $tableRow): void {
            $document = new AstNode('document', [], [
                new AstNode('heading', ['level' => 2, 'id' => 'toc-target'], [$text('Linked section')]),
                new AstNode('figure', ['caption' => 'Chart caption', 'captionInlines' => [$text('Chart caption')]], [
                    new AstNode('image', [
                        'url' => 'media/chart.png',
                        'alt' => 'Chart alt',
                        'width' => 640,
                        'height' => 360,
                    ]),
                ]),
                new AstNode('table', ['caption' => 'Data table'], [
                    new AstNode('table_body', [], [
                        $tableRow([$tableCell('Metric', ['header' => true]), $tableCell('Value', ['header' => true])]),
                        $tableRow([$tableCell('Alpha'), $tableCell('42')]),
                    ]),
                ]),
                new AstNode('paragraph', [], [
                    $text('Inline math '),
                    new AstNode('math', [
                        'text' => 'x',
                        'mathml' => '<math><mi>x</mi></math>',
                    ]),
                    $text('.'),
                ]),
            ]);

            $blocks = (new WordPressBlockWriter(['writerHTMLMathMethod' => 'mathml']))->write($document);
            pandoc_assert_wordpress_round_trip($t, $blocks, [
                'core/heading',
                'core/image',
                'core/table',
                'core/paragraph',
            ]);

            $serialized = serialize_blocks(parse_blocks($blocks));
            $t->contains('<h2 id="toc-target">Linked section</h2>', $serialized);
            $t->contains('class="wp-block-image"', $serialized);
            $t->contains('<figcaption>Chart caption</figcaption>', $serialized);
            $t->contains('<!-- wp:table -->', $serialized);
            $t->contains('<figcaption class="wp-element-caption">Data table</figcaption>', $serialized);
            $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>', $serialized);
        },

    'round trips selected showcase wordpress block output through WordPress core parser and serializer' =>
        static function (TestRunner $t): void {
            $root = dirname(__DIR__, 3);
            $path = $root . '/pandoc-showcase/outputs/markdown-github-rendered-syntax/wordpress-blocks.html';
            $t->true(is_file($path), 'Expected generated GitHub Markdown showcase WordPress block output to exist');
            $blocks = file_get_contents($path);
            $t->true(is_string($blocks) && $blocks !== '', 'Expected readable showcase WordPress block output');

            pandoc_assert_wordpress_round_trip($t, $blocks, [
                'core/heading',
                'core/paragraph',
                'core/list',
                'core/table',
                'core/image',
                'core/code',
                'core/html',
            ]);
        },

    'round trips every successful showcase wordpress import through WordPress core' =>
        static function (TestRunner $t): void {
            $root = dirname(__DIR__, 3);
            $manifestPath = $root . '/pandoc-showcase/manifest.json';
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $records = is_array($manifest) && is_array($manifest['records'] ?? null)
                ? $manifest['records']
                : [];
            $expectedSuccessfulImports = count(array_filter(
                $records,
                static fn (mixed $record): bool => is_array($record)
                    && (($record['wpBlocks']['ok'] ?? false) === true)
            ));
            $checked = 0;

            $t->true(
                $expectedSuccessfulImports >= 90,
                'The successful WordPress showcase corpus should not shrink below its established floor.'
            );

            pandoc_require_wordpress_core_block_parser();
            foreach ($records as $record) {
                if (!is_array($record) || (($record['wpBlocks']['ok'] ?? false) !== true)) {
                    continue;
                }

                $id = (string) ($record['id'] ?? 'unknown');
                $relativePath = (string) ($record['wpBlocks']['path'] ?? '');
                $path = $root . '/pandoc-showcase/' . ltrim($relativePath, '/');
                $t->true(is_file($path), "Expected WordPress output for {$id}");
                $blocks = file_get_contents($path);
                $t->true(is_string($blocks) && $blocks !== '', "Expected readable WordPress blocks for {$id}");
                if (!is_string($blocks) || $blocks === '') {
                    continue;
                }

                $parsed = parse_blocks($blocks);
                $t->true(is_array($parsed) && $parsed !== [], "WordPress should parse blocks for {$id}");
                if (!is_array($parsed) || $parsed === []) {
                    continue;
                }
                pandoc_assert_no_non_whitespace_classic_blocks($t, $parsed);

                $serialized = serialize_blocks($parsed);
                $reparsed = parse_blocks($serialized);
                pandoc_assert_no_non_whitespace_classic_blocks($t, $reparsed);
                $t->same(
                    pandoc_wordpress_block_names($parsed),
                    pandoc_wordpress_block_names($reparsed),
                    "WordPress block names should survive serialize_blocks for {$id}"
                );
                $t->same(
                    $serialized,
                    serialize_blocks($reparsed),
                    "WordPress serialization should be stable for {$id}"
                );
                $checked++;
            }

            $t->same(
                $expectedSuccessfulImports,
                $checked,
                'Expected every successful current showcase sample to have valid WordPress block output.'
            );
        },
];
