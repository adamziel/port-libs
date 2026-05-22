<?php

declare(strict_types=1);

use PortLibs\Gitoxide\SparseCheckoutSpec;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$entryNames = static fn (array $entries): array => array_map(
    static fn (TreeEntry $entry): string => $entry->filename,
    $entries
);

return [
    'cone sparse checkout includes root and ancestor files plus selected directory contents' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::cone(['wp-content/plugins/gutenberg']);

        $t->same(['wp-content/plugins/gutenberg'], $spec->recursiveDirectories());
        $t->same(['', 'wp-content', 'wp-content/plugins'], $spec->parentDirectories());
        $t->same(true, $spec->includesPath('index.php', false));
        $t->same(true, $spec->includesPath('wp-config.php', false));
        $t->same(true, $spec->includesPath('wp-content/index.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/plugin-loader.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/src/editor.js', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/akismet/akismet.php', false));
        $t->same(false, $spec->includesPath('wp-admin/admin.php', false));

        $t->same(true, $spec->includesPath('wp-content', true));
        $t->same(true, $spec->includesPath('wp-content/plugins', true));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg', true));
        $t->same(false, $spec->includesPath('wp-content/plugins/akismet', true));
        $t->same(false, $spec->includesPath('wp-admin', true));
        $t->same(true, $spec->skipWorktree('wp-admin/admin.php', false));
    },
    'cone sparse checkout pattern files round trip to recursive directories' => static function (TestRunner $t): void {
        $patternFile = "/*\n"
            . "!/*/\n"
            . "/wp-content/\n"
            . "!/wp-content/*/\n"
            . "/wp-content/plugins/\n"
            . "!/wp-content/plugins/*/\n"
            . "/wp-content/plugins/gutenberg/\n";
        $spec = SparseCheckoutSpec::fromConePatternFile($patternFile);

        $t->same(['wp-content/plugins/gutenberg'], $spec->recursiveDirectories());
        $t->same(true, $spec->includesPath('wp-content/plugins/plugin-loader.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/build/index.asset.php', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/akismet/akismet.php', false));
    },
    'non cone sparse checkout applies include and exclude patterns in order' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::fromNonConePatternFile("/*\n!wp-content/cache/**\n\\!literal\n");

        $t->same(true, $spec->includesPath('index.php', false));
        $t->same(true, $spec->includesPath('wp-content/index.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/plugin.php', false));
        $t->same(false, $spec->includesPath('wp-content/cache/page.html', false));
        $t->same(true, $spec->includesPath('!literal', false));
    },
    'sparse checkout filters wordpress tree entries for traversal' => static function (TestRunner $t) use ($entryNames): void {
        $spec = SparseCheckoutSpec::cone(['wp-content/plugins/gutenberg']);
        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $root = new Tree([
            new TreeEntry('100644', 'index.php', $blob),
            new TreeEntry('100644', 'wp-config.php', $blob),
            new TreeEntry('040000', 'wp-admin', $tree),
            new TreeEntry('040000', 'wp-content', $tree),
        ]);
        $wpContent = new Tree([
            new TreeEntry('100644', 'index.php', $blob),
            new TreeEntry('040000', 'plugins', $tree),
            new TreeEntry('040000', 'uploads', $tree),
        ]);
        $plugins = new Tree([
            new TreeEntry('100644', 'plugin-loader.php', $blob),
            new TreeEntry('040000', 'akismet', $tree),
            new TreeEntry('040000', 'gutenberg', $tree),
        ]);
        $gutenberg = new Tree([
            new TreeEntry('100644', 'block.json', $blob),
            new TreeEntry('040000', 'src', $tree),
        ]);

        $t->same(['index.php', 'wp-config.php', 'wp-content'], $entryNames($spec->includedTreeEntries($root)));
        $t->same(['index.php', 'plugins'], $entryNames($spec->includedTreeEntries($wpContent, 'wp-content')));
        $t->same(['plugin-loader.php', 'gutenberg'], $entryNames($spec->includedTreeEntries($plugins, 'wp-content/plugins')));
        $t->same(['block.json', 'src'], $entryNames($spec->includedTreeEntries($gutenberg, 'wp-content/plugins/gutenberg')));
    },
    'case insensitive cone sparse checkout follows ignorecase matching' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::cone(['WP-Content/Plugins/Gutenberg'], ignoreCase: true);

        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $spec->includesPath('WP-CONTENT/plugins/plugin-loader.php', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/akismet/akismet.php', false));
    },
];
