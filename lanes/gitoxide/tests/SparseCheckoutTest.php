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
    'non cone sparse checkout follows gix ignore pattern file trimming boundaries' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::fromNonConePatternFile(
            "\xEF\xBB\xBFwp-content/mu-plugins/**   \n"
            . "wp-content/plugins/**   \n"
            . "!wp-content/plugins/cache/**   \n"
            . "\\#literal-plugin.php   \n"
            . "\\!literal-plugin.php   \n"
            . "wp-content/uploads/hero\\  \n"
            . "  \t  \n"
        );

        $t->same(true, $spec->includesPath('wp-content/mu-plugins/loader.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/cache/page.html', false));
        $t->same(true, $spec->skipWorktree('wp-content/plugins/cache/page.html', false));
        $t->same(true, $spec->includesPath('#literal-plugin.php', false));
        $t->same(false, $spec->includesPath('\\#literal-plugin.php', false));
        $t->same(true, $spec->includesPath('!literal-plugin.php', false));
        $t->same(false, $spec->includesPath('literal-plugin.php', false));
        $t->same(true, $spec->includesPath('wp-content/uploads/hero ', false));
        $t->same(false, $spec->includesPath('wp-content/uploads/hero', false));
        $t->same(false, $spec->includesPath("  \t", false));
        $t->same(false, $spec->includesPath('wp-admin/admin.php', false));
    },
    'non cone sparse checkout preserves extra leading and trailing slash bytes' => static function (TestRunner $t) use ($entryNames): void {
        $spec = SparseCheckoutSpec::fromNonConePatternFile(
            "//wp-content/cache/**\n"
            . "wp-content/generated///\n"
            . "/wp-content/plugins/\n"
        );

        $t->same(false, $spec->includesPath('wp-content/cache/page.html', false));
        $t->same(true, $spec->skipWorktree('wp-content/cache/page.html', false));
        $t->same(false, $spec->includesPath('wp-content/generated', true));
        $t->same(false, $spec->includesPath('wp-content/generated/page.html', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));

        $directory = SparseCheckoutSpec::fromNonConePatternFile("wp-content/uploads/\n");
        $t->same(true, $directory->includesPath('wp-content/uploads', true));
        $t->same(true, $directory->includesPath('wp-content/uploads/2026/hero.jpg', false));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $wpContent = new Tree([
            new TreeEntry('040000', 'cache', $tree),
            new TreeEntry('040000', 'generated', $tree),
            new TreeEntry('040000', 'plugins', $tree),
            new TreeEntry('040000', 'uploads', $tree),
            new TreeEntry('100644', 'index.php', $blob),
        ]);

        $t->same(['plugins'], $entryNames($spec->includedTreeEntries($wpContent, 'wp-content')));
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
    'pathspec sparse checkout keeps root matched before always negative specs' => static function (TestRunner $t) use ($entryNames): void {
        $shortNegativeNil = SparseCheckoutSpec::fromPathspecs([':!']);
        $longNegativeNil = SparseCheckoutSpec::fromPathspecs([':(exclude)']);
        $positiveThenNegativeNil = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':!',
        ]);

        foreach ([$shortNegativeNil, $longNegativeNil, $positiveThenNegativeNil] as $spec) {
            $t->same(true, $spec->includesPath('', true));
            $t->same(true, $spec->includesPath('', false));
            $t->same(false, $spec->skipWorktree('', true));
            $t->same(false, $spec->includesPath('wp-content', true));
            $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
            $t->same(true, $spec->skipWorktree('wp-content/plugins/gutenberg/block.json', false));
        }

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $root = new Tree([
            new TreeEntry('100644', 'index.php', $blob),
            new TreeEntry('040000', 'wp-content', $tree),
        ]);

        $t->same([], $entryNames($shortNegativeNil->includedTreeEntries($root)));
    },
    'pathspec sparse checkout honors gitoxide default search modes' => static function (TestRunner $t): void {
        $shellDefault = SparseCheckoutSpec::fromPathspecs(['wp-content/plugins/*']);
        $pathAwareDefault = SparseCheckoutSpec::fromPathspecs(
            ['wp-content/plugins/*'],
            defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_PATH_AWARE_GLOB,
        );
        $noGlobDefault = SparseCheckoutSpec::fromPathspecs(
            ['wp-content/plugins/*.php', ':(glob)wp-content/mu-plugins/*.php'],
            defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL,
        );
        $literalDefault = SparseCheckoutSpec::fromPathspecs(
            [':(glob)wp-content/plugins/*.php', ':'],
            literalDefault: true,
        );

        $t->same(true, $shellDefault->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $pathAwareDefault->includesPath('wp-content/plugins/gutenberg', true));
        $t->same(false, $pathAwareDefault->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $pathAwareDefault->includesPath('wp-content/plugins', true));

        $t->same(true, $noGlobDefault->includesPath('wp-content/plugins/*.php', false));
        $t->same(false, $noGlobDefault->includesPath('wp-content/plugins/gutenberg.php', false));
        $t->same(true, $noGlobDefault->includesPath('wp-content/mu-plugins/loader.php', false));
        $t->same(false, $noGlobDefault->includesPath('wp-content/mu-plugins/nested/loader.php', false));

        $t->same(true, $literalDefault->includesPath(':(glob)wp-content/plugins/*.php', false));
        $t->same(true, $literalDefault->includesPath(':', false));
        $t->same(false, $literalDefault->includesPath('wp-content/plugins/gutenberg.php', false));
        $t->same(false, $literalDefault->includesPath('wp-admin/admin.php', false));

        $literalDirectory = SparseCheckoutSpec::fromPathspecs(
            ['wp-content/cache/'],
            literalDefault: true,
        );
        $parsedDirectory = SparseCheckoutSpec::fromPathspecs(
            ['wp-content/cache/'],
            defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL,
        );
        $t->same(true, $literalDirectory->includesPath('wp-content/cache', false));
        $t->same(false, $parsedDirectory->includesPath('wp-content/cache', false));
        $t->same(true, $parsedDirectory->includesPath('wp-content/cache', true));

        $root = '/srv/www/example.com/current';
        $absoluteLiteral = SparseCheckoutSpec::fromPathspecs(
            [$root . '/wp-content/*/readme.md'],
            ignoreCase: true,
            root: $root,
            defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL,
        );
        $t->same(true, $absoluteLiteral->includesPath('wp-content/*/README.md', false));
        $t->same(false, $absoluteLiteral->includesPath('WP-CONTENT/*/README.md', false));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecs(
                ['wp-content/**'],
                defaultSearchMode: 'unsupported',
            ),
        );
    },
    'pathspec sparse checkout applies gitoxide environment defaults' => static function (TestRunner $t): void {
        $literalWins = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            [':(glob)wp-content/plugins/*.php', ':'],
            [
                'GIT_LITERAL_PATHSPECS' => 'yes',
                'GIT_ICASE_PATHSPECS' => '+10',
                'GIT_GLOB_PATHSPECS' => "yesn't",
                'GIT_NOGLOB_PATHSPECS' => 'true',
            ],
        );

        $t->same(true, $literalWins->includesPath(':(GLOB)WP-CONTENT/PLUGINS/*.PHP', false));
        $t->same(true, $literalWins->includesPath(':', false));
        $t->same(false, $literalWins->includesPath('wp-content/plugins/gutenberg.php', false));
        $t->same(false, $literalWins->includesPath('wp-admin/admin.php', false));

        $globDefault = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['wp-content/plugins/*.php'],
            ['GIT_GLOB_PATHSPECS' => '1'],
        );
        $t->same(true, $globDefault->includesPath('wp-content/plugins/gutenberg.php', false));
        $t->same(false, $globDefault->includesPath('wp-content/plugins/nested/plugin.php', false));
        $t->same(true, $globDefault->includesPath('wp-content/plugins', true));

        $noGlobDefault = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['wp-content/plugins/*.php', ':(glob)wp-content/mu-plugins/*.php'],
            ['GIT_NOGLOB_PATHSPECS' => 'on'],
        );
        $t->same(true, $noGlobDefault->includesPath('wp-content/plugins/*.php', false));
        $t->same(false, $noGlobDefault->includesPath('wp-content/plugins/gutenberg.php', false));
        $t->same(true, $noGlobDefault->includesPath('wp-content/mu-plugins/loader.php', false));
        $t->same(false, $noGlobDefault->includesPath('wp-content/mu-plugins/nested/loader.php', false));

        $inheritedIcase = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['plugins/*.php'],
            ['GIT_ICASE_PATHSPECS' => '-1'],
            prefix: 'WP-CONTENT',
        );
        $t->same(true, $inheritedIcase->includesPath('WP-CONTENT/plugins/Loader.PHP', false));
        $t->same(true, $inheritedIcase->includesPath('WP-CONTENT/plugins/loader.php', false));
        $t->same(false, $inheritedIcase->includesPath('wp-content/plugins/Loader.PHP', false));
        $t->same(true, $inheritedIcase->includesPath('WP-CONTENT', true));
        $t->same(false, $inheritedIcase->includesPath('wp-content', true));

        $emptyGlobDefault = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['wp-content/plugins/*.php'],
            ['GIT_GLOB_PATHSPECS' => ''],
        );
        $t->same(true, $emptyGlobDefault->includesPath('wp-content/plugins/nested/plugin.php', false));

        $falseNoGlobDefault = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['wp-content/plugins/*.php'],
            ['GIT_NOGLOB_PATHSPECS' => '0'],
        );
        $t->same(true, $falseNoGlobDefault->includesPath('wp-content/plugins/*.php', false));
        $t->same(false, $falseNoGlobDefault->includesPath('wp-content/plugins/gutenberg.php', false));

        $globOverriddenByFalseNoGlob = SparseCheckoutSpec::fromPathspecsWithEnvironment(
            ['wp-content/plugins/*.php'],
            [
                'GIT_GLOB_PATHSPECS' => 'true',
                'GIT_NOGLOB_PATHSPECS' => 'false',
            ],
        );
        $t->same(true, $globOverriddenByFalseNoGlob->includesPath('wp-content/plugins/*.php', false));
        $t->same(false, $globOverriddenByFalseNoGlob->includesPath('wp-content/plugins/gutenberg.php', false));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecsWithEnvironment(
                ['wp-content/plugins/*.php'],
                [
                    'GIT_GLOB_PATHSPECS' => 'true',
                    'GIT_NOGLOB_PATHSPECS' => 'true',
                ],
            ),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecsWithEnvironment(
                ['wp-content/plugins/*.php'],
                ['GIT_ICASE_PATHSPECS' => "yesn't"],
            ),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecsWithEnvironment(
                ['wp-content/plugins/*.php'],
                ['GIT_LITERAL_PATHSPECS' => '9223372036854775808'],
            ),
        );
    },
    'pathspec sparse checkout treats unknown exact directory only matches as files' => static function (TestRunner $t): void {
        $directory = SparseCheckoutSpec::fromPathspecs(['wp-content/cache/']);

        $t->same(false, $directory->includesPath('wp-content/cache', null));
        $t->same(true, $directory->skipWorktree('wp-content/cache', null));
        $t->same(false, $directory->includesPath('wp-content/cache', false));
        $t->same(true, $directory->includesPath('wp-content/cache', true));
        $t->same(true, $directory->includesPath('wp-content/cache/page.html', null));
        $t->same(true, $directory->includesPath('wp-content/cache/page.html', false));
        $t->same(false, $directory->includesPath('wp-content/cache-busting', true));

        $prefixedEmpty = SparseCheckoutSpec::fromPathspecs([], prefix: 'wp-content/themes');
        $t->same(false, $prefixedEmpty->includesPath('wp-content/themes', null));
        $t->same(true, $prefixedEmpty->skipWorktree('wp-content/themes', null));
        $t->same(false, $prefixedEmpty->includesPath('wp-content/themes', false));
        $t->same(true, $prefixedEmpty->includesPath('wp-content/themes', true));
        $t->same(true, $prefixedEmpty->includesPath('wp-content/themes/acme/style.css', null));
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
    'pathspec sparse checkout keeps directory only excludes authoritative during prefix traversal' => static function (TestRunner $t) use ($entryNames): void {
        $upstream = SparseCheckoutSpec::fromPathspecs([
            ':/foo',
            ':!/foo/target/',
        ], prefix: 'foo');

        $t->same(true, $upstream->includesPath('foo', true));
        $t->same(true, $upstream->includesPath('foo/bar', false));
        $t->same(true, $upstream->includesPath('foo/target', false));
        $t->same(false, $upstream->includesPath('foo/target', true));
        $t->same(false, $upstream->includesPath('foo/target/file', false));
        $t->same(true, $upstream->skipWorktree('foo/target', true));
        $t->same(true, $upstream->skipWorktree('foo/target/file', false));

        $wordpress = SparseCheckoutSpec::fromPathspecs([
            ':/wp-content',
            ':!/wp-content/cache/',
        ], prefix: 'wp-content');
        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $wpContent = new Tree([
            new TreeEntry('100644', 'index.php', $blob),
            new TreeEntry('040000', 'cache', $tree),
            new TreeEntry('040000', 'cache-busting', $tree),
            new TreeEntry('040000', 'plugins', $tree),
        ]);

        $t->same(true, $wordpress->includesPath('wp-content', true));
        $t->same(true, $wordpress->includesPath('wp-content/cache', false));
        $t->same(false, $wordpress->includesPath('wp-content/cache', true));
        $t->same(false, $wordpress->includesPath('wp-content/cache/page.html', false));
        $t->same(true, $wordpress->includesPath('wp-content/cache-busting/loader.php', false));
        $t->same(true, $wordpress->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(['index.php', 'cache-busting', 'plugins'], $entryNames($wordpress->includedTreeEntries($wpContent, 'wp-content')));
    },
    'pathspec sparse checkout keeps negative wildcard directories traversable' => static function (TestRunner $t) use ($entryNames): void {
        $pathAware = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':(exclude,glob)wp-content/*-cache',
        ]);
        $excludeOnly = SparseCheckoutSpec::fromPathspecs([
            ':(exclude,glob)wp-content/*-cache',
        ]);
        $directoryOnly = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':(exclude,glob)wp-content/*-cache/',
        ]);
        $exactDirectoryOnly = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**',
            ':(exclude)wp-content/generated-cache/',
        ]);
        $shellGlob = SparseCheckoutSpec::fromPathspecs([
            ':(exclude)wp-content*',
        ]);

        $t->same(true, $pathAware->includesPath('wp-content/generated-cache', true));
        $t->same(false, $pathAware->includesPath('wp-content/generated-cache', false));
        $t->same(true, $pathAware->includesPath('wp-content/generated-cache/index.php', false));
        $t->same(true, $pathAware->includesPath('wp-content/plugins/gutenberg/block.json', false));

        $t->same(true, $excludeOnly->includesPath('wp-content/generated-cache', true));
        $t->same(false, $excludeOnly->includesPath('wp-content/generated-cache', false));
        $t->same(true, $excludeOnly->includesPath('wp-content/generated-cache/index.php', false));
        $t->same(true, $excludeOnly->includesPath('wp-content/plugins/gutenberg/block.json', false));

        $t->same(true, $directoryOnly->includesPath('wp-content/generated-cache', true));
        $t->same(true, $directoryOnly->includesPath('wp-content/generated-cache/index.php', false));
        $t->same(true, $directoryOnly->includesPath('wp-content/generated-cache-busting/index.php', false));

        $t->same(false, $exactDirectoryOnly->includesPath('wp-content/generated-cache', true));
        $t->same(false, $exactDirectoryOnly->includesPath('wp-content/generated-cache/index.php', false));
        $t->same(true, $exactDirectoryOnly->includesPath('wp-content/generated-cache-busting/index.php', false));

        $t->same(true, $shellGlob->includesPath('wp-content', true));
        $t->same(false, $shellGlob->includesPath('wp-content', false));
        $t->same(false, $shellGlob->includesPath('wp-content/index.php', false));
        $t->same(true, $shellGlob->includesPath('wp-admin/admin.php', false));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $wpContent = new Tree([
            new TreeEntry('040000', 'generated-cache', $tree),
            new TreeEntry('040000', 'generated-cache-busting', $tree),
            new TreeEntry('040000', 'plugins', $tree),
            new TreeEntry('100644', 'theme-cache', $blob),
        ]);

        $t->same(
            ['generated-cache', 'generated-cache-busting', 'plugins'],
            $entryNames($pathAware->includedTreeEntries($wpContent, 'wp-content')),
        );
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

        $backslashBytes = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/plugins/f\\\\oo/block.json',
            ':(glob)wp-content/plugins/[[-\\]]/block.json',
        ]);
        $t->same(true, $backslashBytes->includesPath('wp-content/plugins/f\\oo/block.json', false));
        $t->same(false, $backslashBytes->includesPath('wp-content/plugins/f/oo/block.json', false));
        $t->same(true, $backslashBytes->includesPath('wp-content/plugins/\\/block.json', false));
        $t->same(true, $backslashBytes->includesPath('wp-content/plugins/[/block.json', false));
        $t->same(true, $backslashBytes->includesPath('wp-content/plugins/]/block.json', false));
        $t->same(false, $backslashBytes->includesPath('wp-content/plugins/-/block.json', false));

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
    'pathspec sparse checkout shell wildmatch treats LF as an ordinary byte' => static function (TestRunner $t) use ($entryNames): void {
        $shellStar = SparseCheckoutSpec::fromPathspecs(['wp-content*']);
        $shellQuestion = SparseCheckoutSpec::fromPathspecs(['wp-content?/plugins/gutenberg/block.json']);
        $pathAwareQuestion = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content?/plugins/gutenberg/block.json']);
        $pathAwareStar = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content*/plugins/gutenberg/block.json']);
        $exact = SparseCheckoutSpec::fromPathspecs(['wp-content']);

        $t->same(true, $shellStar->includesPath("wp-content\n/plugins/gutenberg/block.json", false));
        $t->same(true, $shellStar->includesPath("wp-content\n", true));
        $t->same(true, $shellQuestion->includesPath("wp-content\n/plugins/gutenberg/block.json", false));
        $t->same(true, $pathAwareQuestion->includesPath("wp-content\n/plugins/gutenberg/block.json", false));
        $t->same(true, $pathAwareStar->includesPath("wp-content\n/plugins/gutenberg/block.json", false));
        $t->same(false, $pathAwareQuestion->includesPath("wp-content\n/plugins/gutenberg/readme.md", false));
        $t->same(false, $exact->includesPath("wp-content\n", false));
        $t->same(false, $exact->includesPath("wp-content\n", true));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $root = new Tree([
            new TreeEntry('040000', "wp-content\n", $tree),
            new TreeEntry('040000', 'wp-content', $tree),
            new TreeEntry('040000', 'wp-admin', $tree),
            new TreeEntry('100644', "wp-content\n-file.php", $blob),
        ]);

        $t->same(["wp-content\n", 'wp-content', "wp-content\n-file.php"], $entryNames($shellStar->includedTreeEntries($root)));
    },
    'pathspec sparse checkout keeps gix double star path component boundaries' => static function (TestRunner $t): void {
        $componentLocal = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/**.php',
        ]);
        $recursiveComponent = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/**/loader.php',
        ]);
        $midComponent = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/plugins**/loader.php',
        ]);
        $escapedSlashComponent = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/**\/loader.php',
        ]);
        $shellGlob = SparseCheckoutSpec::fromPathspecs([
            'wp-content/**.php',
        ]);

        $t->same(true, $componentLocal->includesPath('wp-content/index.php', false));
        $t->same(false, $componentLocal->includesPath('wp-content/plugins/loader.php', false));
        $t->same(false, $componentLocal->includesPath('wp-content/plugins/nested/loader.php', false));

        $t->same(true, $recursiveComponent->includesPath('wp-content/loader.php', false));
        $t->same(true, $recursiveComponent->includesPath('wp-content/plugins/loader.php', false));
        $t->same(true, $recursiveComponent->includesPath('wp-content/plugins/nested/loader.php', false));

        $t->same(true, $midComponent->includesPath('wp-content/plugins-vendor/loader.php', false));
        $t->same(false, $midComponent->includesPath('wp-content/plugins/vendor/loader.php', false));
        $t->same(false, $midComponent->includesPath('wp-content/plugins/vendor/nested/loader.php', false));

        $t->same(true, $escapedSlashComponent->includesPath('wp-content/loader.php', false));
        $t->same(true, $escapedSlashComponent->includesPath('wp-content/plugins/loader.php', false));
        $t->same(false, $escapedSlashComponent->includesPath('wp-content/plugins/other.php', false));

        $t->same(true, $shellGlob->includesPath('wp-content/plugins/loader.php', false));
        $t->same(true, $shellGlob->includesPath('wp-content/plugins/nested/loader.php', false));
    },
    'pathspec sparse checkout follows gix POSIX blank and invalid class fallback boundaries' => static function (TestRunner $t) use ($entryNames): void {
        $blank = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/uploads/slot[[:blank:]]/**']);
        $space = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/uploads/slot[[:space:]]/**']);
        $invalid = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/uploads/[[:unknown:]]*.jpg']);
        $combined = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/uploads/slot[[:blank:]]/**',
            ':(glob)wp-content/uploads/[[:unknown:]]*.jpg',
        ]);

        $t->same(true, $blank->includesPath("wp-content/uploads/slot\v/photo.jpg", false));
        $t->same(true, $blank->includesPath("wp-content/uploads/slot\f/photo.jpg", false));
        $t->same(true, $blank->includesPath("wp-content/uploads/slot\r/photo.jpg", false));
        $t->same(true, $blank->includesPath("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(true, $blank->includesPath('wp-content/uploads/slot /photo.jpg', false));
        $t->same(false, $blank->includesPath('wp-content/uploads/slotx/photo.jpg', false));

        $t->same(true, $space->includesPath('wp-content/uploads/slot /photo.jpg', false));
        $t->same(false, $space->includesPath("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(false, $space->includesPath("wp-content/uploads/slot\v/photo.jpg", false));

        $t->same(false, $invalid->includesPath('wp-content/uploads/[[:unknown:]]hero.jpg', false));
        $t->same(true, $invalid->includesPath('wp-content/uploads/[[:unknown:]]*.jpg', false));
        $t->same(false, $invalid->includesPath('wp-content/uploads/uhero.jpg', false));
        $t->same(true, $invalid->includesPath('wp-content/uploads', true));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $uploads = new Tree([
            new TreeEntry('040000', "slot\v", $tree),
            new TreeEntry('100644', '[[:unknown:]]*.jpg', $blob),
            new TreeEntry('100644', '[[:unknown:]]hero.jpg', $blob),
        ]);

        $t->same(["slot\v", '[[:unknown:]]*.jpg'], $entryNames($combined->includedTreeEntries($uploads, 'wp-content/uploads')));
    },
    'pathspec sparse checkout resumes malformed POSIX class starts before verbatim fallback' => static function (TestRunner $t): void {
        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if (str_contains($message, 'preg_match()')) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            $malformed = SparseCheckoutSpec::fromPathspecs([
                ':(glob)wp-content/uploads/[[:alpha]/photo.jpg',
            ]);
            $digitPrefix = SparseCheckoutSpec::fromPathspecs([
                ':(glob)wp-content/uploads/[[:digit]ab]',
            ]);
            $emptyNamePrefix = SparseCheckoutSpec::fromPathspecs([
                ':(glob)wp-content/uploads/[[:]ab]',
            ]);
            $doubleColon = SparseCheckoutSpec::fromPathspecs([
                ':(glob)wp-content/uploads/[[::]ab]',
            ]);

            $t->same(false, $malformed->includesPath('wp-content/uploads/a/photo.jpg', false));
            $t->same(false, $malformed->includesPath('wp-content/uploads/A/photo.jpg', false));
            $t->same(true, $malformed->includesPath('wp-content/uploads/[/photo.jpg', false));
            $t->same(false, $malformed->skipWorktree('wp-content/uploads/[/photo.jpg', false));
            $t->same(true, $malformed->includesPath('wp-content/uploads/[[:alpha]/photo.jpg', false));
            $t->same(true, $malformed->skipWorktree('wp-content/uploads/a/photo.jpg', false));
            $t->same(false, $malformed->skipWorktree('wp-content/uploads/[[:alpha]/photo.jpg', false));
            $t->same(true, $malformed->includesPath('wp-content/uploads', true));
            $t->same(true, $digitPrefix->includesPath('wp-content/uploads/[ab]', false));
            $t->same(true, $emptyNamePrefix->includesPath('wp-content/uploads/[ab]', false));
            $t->same(false, $doubleColon->includesPath('wp-content/uploads/[ab]', false));
            $t->same(true, $digitPrefix->includesPath('wp-content/uploads/[[:digit]ab]', false));
        } finally {
            restore_error_handler();
        }

        $t->same([], $warnings);
    },
    'pathspec sparse checkout follows gix reversed character range boundaries' => static function (TestRunner $t) use ($entryNames): void {
        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if (str_contains($message, 'preg_match()')) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });

        try {
            $reversed = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/uploads/[z-a]/**']);
            $negated = SparseCheckoutSpec::fromPathspecs([':(glob)wp-content/uploads/[!z-a]/**']);
            $folded = SparseCheckoutSpec::fromPathspecs([':(glob,icase)wp-content/uploads/[Z-A]/**']);

            $t->same(true, $reversed->includesPath('wp-content/uploads/z/photo.jpg', false));
            $t->same(false, $reversed->includesPath('wp-content/uploads/a/photo.jpg', false));
            $t->same(false, $reversed->includesPath('wp-content/uploads/m/photo.jpg', false));
            $t->same(true, $reversed->includesPath('wp-content/uploads/z', true));
            $t->same(true, $reversed->includesPath('wp-content/uploads/a', true));
            $t->same(false, $reversed->includesPath('wp-content/uploads/[z-a].jpg', false));

            $t->same(false, $negated->includesPath('wp-content/uploads/z/photo.jpg', false));
            $t->same(true, $negated->includesPath('wp-content/uploads/a/photo.jpg', false));
            $t->same(true, $negated->includesPath('wp-content/uploads/m/photo.jpg', false));
            $t->same(true, $negated->skipWorktree('wp-content/uploads/z/photo.jpg', false));

            $t->same(true, $folded->includesPath('wp-content/uploads/z/photo.jpg', false));
            $t->same(true, $folded->includesPath('wp-content/uploads/a/photo.jpg', false));
            $t->same(true, $folded->includesPath('wp-content/uploads/m/photo.jpg', false));
            $t->same(true, $folded->includesPath('wp-content/uploads/M/photo.jpg', false));
            $t->same(false, $folded->includesPath('wp-content/uploads/0/photo.jpg', false));

            $blob = str_repeat('1', 40);
            $tree = str_repeat('2', 40);
            $uploads = new Tree([
                new TreeEntry('040000', 'z', $tree),
                new TreeEntry('040000', 'a', $tree),
                new TreeEntry('040000', 'm', $tree),
                new TreeEntry('100644', '[z-a].jpg', $blob),
            ]);

            $t->same(['z', 'a', 'm'], $entryNames($reversed->includedTreeEntries($uploads, 'wp-content/uploads')));
        } finally {
            restore_error_handler();
        }

        $t->same([], $warnings);
    },
    'pathspec sparse checkout normalizes worktree prefixes with case sensitive prefix matching' => static function (TestRunner $t): void {
        $spec = SparseCheckoutSpec::fromPathspecs([
            ':(glob)*.php',
            ':(icase)BLOCK.JSON',
            ':(top)wp-config.php',
            ':(exclude,glob)build/**',
        ], prefix: 'wp-content/plugins/gutenberg');

        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/index.php', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/src/editor.php', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/BLOCK.JSON', false));
        $t->same(false, $spec->includesPath('WP-CONTENT/plugins/gutenberg/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/Plugins/gutenberg/block.json', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/build/index.php', false));
        $t->same(true, $spec->includesPath('wp-config.php', false));
        $t->same(true, $spec->includesPath('wp-content', true));
        $t->same(true, $spec->includesPath('wp-content/plugins', true));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg', true));
        $t->same(false, $spec->includesPath('WP-CONTENT', true));

        $sibling = SparseCheckoutSpec::fromPathspecs(['../akismet/*.php'], prefix: 'wp-content/plugins/gutenberg');
        $t->same(true, $sibling->includesPath('wp-content/plugins/akismet/akismet.php', false));
        $t->same(false, $sibling->includesPath('wp-content/plugins/gutenberg/index.php', false));
        $t->same(false, $sibling->includesPath('WP-CONTENT/plugins/akismet/akismet.php', false));

        $prefixedEmpty = SparseCheckoutSpec::fromPathspecs([], prefix: 'wp-content/themes');
        $t->same(true, $prefixedEmpty->includesPath('wp-content/themes/acme/style.css', false));
        $t->same(true, $prefixedEmpty->includesPath('wp-content', true));
        $t->same(false, $prefixedEmpty->includesPath('wp-admin/admin.php', false));
        $t->same(false, $prefixedEmpty->includesPath('WP-CONTENT/themes/acme/style.css', false));

        $nil = SparseCheckoutSpec::fromPathspecs([':'], prefix: 'wp-content/themes');
        $t->same(true, $nil->includesPath('wp-admin/admin.php', false));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecs(['../../../outside.php'], prefix: 'wp-content/plugins'),
        );
    },
    'pathspec sparse checkout normalizes absolute worktree paths under root' => static function (TestRunner $t): void {
        $root = '/srv/www/example.com/current';
        $spec = SparseCheckoutSpec::fromPathspecs([
            $root . '/wp-content/plugins/gutenberg/block.json',
            ':(icase)' . $root . '/wp-content/plugins/gutenberg/readme.md',
            ':(exclude)' . $root . '/wp-content/plugins/gutenberg/build/',
        ], root: $root);

        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg/README.md', false));
        $t->same(false, $spec->includesPath('WP-CONTENT/plugins/gutenberg/README.md', false));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/build', true));
        $t->same(false, $spec->includesPath('wp-content/plugins/gutenberg/build/index.js', false));
        $t->same(true, $spec->skipWorktree('wp-content/plugins/gutenberg/build/index.js', false));
        $t->same(true, $spec->includesPath('wp-content', true));
        $t->same(true, $spec->includesPath('wp-content/plugins', true));
        $t->same(true, $spec->includesPath('wp-content/plugins/gutenberg', true));
        $t->same(false, $spec->includesPath('wp-content/plugins/akismet/akismet.php', false));

        $directory = SparseCheckoutSpec::fromPathspecs([
            $root . '/wp-content/uploads/',
        ], root: $root);
        $t->same(true, $directory->includesPath('wp-content/uploads', true));
        $t->same(true, $directory->includesPath('wp-content/uploads/2026/hero.jpg', false));
        $t->same(false, $directory->includesPath('wp-content/uploaded/hero.jpg', false));

        $topAbsolute = SparseCheckoutSpec::fromPathspecs([
            ':(top)' . $root . '/wp-config.php',
        ], prefix: 'wp-content/plugins/gutenberg', root: $root);
        $t->same(true, $topAbsolute->includesPath('wp-config.php', false));
        $t->same(false, $topAbsolute->includesPath('wp-content/plugins/gutenberg/wp-config.php', false));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecs([$root . '/../outside.php'], root: $root),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecs(['/var/www/other/wp-config.php'], root: $root),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SparseCheckoutSpec::fromPathspecs([$root . '/wp-config.php'], root: 'relative/root'),
        );
    },
    'pathspec sparse checkout preserves absolute unix backslash bytes under root' => static function (TestRunner $t) use ($entryNames): void {
        $root = '/srv/www/example.com/current';
        $literalBackslash = SparseCheckoutSpec::fromPathspecs([
            $root . '/wp-content/plugins/f\\oo/block.json',
        ], root: $root, defaultSearchMode: SparseCheckoutSpec::PATHSPEC_SEARCH_LITERAL);
        $escapedGlobBackslash = SparseCheckoutSpec::fromPathspecs([
            ':(glob)' . $root . '/wp-content/plugins/f\\\\oo/*.php',
        ], root: $root);
        $ordinaryAbsolute = SparseCheckoutSpec::fromPathspecs([
            $root . '/wp-content/plugins/f\\oo/block.json',
        ], root: $root);

        $t->same(true, $literalBackslash->includesPath('wp-content/plugins/f\\oo/block.json', false));
        $t->same(false, $literalBackslash->includesPath('wp-content/plugins/f/oo/block.json', false));
        $t->same(true, $literalBackslash->includesPath('wp-content', true));
        $t->same(true, $literalBackslash->includesPath('wp-content/plugins', true));
        $t->same(true, $literalBackslash->includesPath('wp-content/plugins/f\\oo', true));
        $t->same(false, $literalBackslash->includesPath('wp-content/plugins/f', true));

        $t->same(true, $escapedGlobBackslash->includesPath('wp-content/plugins/f\\oo/loader.php', false));
        $t->same(false, $escapedGlobBackslash->includesPath('wp-content/plugins/f/oo/loader.php', false));
        $t->same(true, $escapedGlobBackslash->includesPath('wp-content/plugins', true));
        $t->same(true, $escapedGlobBackslash->includesPath('wp-content/plugins/f\\oo', true));
        $t->same(true, $escapedGlobBackslash->includesPath('wp-content/plugins/f/oo', true));

        $t->same(true, $ordinaryAbsolute->includesPath('wp-content/plugins/foo/block.json', false));
        $t->same(true, $ordinaryAbsolute->includesPath('wp-content/plugins/f\\oo/block.json', false));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $plugins = new Tree([
            new TreeEntry('040000', 'f\\oo', $tree),
            new TreeEntry('040000', 'f', $tree),
            new TreeEntry('040000', 'foo', $tree),
        ]);

        $t->same(['f\\oo'], $entryNames($literalBackslash->includedTreeEntries($plugins, 'wp-content/plugins')));
    },
    'pathspec sparse checkout keeps escaped byte directories traversable' => static function (TestRunner $t) use ($entryNames): void {
        $escapedByte = SparseCheckoutSpec::fromPathspecs([
            'wp-content/plugins/f\\oo/block.json',
        ]);
        $escapedSlash = SparseCheckoutSpec::fromPathspecs([
            ':(glob)wp-content/plugins/foo\\/block.json',
        ]);

        $t->same(true, $escapedByte->includesPath('wp-content/plugins', true));
        $t->same(true, $escapedByte->includesPath('wp-content/plugins/f', true));
        $t->same(true, $escapedByte->includesPath('wp-content/plugins/foo', true));
        $t->same(true, $escapedByte->includesPath('wp-content/plugins/f\\oo', true));
        $t->same(false, $escapedByte->includesPath('wp-content/plugins/bar', true));
        $t->same(true, $escapedByte->includesPath('wp-content/plugins/foo/block.json', false));
        $t->same(true, $escapedByte->includesPath('wp-content/plugins/f\\oo/block.json', false));
        $t->same(false, $escapedByte->includesPath('wp-content/plugins/bar/block.json', false));

        $t->same(true, $escapedSlash->includesPath('wp-content/plugins/foo', true));
        $t->same(true, $escapedSlash->includesPath('wp-content/plugins/foo/block.json', false));
        $t->same(false, $escapedSlash->includesPath('wp-content/plugins/foo/other.json', false));

        $blob = str_repeat('1', 40);
        $tree = str_repeat('2', 40);
        $plugins = new Tree([
            new TreeEntry('040000', 'bar', $tree),
            new TreeEntry('040000', 'f', $tree),
            new TreeEntry('040000', 'f\\oo', $tree),
            new TreeEntry('040000', 'foo', $tree),
            new TreeEntry('100644', 'foo-file.php', $blob),
        ]);

        $t->same(['f', 'f\\oo', 'foo'], $entryNames($escapedByte->includedTreeEntries($plugins, 'wp-content/plugins')));
        $t->same(['f', 'foo'], $entryNames($escapedSlash->includedTreeEntries($plugins, 'wp-content/plugins')));
    },
    'pathspec sparse checkout keeps absolute wildcard prefixes case sensitive under icase' => static function (TestRunner $t): void {
        $root = '/srv/www/example.com/current';
        $foldedAbsoluteWildcard = SparseCheckoutSpec::fromPathspecs([
            ':(icase)' . $root . '/*/readme.md',
        ], root: $root);

        $t->same(false, $foldedAbsoluteWildcard->includesPath('wp-content/README.md', false));
        $t->same(false, $foldedAbsoluteWildcard->includesPath('WP-CONTENT/README.md', false));
        $t->same(true, $foldedAbsoluteWildcard->includesPath('*/README.md', false));
        $t->same(true, $foldedAbsoluteWildcard->includesPath('*', true));
        $t->same(false, $foldedAbsoluteWildcard->includesPath('wp-content', true));

        $nestedFoldedAbsoluteWildcard = SparseCheckoutSpec::fromPathspecs([
            ':(icase)' . $root . '/wp-content/*/readme.md',
        ], root: $root);

        $t->same(false, $nestedFoldedAbsoluteWildcard->includesPath('wp-content/plugins/README.md', false));
        $t->same(true, $nestedFoldedAbsoluteWildcard->includesPath('wp-content/*/README.md', false));
        $t->same(true, $nestedFoldedAbsoluteWildcard->includesPath('wp-content/*', true));
        $t->same(false, $nestedFoldedAbsoluteWildcard->includesPath('wp-content/plugins', true));

        $ordinaryAbsoluteWildcard = SparseCheckoutSpec::fromPathspecs([
            $root . '/*/readme.md',
        ], root: $root);

        $t->same(true, $ordinaryAbsoluteWildcard->includesPath('wp-content/readme.md', false));
        $t->same(false, $ordinaryAbsoluteWildcard->includesPath('wp-content/README.md', false));
        $t->same(true, $ordinaryAbsoluteWildcard->includesPath('wp-content', true));
    },
];
