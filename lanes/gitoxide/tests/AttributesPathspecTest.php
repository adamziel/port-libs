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
        $t->same(true, $example['cacheExcluded']);
        $t->same(true, $example['buildExcludedByPathspec']);
    },
];
