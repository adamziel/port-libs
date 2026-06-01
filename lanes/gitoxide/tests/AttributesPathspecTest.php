<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitAttributes;
use PortLibs\Gitoxide\PathspecMatcher;
use PortLibs\Gitoxide\PathspecMatch;
use PortLibs\Gitoxide\PathspecSearch;

return [
    'git attributes parser follows gix attributes whitespace escapes macros and last match semantics' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("\xEF\xBB\xBF# ignored\n"
            . "*.[oa] binary custom=one\n"
            . "\"*.html\" text diff=html\n"
            . "\\!literal merge=union\n"
            . "\\#a/path -merge\n"
            . "docs/** export-ignore\n"
            . "docs/private/** !export-ignore\n"
            . "[attr]review merge=union reviewed\n"
            . "wp-content/plugins/** review diff=php\n"
            . "wp-content/plugins/private/** -reviewed merge=binary\n");

        $t->same([
            'binary' => true,
            'custom' => 'one',
            'diff' => false,
            'merge' => false,
            'text' => false,
        ], $attributes->attributesForPath('build/addon.o', ['binary', 'custom', 'diff', 'merge', 'text']));
        $t->same(['diff' => 'html', 'text' => true], $attributes->attributesForPath('index.html', ['diff', 'text']));
        $t->same(['merge' => 'union'], $attributes->attributesForPath('!literal', ['merge']));
        $t->same(['merge' => false], $attributes->attributesForPath('#a/path', ['merge']));
        $t->same(['export-ignore' => true], $attributes->attributesForPath('docs/readme.md', ['export-ignore']));
        $t->same(['export-ignore' => null], $attributes->attributesForPath('docs/private/secret.md', ['export-ignore']));
        $t->same([
            'diff' => 'php',
            'merge' => 'union',
            'review' => true,
            'reviewed' => true,
        ], $attributes->attributesForPath('wp-content/plugins/editor/block.php', ['diff', 'merge', 'review', 'reviewed']));
        $t->same([
            'merge' => 'binary',
            'review' => true,
            'reviewed' => false,
        ], $attributes->attributesForPath('wp-content/plugins/private/block.php', ['merge', 'review', 'reviewed']));
    },
    'attribute patterns use path aware wildmatch and local base matching like gix attributes search' => static function (TestRunner $t): void {
        $root = GitAttributes::fromString("dir/ marker\nDir/** folded\nD/* local\n", withBuiltInMacros: false);
        $local = GitAttributes::fromString("D/* local\n", 'a/b', withBuiltInMacros: false);

        $t->same(['marker' => true], $root->attributesForPath('dir', ['marker'], true));
        $t->same(['marker' => null], $root->attributesForPath('dir/a', ['marker'], false));
        $t->same(['folded' => true], $root->attributesForPath('Dir/a', ['folded'], false));
        $t->same(['folded' => null], $root->attributesForPath('dir/a', ['folded'], false));
        $t->same(['folded' => true], $root->attributesForPath('dir/a', ['folded'], false, true));
        $t->same(['local' => true], $local->attributesForPath('a/b/D/g', ['local'], false));
        $t->same(['local' => null], $local->attributesForPath('a/B/D/g', ['local'], false));
        $t->same(['local' => true], $local->attributesForPath('a/B/D/g', ['local'], false, true));
    },
    'attribute pathspec filters use gix glob character class parity' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/uploads/[[:digit:]][[:digit:]]/** dated\n"
            . "wp-content/uploads/[!0-4][[:digit:]]/** late-year\n"
            . "wp-content/plugins/foo[/]bar.php slash-class\n", withBuiltInMacros: false);

        $t->same(['dated' => true], $attributes->attributesForPath('wp-content/uploads/05/photo.jpg', ['dated']));
        $t->same(['dated' => null], $attributes->attributesForPath('wp-content/uploads/ab/photo.jpg', ['dated']));
        $t->same(['late-year' => true], $attributes->attributesForPath('wp-content/uploads/52/photo.jpg', ['late-year']));
        $t->same(['late-year' => null], $attributes->attributesForPath('wp-content/uploads/42/photo.jpg', ['late-year']));
        $t->same(['slash-class' => null], $attributes->attributesForPath('wp-content/plugins/foo/bar.php', ['slash-class']));
        $t->same(true, PathspecMatcher::matchesOne(
            ':(attr:dated)wp-content/uploads/**',
            'wp-content/uploads/05/photo.jpg',
            false,
            $attributes,
        ));
        $t->same(false, PathspecMatcher::matchesOne(
            ':(attr:dated)wp-content/uploads/**',
            'wp-content/uploads/ab/photo.jpg',
            false,
            $attributes,
        ));
        $search = PathspecSearch::fromSpecs([':(attr:late-year)wp-content/uploads/**']);
        $t->same(true, $search->isIncluded('wp-content/uploads/52/photo.jpg', false, $attributes));
        $t->same(false, $search->isIncluded('wp-content/uploads/42/photo.jpg', false, $attributes));
    },
    'attribute pathspec filters follow gix wildmatch POSIX blank and invalid class boundaries' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("\"wp-content/uploads/slot[[:blank:]]/**\" whitespace-upload\n"
            . "\"wp-content/uploads/[[:unknown:]]/**\" invalid-upload\n", withBuiltInMacros: false);

        $t->same(['whitespace-upload' => true], $attributes->attributesForPath("wp-content/uploads/slot\v/photo.jpg", ['whitespace-upload']));
        $t->same(['whitespace-upload' => true], $attributes->attributesForPath("wp-content/uploads/slot\t/photo.jpg", ['whitespace-upload']));
        $t->same(['whitespace-upload' => true], $attributes->attributesForPath('wp-content/uploads/slot /photo.jpg', ['whitespace-upload']));
        $t->same(['whitespace-upload' => null], $attributes->attributesForPath('wp-content/uploads/slotx/photo.jpg', ['whitespace-upload']));
        $t->same(['invalid-upload' => null], $attributes->attributesForPath('wp-content/uploads/[[:unknown:]]/photo.jpg', ['invalid-upload']));

        $search = PathspecSearch::fromSpecs([':(attr:whitespace-upload)wp-content/uploads/**']);
        $t->same(true, $search->isIncluded("wp-content/uploads/slot\v/photo.jpg", false, $attributes));
        $t->same(false, $search->isIncluded('wp-content/uploads/slotx/photo.jpg', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(
            ':(attr:invalid-upload)wp-content/uploads/**',
            'wp-content/uploads/[[:unknown:]]/photo.jpg',
            false,
            $attributes,
        ));
    },
    'attribute pathspec filters follow gix reversed character range boundaries' => static function (TestRunner $t): void {
        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if (str_contains($message, 'preg_match()')) {
                $warnings[] = $message;

                return true;
            }

            return false;
        });
        try {
            $attributes = GitAttributes::fromString("wp-content/uploads/[z-a]/** reversed-range\n"
                . "wp-content/uploads/[!z-a]/** not-reversed-range\n"
                . "wp-content/uploads/[Z-A]/** folded-reversed-range\n", withBuiltInMacros: false);

            $t->same(['reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/z/photo.jpg', ['reversed-range']));
            $t->same(['reversed-range' => null], $attributes->attributesForPath('wp-content/uploads/a/photo.jpg', ['reversed-range']));
            $t->same(['reversed-range' => null], $attributes->attributesForPath('wp-content/uploads/m/photo.jpg', ['reversed-range']));
            $t->same(['not-reversed-range' => null], $attributes->attributesForPath('wp-content/uploads/z/photo.jpg', ['not-reversed-range']));
            $t->same(['not-reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/a/photo.jpg', ['not-reversed-range']));
            $t->same(['not-reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/m/photo.jpg', ['not-reversed-range']));
            $t->same(['folded-reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/Z/photo.jpg', ['folded-reversed-range']));
            $t->same(['folded-reversed-range' => null], $attributes->attributesForPath('wp-content/uploads/m/photo.jpg', ['folded-reversed-range']));
            $t->same(['folded-reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/m/photo.jpg', ['folded-reversed-range'], false, true));
            $t->same(['folded-reversed-range' => true], $attributes->attributesForPath('wp-content/uploads/a/photo.jpg', ['folded-reversed-range'], false, true));

            $reversedSearch = PathspecSearch::fromSpecs([':(attr:reversed-range)wp-content/uploads/[z-a]/**']);
            $t->same(true, $reversedSearch->isIncluded('wp-content/uploads/z/photo.jpg', false, $attributes));
            $t->same(false, $reversedSearch->isIncluded('wp-content/uploads/a/photo.jpg', false, $attributes));
            $t->same(false, $reversedSearch->isIncluded('wp-content/uploads/m/photo.jpg', false, $attributes));

            $foldedSearch = PathspecSearch::fromSpecs([':(icase)wp-content/uploads/[Z-A]/**']);
            $t->same(true, $foldedSearch->isIncluded('wp-content/uploads/m/photo.jpg', false));
            $t->same(true, PathspecMatcher::matchesOne(':(icase)wp-content/uploads/[Z-A]/**', 'wp-content/uploads/m/photo.jpg', false));
            $t->same(true, PathspecMatcher::matchesOne('wp-content/uploads/[z-a]/**', 'wp-content/uploads/z/photo.jpg', false));
            $t->same(false, PathspecMatcher::matchesOne('wp-content/uploads/[z-a]/**', 'wp-content/uploads/m/photo.jpg', false));
        } finally {
            restore_error_handler();
        }

        $t->same([], $warnings);
    },
    'attribute pathspec filters preserve escaped backslash path bytes like gix wildmatch' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString(
            'wp-content/plugins/f\\\\oo/block.json backslash-plugin' . "\n"
            . 'wp-content/plugins/f/oo/block.json slash-plugin' . "\n",
            withBuiltInMacros: false,
        );
        $backslashPath = 'wp-content/plugins/f\\oo/block.json';
        $slashPath = 'wp-content/plugins/f/oo/block.json';

        $t->same(['backslash-plugin' => true], $attributes->attributesForPath($backslashPath, ['backslash-plugin']));
        $t->same(['backslash-plugin' => null], $attributes->attributesForPath($slashPath, ['backslash-plugin']));
        $t->same(['slash-plugin' => true], $attributes->attributesForPath($slashPath, ['slash-plugin']));
        $t->same(['slash-plugin' => null], $attributes->attributesForPath($backslashPath, ['slash-plugin']));

        $escapedBackslashPathspec = ':(glob,attr:backslash-plugin)wp-content/plugins/f\\\\oo/block.json';
        $t->same(true, PathspecMatcher::matchesOne($escapedBackslashPathspec, $backslashPath, false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne($escapedBackslashPathspec, $slashPath, false, $attributes));
        $t->same(true, PathspecSearch::fromSpecs([$escapedBackslashPathspec])->isIncluded($backslashPath, false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([$escapedBackslashPathspec])->isIncluded($slashPath, false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(glob)wp-content/plugins/f\\\\oo/block.json', $backslashPath, false));
        $t->same(false, PathspecMatcher::matchesOne(':(glob)wp-content/plugins/f\\\\oo/block.json', $slashPath, false));
    },
    'attribute pathspec filters keep path aware double star component local like gix wildmatch' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString(
            "wp-content/plugins/a**f.php component-local\n"
            . "wp-content/plugins/**.php top-level-php\n"
            . "wp-content/plugins/**/block.json recursive-block\n",
            withBuiltInMacros: false,
        );

        $t->same(['component-local' => true], $attributes->attributesForPath('wp-content/plugins/af.php', ['component-local']));
        $t->same(['component-local' => true], $attributes->attributesForPath('wp-content/plugins/axf.php', ['component-local']));
        $t->same(['component-local' => null], $attributes->attributesForPath('wp-content/plugins/a/x/f.php', ['component-local']));
        $t->same(['top-level-php' => true], $attributes->attributesForPath('wp-content/plugins/index.php', ['top-level-php']));
        $t->same(['top-level-php' => null], $attributes->attributesForPath('wp-content/plugins/nested/index.php', ['top-level-php']));
        $t->same(['recursive-block' => true], $attributes->attributesForPath('wp-content/plugins/block.json', ['recursive-block']));
        $t->same(['recursive-block' => true], $attributes->attributesForPath('wp-content/plugins/nested/block.json', ['recursive-block']));
        $t->same(['recursive-block' => null], $attributes->attributesForPath('wp-content/plugins/nested/xblock.json', ['recursive-block']));

        $componentLocal = ':(glob,attr:component-local)wp-content/plugins/a**f.php';
        $topLevelPhp = ':(glob,attr:top-level-php)wp-content/plugins/**.php';
        $recursiveBlock = ':(glob,attr:recursive-block)wp-content/plugins/**/block.json';

        $t->same(true, PathspecMatcher::matchesOne($componentLocal, 'wp-content/plugins/axf.php', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne($componentLocal, 'wp-content/plugins/a/x/f.php', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne($topLevelPhp, 'wp-content/plugins/index.php', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne($topLevelPhp, 'wp-content/plugins/nested/index.php', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne($recursiveBlock, 'wp-content/plugins/block.json', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne($recursiveBlock, 'wp-content/plugins/nested/block.json', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne($recursiveBlock, 'wp-content/plugins/nested/xblock.json', false, $attributes));

        $t->same(true, PathspecSearch::fromSpecs([$componentLocal])->isIncluded('wp-content/plugins/axf.php', false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([$componentLocal])->isIncluded('wp-content/plugins/a/x/f.php', false, $attributes));
        $t->same(true, PathspecSearch::fromSpecs([$topLevelPhp])->isIncluded('wp-content/plugins/index.php', false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([$topLevelPhp])->isIncluded('wp-content/plugins/nested/index.php', false, $attributes));
        $t->same(true, PathspecSearch::fromSpecs([$recursiveBlock])->isIncluded('wp-content/plugins/block.json', false, $attributes));
        $t->same(true, PathspecSearch::fromSpecs([$recursiveBlock])->isIncluded('wp-content/plugins/nested/block.json', false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([$recursiveBlock])->isIncluded('wp-content/plugins/nested/xblock.json', false, $attributes));

        $shellGlob = PathspecSearch::fromSpecs(['wp-content/plugins/**.php']);
        $t->same(true, $shellGlob->isIncluded('wp-content/plugins/nested/index.php', false));
    },
    'pathspec parser accepts upstream attribute magic and escaped values' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/** deploy=plugin kind=one,two\n"
            . "wp-content/themes/** deploy=theme kind=one-two\n"
            . "wp-content/uploads/** -diff binary\n"
            . "wp-content/cache/** !deploy export-ignore\n");

        $t->same(true, PathspecMatcher::matchesOne(':(attr:deploy=plugin kind=one\,two)wp-content/**', 'wp-content/plugins/editor/block.json', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(attr:deploy=plugin kind=one\,two)wp-content/**', 'wp-content/themes/theme.json', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(attr:-diff)wp-content/uploads/**', 'wp-content/uploads/logo.png', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(attr:!deploy)wp-content/uploads/**', 'wp-content/uploads/logo.png', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(attr:!deploy)wp-content/cache/**', 'wp-content/cache/page.html', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(attr:deploy=plugin !review)wp-content/plugins/**', 'wp-content/plugins/editor/block.json', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(attr:!review)wp-content/plugins/**', 'wp-content/plugins/editor/block.json', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(attr:!deploy)wp-content/plugins/**', 'wp-content/plugins/editor/block.json', false, $attributes));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([':(attr:)']));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([':(attr:one,attr:two)path']));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([':(attr:v=inva\#lid)path']));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([':(glob,literal)path']));
    },
    'pathspec search rejects empty long magic components around attr filters like gix pathspec parse' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/** deploy\n", withBuiltInMacros: false);

        $t->same(true, PathspecSearch::fromSpecs([':()wp-content/plugins/**'])
            ->isIncluded('wp-content/plugins/editor/block.json', false, $attributes));
        $t->same(true, PathspecMatcher::fromSpecs([':()wp-content/plugins/**'])
            ->matches('wp-content/plugins/editor/block.json', false, $attributes));

        foreach ([
            ':(attr:deploy,)wp-content/plugins/**',
            ':(,attr:deploy)wp-content/plugins/**',
            ':(attr:deploy,,icase)wp-content/plugins/**',
        ] as $spec) {
            $t->throws(InvalidArgumentException::class, static fn () => PathspecSearch::fromSpecs([$spec]));
            $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([$spec]));
        }
    },
    'pathspec search rejects unimplemented short magic before attr filters like gix pathspec parse' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/** deploy\n", withBuiltInMacros: false);

        $t->same(true, PathspecSearch::fromSpecs([':!:(attr:deploy)wp-content/plugins/**'])
            ->match('wp-content/plugins/editor/block.json', false, $attributes)?->isExcluded());
        $t->same(true, PathspecSearch::fromSpecs([':^:(attr:deploy)wp-content/plugins/**'])
            ->match('wp-content/plugins/editor/block.json', false, $attributes)?->isExcluded());

        foreach ([
            ':;:(attr:deploy)wp-content/plugins/**',
            ':-:(attr:deploy)wp-content/plugins/**',
            ':@:(attr:deploy)wp-content/plugins/**',
        ] as $spec) {
            $t->throws(InvalidArgumentException::class, static fn () => PathspecSearch::fromSpecs([$spec]));
            $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([$spec]));
        }
    },
    'pathspec attribute values reject tab bytes after value chunks like gix pathspec parse' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/** deploy review=yes\n", withBuiltInMacros: false);

        $t->same(true, PathspecMatcher::matchesOne(
            ":(attr:deploy\treview=yes)wp-content/plugins/**",
            'wp-content/plugins/editor/block.json',
            false,
            $attributes,
        ));
        $t->same(true, PathspecSearch::fromSpecs([":(attr:deploy\treview=yes)wp-content/plugins/**"])
            ->isIncluded('wp-content/plugins/editor/block.json', false, $attributes));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([
            ":(attr:deploy=plugin\treview=yes)wp-content/plugins/**",
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecSearch::fromSpecs([
            ":(attr:deploy=plugin\treview=yes)wp-content/plugins/**",
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([
            ":(attr:deploy=plugin\treview)wp-content/plugins/**",
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecSearch::fromSpecs([
            ":(attr:deploy=plugin\treview)wp-content/plugins/**",
        ]));
    },
    'attribute and pathspec state adjustments ignore value suffixes like gix attributes' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/mu-plugins/** deploy=mustuse -diff=legacy !review=stale\n"
            . "wp-content/mu-plugins/private/** !deploy=old -merge=ours\n");

        $t->same([
            'deploy' => 'mustuse',
            'diff' => false,
            'review' => null,
        ], $attributes->attributesForPath('wp-content/mu-plugins/loader.php', ['deploy', 'diff', 'review']));
        $t->same([
            'deploy' => null,
            'merge' => false,
            'review' => null,
        ], $attributes->attributesForPath('wp-content/mu-plugins/private/secret.php', ['deploy', 'merge', 'review']));
        $t->same(true, PathspecMatcher::matchesOne(':(attr:-diff=legacy !review=stale)wp-content/mu-plugins/**', 'wp-content/mu-plugins/loader.php', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(attr:!deploy=old -merge=ours)wp-content/mu-plugins/private/**', 'wp-content/mu-plugins/private/secret.php', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(attr:!deploy=old)wp-content/mu-plugins/**', 'wp-content/mu-plugins/loader.php', false, $attributes));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecMatcher::fromSpecs([':(attr:-diff=inva\#lid)path']));
    },
    'pathspec search carries attr filters into upstream style matching' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/** deploy=plugin merge=union\n"
            . "wp-content/plugins/private/** !deploy\n"
            . "wp-content/themes/** deploy=theme merge=union\n"
            . "wp-content/uploads/** binary -diff\n");

        $pluginSearch = PathspecSearch::fromSpecs([':(attr:deploy=plugin)wp-content/**']);
        $t->same([
            ['name' => 'deploy', 'state' => GitAttributes::STATE_VALUE, 'value' => 'plugin'],
        ], $pluginSearch->patterns()[0]->attributes);
        $t->same(null, $pluginSearch->match('wp-content/plugins/editor/block.json', false));
        $pluginMatch = $pluginSearch->match('wp-content/plugins/editor/block.json', false, $attributes);
        $t->same(PathspecMatch::KIND_WILDCARD, $pluginMatch?->kind);
        $t->same(true, $pluginSearch->isIncluded('wp-content/plugins/editor/block.json', false, $attributes));
        $t->same(false, $pluginSearch->isIncluded('wp-content/themes/twentytwentyfour/theme.json', false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([':(attr:deploy)wp-content/plugins/**'])
            ->isIncluded('wp-content/plugins/editor/block.json', false, $attributes));

        $t->same(true, PathspecSearch::fromSpecs([':(attr:-diff)wp-content/uploads/**'])
            ->isIncluded('wp-content/uploads/logo.png', false, $attributes));
        $t->same(true, PathspecSearch::fromSpecs([':(attr:!deploy)wp-content/plugins/private/**'])
            ->isIncluded('wp-content/plugins/private/secret.php', false, $attributes));
        $t->same(false, PathspecSearch::fromSpecs([':(attr:!deploy)wp-content/uploads/**'])
            ->isIncluded('wp-content/uploads/logo.png', false, $attributes));

        $deployment = PathspecSearch::fromSpecs([
            'wp-content/**',
            ':!:(attr:!deploy)wp-content/plugins/private/**',
        ]);
        $excluded = $deployment->match('wp-content/plugins/private/secret.php', false, $attributes);
        $t->same(true, $excluded?->isExcluded());
        $t->same(false, $deployment->isIncluded('wp-content/plugins/private/secret.php', false, $attributes));
        $t->same(true, $deployment->isIncluded('wp-content/uploads/logo.png', false, $attributes));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecSearch::fromSpecs([':(attr:one,attr:two)path']));
    },
    'combined attribute sources apply nested precedence to attr pathspec matches like gix search' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromSources([
            [
                'contents' => "wp-content/** deploy=root review=pending\n"
                    . "wp-content/cache/** export-ignore\n"
                    . "[attr]root-binary binary root-macro\n"
                    . "wp-content/uploads/** root-binary\n",
            ],
            [
                'baseDirectory' => 'wp-content/plugins',
                'contents' => "gutenberg/** deploy=plugin merge=union local-macro\n"
                    . "gutenberg/build/** -deploy\n"
                    . "private/** !deploy\n"
                    . "[attr]local-macro nested-macro\n",
                'allowMacros' => false,
            ],
            [
                'baseDirectory' => 'wp-content/themes/acme',
                'contents' => "style.css deploy=theme\n",
            ],
        ]);

        $t->same([
            'deploy' => 'plugin',
            'merge' => 'union',
            'review' => 'pending',
        ], $attributes->attributesForPath('wp-content/plugins/gutenberg/block.json', ['deploy', 'merge', 'review']));
        $t->same([
            'deploy' => false,
            'merge' => 'union',
        ], $attributes->attributesForPath('wp-content/plugins/gutenberg/build/index.js', ['deploy', 'merge']));
        $t->same(['deploy' => null], $attributes->attributesForPath('wp-content/plugins/private/secret.php', ['deploy']));
        $t->same(['deploy' => 'theme'], $attributes->attributesForPath('wp-content/themes/acme/style.css', ['deploy']));
        $t->same([
            'diff' => false,
            'merge' => false,
            'root-macro' => true,
            'text' => false,
        ], $attributes->attributesForPath('wp-content/uploads/logo.png', ['diff', 'merge', 'root-macro', 'text']));
        $t->same([
            'local-macro' => true,
            'nested-macro' => null,
        ], $attributes->attributesForPath('wp-content/plugins/gutenberg/block.json', ['local-macro', 'nested-macro']));

        $search = PathspecSearch::fromSpecs([
            ':(attr:deploy=plugin)wp-content/plugins/**',
            ':(attr:deploy=theme)wp-content/themes/**',
            ':!:(attr:-deploy)wp-content/plugins/gutenberg/build/**',
            ':!:(attr:export-ignore)wp-content/cache/**',
        ]);
        $paths = [
            'wp-content/cache/page.html' => false,
            'wp-content/plugins/gutenberg/block.json' => false,
            'wp-content/plugins/gutenberg/build/index.js' => false,
            'wp-content/plugins/private/secret.php' => false,
            'wp-content/themes/acme/style.css' => false,
            'wp-content/uploads/logo.png' => false,
        ];

        $included = [];
        foreach ($paths as $path => $isDirectory) {
            if ($search->isIncluded($path, $isDirectory, $attributes)) {
                $included[] = $path;
            }
        }
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/themes/acme/style.css',
        ], $included);

        $matcher = PathspecMatcher::fromSpecs([
            ':(attr:deploy=plugin)wp-content/plugins/**',
            ':(attr:deploy=theme)wp-content/themes/**',
            ':!:(attr:-deploy)wp-content/plugins/gutenberg/build/**',
        ]);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/themes/acme/style.css',
        ], $matcher->matchingPaths($paths, $attributes));
    },
    'pathspec search applies exclude first directory prefixes icase and attr filters' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("wp-content/plugins/gutenberg/** deploy=plugin merge=union\n"
            . "wp-content/plugins/gutenberg/build/** -deploy\n"
            . "wp-content/themes/twentytwentyfour/** deploy=theme merge=union\n"
            . "wp-content/cache/** export-ignore\n");
        $matcher = PathspecMatcher::fromSpecs([
            ':(attr:merge=union)wp-content/**',
            ':!wp-content/cache/**',
            ':!wp-content/plugins/gutenberg/build/**',
        ]);

        $t->same(true, $matcher->matches('wp-content/plugins/gutenberg/block.json', false, $attributes));
        $t->same(true, $matcher->matches('wp-content/themes/twentytwentyfour/theme.json', false, $attributes));
        $t->same(false, $matcher->matches('wp-content/plugins/gutenberg/build/index.js', false, $attributes));
        $t->same(false, $matcher->matches('wp-content/cache/page.html', false, $attributes));
        $t->same(false, $matcher->matches('wp-content/uploads/logo.png', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne('wp-content/plugins/gutenberg', 'wp-content/plugins/gutenberg/block.json', false, $attributes));
        $t->same(true, PathspecMatcher::matchesOne(':(icase)WP-CONTENT/PLUGINS/GUTENBERG/**', 'wp-content/plugins/gutenberg/block.json', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(':(icase,attr:deploy=plugin)WP-CONTENT/PLUGINS/GUTENBERG/**', 'WP-CONTENT/plugins/gutenberg/block.json', false, $attributes));
        $t->same(true, PathspecMatcher::fromSpecs([':!wp-content/cache/**'])->matches('wp-content/plugins/gutenberg/block.json', false, $attributes));
    },
    'recursive attribute macros follow gix lookup order for attr pathspec matches' => static function (TestRunner $t): void {
        $attributes = GitAttributes::fromString("[attr]my-text text\n"
            . "[attr]my-binary binary\n"
            . "[attr]b-cycle a-cycle my-text\n"
            . "[attr]a-cycle b-cycle my-binary\n"
            . "[attr]recursive recursively-assigned-attr\n"
            . "[attr]my-binary binary macro-overridden recursive\n"
            . "wp-content/** other a-cycle\n"
            . "wp-content/** -other b-cycle\n");

        $t->same([
            'a-cycle' => true,
            'b-cycle' => true,
            'binary' => true,
            'diff' => false,
            'macro-overridden' => true,
            'merge' => false,
            'my-binary' => true,
            'my-text' => true,
            'other' => false,
            'recursive' => true,
            'recursively-assigned-attr' => true,
            'text' => true,
        ], $attributes->attributesForPath('wp-content/plugins/editor/block.php', [
            'binary',
            'diff',
            'merge',
            'text',
            'my-text',
            'my-binary',
            'b-cycle',
            'a-cycle',
            'recursive',
            'recursively-assigned-attr',
            'macro-overridden',
            'other',
        ]));
        $t->same(true, PathspecMatcher::matchesOne(
            ':(attr:text my-binary recursive macro-overridden -other)wp-content/**',
            'wp-content/plugins/editor/block.php',
            false,
            $attributes,
        ));
        $t->same(true, PathspecSearch::fromSpecs([
            ':(attr:text recursively-assigned-attr -other)wp-content/**',
        ])->isIncluded('wp-content/plugins/editor/block.php', false, $attributes));
        $t->same(false, PathspecMatcher::matchesOne(
            ':(attr:-text)wp-content/**',
            'wp-content/plugins/editor/block.php',
            false,
            $attributes,
        ));
        $t->same(false, PathspecMatcher::matchesOne(
            ':(attr:other)wp-content/**',
            'wp-content/plugins/editor/block.php',
            false,
            $attributes,
        ));
    },
    'wordpress attributes pathspec fixture selects deployable merge aware content' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-attributes-pathspec.php';
        $attributes = GitAttributes::fromString($fixture['attributes']);
        $matcher = PathspecMatcher::fromSpecs($fixture['deploymentPathspecs']);
        $example = require dirname(__DIR__) . '/examples/wordpress-attributes-pathspec.php';

        $t->same([
            'wp-content/mu-plugins/loader.php',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/themes/twentytwentyfour/theme.json',
            'wp-content/uploads/logo.png',
        ], $matcher->matchingPaths($fixture['paths'], $attributes));
        $t->same($matcher->matchingPaths($fixture['paths'], $attributes), $example['selectedForDeployment']);
        $t->same($example['selectedForDeployment'], $example['searchSelectedForDeployment']);
        $t->same(['deploy' => 'plugin', 'diff' => null, 'merge' => 'union'], $example['pluginBlockAttributes']);
        $t->same(['binary' => true, 'diff' => false, 'merge' => false, 'text' => false], $example['uploadAttributes']);
        $t->same(['deploy' => 'mustuse', 'diff' => false, 'merge' => 'union'], $example['mustUsePluginAttributes']);
        $t->same(PathspecMatch::KIND_WILDCARD, $example['pluginPathspecSearchKind']);
        $t->same(true, $example['explicitDeployUnspecifiedMatches']);
        $t->same(false, $example['absentDeployUnspecifiedMatches']);
        $t->same(['dated-upload' => true], $example['datedUploadAttributes']);
        $t->same(true, $example['datedUploadPathspecMatches']);
        $t->same(true, $example['slashClassDoesNotCrossDirectory']);
        $t->same(['backslash-plugin' => true], $example['backslashPathAttributes']);
        $t->same(['backslash-plugin' => null], $example['slashPathDoesNotMatchBackslashAttribute']);
        $t->same(true, $example['backslashPathspecMatchesByte']);
        $t->same(true, $example['backslashPathspecSkipsSlash']);
        $t->same(true, $example['reversedRangePathspecMatchesStart']);
        $t->same(true, $example['reversedRangePathspecSkipsMiddle']);
        $t->same(['not-reversed-range' => true], $example['reversedRangeNegationMatchesMiddle']);
        $t->same(['folded-reversed-range' => true], $example['foldedReversedRangeAttributeMatchesMiddle']);
        $t->same(true, $example['foldedReversedRangePathspecMatchesMiddle']);
        $t->same(true, $example['tabSeparatedStatePathspecMatches']);
        $t->same(true, $example['valueTabRequirementRejected']);
        $t->same(true, $example['emptyLongMagicComponentRejected']);
        $t->same(true, $example['unimplementedShortMagicRejected']);
        $t->same(true, $example['recursiveMacroPathspecMatches']);
        $t->same([
            'macro-overridden' => true,
            'other' => false,
            'recursively-assigned-attr' => true,
            'text' => true,
        ], $example['recursiveMacroAttributes']);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/themes/twentytwentyfour/theme.json',
        ], $example['nestedSelectedForDeployment']);
        $t->same([
            'deploy' => 'plugin',
            'merge' => 'union',
            'review' => 'pending',
        ], $example['nestedPluginAttributes']);
        $t->same(true, $example['nestedBuildExcluded']);
        $t->same(['deploy' => 'theme', 'merge' => 'union'], $example['nestedThemeAttributes']);
        $t->same(['local-macro' => true, 'nested-macro' => null], $example['nestedLocalMacroDefinitionIgnored']);
        $t->same(true, $example['cacheExcluded']);
        $t->same(true, $example['buildExcludedByPathspec']);
    },
];
