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
    'pathspec sparse checkout parses gitoxide magic include and exclude rules' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':!wp-content/cache/**',
            ':(top,glob,icase)WP-CONTENT/Plugins/*/block.json',
            ':(literal)wp-content/plugins/*.literal',
        ]);

        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $spec->includesPath('WP-CONTENT/Plugins/Gutenberg/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/cache/page.html', false));
        $t->same(false, $spec->includesPath('wp-admin/admin.php', false));
        $t->same(true, $spec->includesPath('wp-content', true));
        $t->same(true, $spec->includesPath('wp-content/plugins', true));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg', true));
        $t->same(true, $spec->includesPath('wp-content/plugins/*.literal', false));

        $literalSpec = SparseCheckoutSpec::fromPathspecs([':(literal)wp-content/plugins/*.literal']);
        $t->same(true, $literalSpec->includesPath('wp-content/plugins/*.literal', false));
        $t->same(false, $literalSpec->includesPath('wp-content/plugins/plugin.literal', false));

        $literalBang = SparseCheckoutSpec::fromPathspecs(['!literal']);
        $t->same(true, $literalBang->includesPath('!literal', false));
        $t->same(false, $literalBang->includesPath('literal', false));

        $spacePath = SparseCheckoutSpec::fromPathspecs([' some/path']);
        $t->same(true, $spacePath->includesPath(' some/path', false));
        $t->same(false, $spacePath->includesPath('some/path', false));

        $excludeOnly = SparseCheckoutSpec::fromPathspecs([':!wp-content/cache/**']);
        $t->same(true, $excludeOnly->includesPath('index.php', false));
        $t->same(true, $excludeOnly->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(false, $excludeOnly->includesPath('wp-content/cache/page.html', false));

        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs(['']));
        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs([':(top']));
        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs([':(glob,literal)path']));
        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs([':(attr:binary)media/**']));
        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs([':#()path']));
        $t->throws(InvalidArgumentException::class, static fn () => SparseCheckoutSpec::fromPathspecs(['../outside']));
    },
    'pathspec sparse checkout distinguishes shell glob and path-aware glob slash matching' => static function (TestRunner $t): void {
        $shellGlob = SparseCheckoutSpec::fromPathspecs(['wp-content*']);
        $pathAwareGlob = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content*']);
        $nil = SparseCheckoutSpec::fromPathspecs([':']);
        $none = SparseCheckoutSpec::fromPathspecs([]);

        $t->same(true, $shellGlob->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $pathAwareGlob->includesPath('wp-content', true));
        $t->same(false, $pathAwareGlob->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $pathAwareGlob->includesPath('wp-content/plugins', true));
        $t->same(false, $pathAwareGlob->includesPath('wp-admin', true));
        $t->same(true, $nil->includesPath('wp-admin/admin.php', false));
        $t->same(true, $none->includesPath('wp-admin/admin.php', false));
    },
    'pathspec sparse checkout keeps excludes authoritative independent of input order' => static function (TestRunner $t): void {
        $positiveThenExclude = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':(exclude,glob)wp-content/cache/**',
        ]);
        $excludeThenPositive = SparseCheckoutSpec::fromPathspecs([
            ':(exclude,glob)wp-content/cache/**',
            'wp-content/**',
        ]);

        foreach ([$positiveThenExclude, $excludeThenPositive] as $spec) {
            $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
            $t->same(false, $spec->includesPath('wp-content/cache/page.html', false));
            $t->same(true, $spec->skipWorktree('wp-content/cache/page.html', false));
            $t->same(true, $spec->includesPath('wp-content/cache', true));
            $t->same(false, $spec->includesPath('wp-admin/admin.php', false));
        }

        $excludeOnly = SparseCheckoutSpec::fromPathspecs([':(exclude,glob)wp-content/cache/**']);
        $t->same(true, $excludeOnly->includesPath('index.php', false));
        $t->same(false, $excludeOnly->includesPath('wp-content/cache/page.html', false));
    },
    'pathspec sparse checkout uses gix wildmatch brackets escapes and recursive directory globs' => static function (TestRunner $t) use ($entryNames): void {
        $spec = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/plugins/[ag]*/block.[jt]son',
            ':(glob)wp-content/uploads/2026/0[!1-4]/**',
            ':(glob)wp-content/**/theme.\?son',
            ':(glob)wp-content/plugins/\[literal\]/block.\?son',
        ]);

        $t->same(true, $spec->includesPath('wp-content/plugins/akismet/block.json', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/block.gson', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/cache/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/block.css', false));
        $t->same(true, $spec->includesPath('wp-content/uploads/2026/05/photo.jpg', false));
        $t->same(false, $spec->includesPath('wp-content/uploads/2026/02/photo.jpg', false));
        $t->same(true, $spec->includesPath('wp-content/theme.?son', false));
        $t->same(true, $spec->includesPath('wp-content/themes/site/theme.?son', false));
        $t->same(false, $spec->includesPath('wp-content/themes/site/theme.json', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/[literal]/block.?son', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/aliteral/block.?son', false));

        $pathAwareSlashClass = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/plugins/foo[/]bar.php']);
        $shellSlashClass = SparseCheckoutSpec::fromPathspecs(['wp-content/plugins/foo[/]bar.php']);
        $t->same(false, $pathAwareSlashClass->includesPath('wp-content/plugins/foo/bar.php', false));
        $t->same(true, $shellSlashClass->includesPath('wp-content/plugins/foo/bar.php', false));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $root = new Tree([
            new TreeEntry('100644', 'index.php', $blob),
            new TreeEntry('040000', 'wp-admin', $tree),
            new TreeEntry('040000', 'wp-content', $tree),
        ]);
        $wpContent = new Tree([
            new TreeEntry('040000', 'plugins', $tree),
            new TreeEntry('040000', 'uploads', $tree),
        ]);
        $plugins = new Tree([
            new TreeEntry('040000', 'akismet', $tree),
            new TreeEntry('040000', 'gutenberg', $tree),
        ]);

        $t->same(['wp-content'], $entryNames($spec->includedTreeEntries($root)));
        $t->same(['plugins', 'uploads'], $entryNames($spec->includedTreeEntries($wpContent, 'wp-content')));
        $t->same(['akismet', 'gutenberg'], $entryNames($spec->includedTreeEntries($plugins, 'wp-content/plugins')));
    },
];
