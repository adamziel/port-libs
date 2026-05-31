<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PathspecMatch;
use PortLibs\Gitoxide\PathspecPattern;
use PortLibs\Gitoxide\PathspecSearch;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreePathspecWalk;
use PortLibs\Gitoxide\TreeWalkEntry;

$blobOid = str_repeat('1', 40);

$makeTreeStore = static function () use ($blobOid): array {
    $objects = [];
    $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
    $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
        $object = $tree->toObject();
        $objects[$object->oid()] = $object;

        return new TreeEntry('040000', $name, $object->oid());
    };

    $gutenberg = new Tree([
        $blob('block.json'),
        $blob('readme.txt'),
        $tree('build', new Tree([$blob('index.js')])),
        $tree('src', new Tree([$blob('editor.js')])),
    ]);
    $plugins = new Tree([
        $tree('akismet', new Tree([$blob('akismet.php')])),
        $tree('gutenberg', $gutenberg),
    ]);
    $themes = new Tree([
        $tree('acme', new Tree([$blob('theme.json'), $blob('style.css')])),
        $tree('twentytwentyfive', new Tree([$blob('style.css')])),
    ]);
    $muPlugins = new Tree([
        $blob('Loader.PHP'),
    ]);
    $uploads = new Tree([
        $tree('2026', new Tree([$blob('[hero].jpg')])),
    ]);
    $wpContent = new Tree([
        $blob('index.php'),
        $tree('mu-plugins', $muPlugins),
        $tree('plugins', $plugins),
        $tree('themes', $themes),
        $tree('uploads', $uploads),
    ]);
    $root = new Tree([
        $blob('index.php'),
        $tree('wp-admin', new Tree([$blob('admin.php')])),
        $tree('wp-content', $wpContent),
    ]);

    $read = static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
        if (!isset($objects[$entry->oid])) {
            throw new RuntimeException("Missing tree object for {$path}");
        }

        return $objects[$entry->oid];
    };

    return [$root, $read];
};

$walkPaths = static fn (array $records): array => array_map(
    static fn (TreeWalkEntry $record): string => $record->path,
    $records
);

return [
    'parses upstream pathspec magic keywords and nil pathspecs' => static function (TestRunner $t): void {
        $nil = PathspecPattern::parse(':');
        $t->same(true, $nil->nil);
        $t->same(true, $nil->alwaysMatches());

        $pattern = PathspecPattern::parse(':/!:(literal,icase)wp-content/[literal]/');
        $t->same('wp-content/[literal]', $pattern->path);
        $t->same(true, $pattern->top);
        $t->same(true, $pattern->exclude);
        $t->same(true, $pattern->ignoreCase);
        $t->same(true, $pattern->mustBeDirectory);
        $t->same(PathspecPattern::SEARCH_LITERAL, $pattern->searchMode);

        $t->same('some/path', PathspecPattern::parse('::some/path')->path);
        $t->same(':some/path', PathspecPattern::parse(':::some/path')->path);
        $t->same(' some/path ', PathspecPattern::parse(': some/path ')->path);
        $t->throws(InvalidArgumentException::class, static fn () => PathspecPattern::parse(''));
        $t->throws(InvalidArgumentException::class, static fn () => PathspecPattern::parse(':(literal,glob)x'));
    },
    'matches shell glob glob magic literal and icase pathspecs' => static function (TestRunner $t): void {
        $shell = PathspecSearch::fromSpecs(['wp-content/*']);
        $t->same(true, $shell->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same(PathspecMatch::KIND_WILDCARD, $shell->match('wp-content/plugins/gutenberg/block.json', false)?->kind);

        $glob = PathspecSearch::fromSpecs([':(glob)wp-content/*']);
        $t->same(true, $glob->isIncluded('wp-content/plugins', true));
        $t->same(false, $glob->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, PathspecSearch::fromSpecs([':(glob)wp-content/**/block*.json'])
            ->isIncluded('wp-content/plugins/gutenberg/block.json', false));

        $literal = PathspecSearch::fromSpecs([':(literal)wp-content/uploads/2026/[hero].jpg']);
        $t->same(true, $literal->isIncluded('wp-content/uploads/2026/[hero].jpg', false));
        $t->same(false, $literal->isIncluded('wp-content/uploads/2026/h.jpg', false));

        $icase = PathspecSearch::fromSpecs([':(icase)WP-CONTENT/MU-PLUGINS/*.PHP']);
        $t->same(true, $icase->isIncluded('wp-content/mu-plugins/Loader.PHP', false));
    },
    'matches directory-only pathspecs as verbatim and prefix matches' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['wp-content/plugins/gutenberg/']);

        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/gutenberg', true)?->kind);
        $t->same(null, $search->match('wp-content/plugins/gutenberg', false));
        $t->same(PathspecMatch::KIND_PREFIX, $search->match('wp-content/plugins/gutenberg/block.json', false)?->kind);
        $t->same(false, $search->isIncluded('wp-content/plugins/akismet/akismet.php', false));
    },
    'applies exclude pathspecs before includes and all-excluded fallback' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['wp-content/plugins/gutenberg/', ':!wp-content/plugins/gutenberg/build/']);

        $excluded = $search->match('wp-content/plugins/gutenberg/build/index.js', false);
        $t->same(true, $excluded?->isExcluded());
        $t->same(PathspecMatch::KIND_PREFIX, $excluded?->kind);
        $t->same(false, $search->isIncluded('wp-content/plugins/gutenberg/build/index.js', false));
        $t->same(true, $search->isIncluded('wp-content/plugins/gutenberg/src/editor.js', false));

        $allExcluded = PathspecSearch::fromSpecs([':!wp-content/cache/**']);
        $t->same(false, $allExcluded->isIncluded('wp-content/cache/page.html', false));
        $t->same(true, $allExcluded->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same(PathspecMatch::KIND_ALWAYS, $allExcluded->match('wp-content/plugins/gutenberg/block.json', false)?->kind);
    },
    'maps upstream can-match and directory-prefix pruning cases' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['dir/*']);
        $t->same(false, $search->canMatch('a', null));
        $t->same(false, $search->canMatch('di', null));
        $t->same(true, $search->canMatch('dir', true));
        $t->same(true, $search->canMatch('dir/file', false));
        $t->same(true, $search->directoryMatchesPrefix('dir', false));
        $t->same(false, $search->directoryMatchesPrefix('ab', false));

        $mustBeDir = PathspecSearch::fromSpecs(['a/be/']);
        $t->same(false, $mustBeDir->canMatch('a', false));
        $t->same(true, $mustBeDir->canMatch('a', true));
        $t->same(false, $mustBeDir->canMatch('a/be', false));
        $t->same(true, $mustBeDir->canMatch('a/be', true));
        $t->same(true, $mustBeDir->canMatch('a/be/file', false));

        $leading = PathspecSearch::fromSpecs(['d/d/generated/b']);
        $t->same(false, $leading->directoryMatchesPrefix('d', false));
        $t->same(true, $leading->directoryMatchesPrefix('d', true));
        $t->same(true, $leading->directoryMatchesPrefix('d/d/generated', true));
        $t->same(false, $leading->directoryMatchesPrefix('d/d/generatedfoo', true));
    },
    'keeps caller prefixes case sensitive under icase pathspecs' => static function (TestRunner $t): void {
        $noCallerPrefix = PathspecSearch::fromSpecs(['foo/bar', 'foo']);
        $t->same('foo', $noCallerPrefix->commonPrefix());
        $t->same('', $noCallerPrefix->prefixDirectory());

        $search = PathspecSearch::fromSpecs([':(icase)bar'], 'FOO');

        $t->same('FOO', $search->commonPrefix());
        $t->same('FOO', $search->prefixDirectory());
        $t->same('FOO', $search->longestCommonDirectory());
        $t->same(true, $search->isIncluded('FOO/BAR', false));
        $t->same(true, $search->isIncluded('FOO/bAr', false));
        $t->same(false, $search->isIncluded('foo/BAR', false));
        $t->same(false, $search->canMatch('foo', true));
        $t->same(true, $search->canMatch('FOO', true));
        $t->same(false, $search->canMatch('FOO/ba', true));
        $t->same(true, $search->canMatch('FOO/bar', true));
        $t->same(false, $search->directoryMatchesPrefix('foo', true));
        $t->same(true, $search->directoryMatchesPrefix('FOO', true));

        $escaped = PathspecSearch::fromSpecs([':(icase)../bar'], 'fOo');
        $t->same('', $escaped->commonPrefix());
        $t->same('', $escaped->prefixDirectory());
        $t->same(true, $escaped->isIncluded('BAR', false));
        $t->same(false, $escaped->isIncluded('fOo/BAR', false));
    },
    'tree walk respects case-sensitive normalized prefixes' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('WP-CONTENT', new Tree([
                $tree('plugins', new Tree([$blob('Plugin.PHP')])),
            ])),
            $tree('wp-content', new Tree([
                $tree('plugins', new Tree([$blob('plugin.php')])),
            ])),
        ]);
        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([':(icase)plugins/*.php'], 'WP-CONTENT'),
            static function (TreeEntry $entry, string $path) use (&$objects, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same(['WP-CONTENT/plugins/Plugin.PHP'], $walkPaths($records));
        $t->same(['WP-CONTENT', 'WP-CONTENT/plugins'], $readPaths);
    },
    'walks trees breadth first with pathspec matches and subtree pruning' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $readPaths = [];
        $search = PathspecSearch::fromSpecs([
            'wp-content/plugins/gutenberg/',
            ':(glob)wp-content/themes/*/theme.json',
            ':!wp-content/plugins/gutenberg/build/',
            ':(icase)WP-CONTENT/MU-PLUGINS/*.PHP',
            ':(literal)wp-content/uploads/2026/[hero].jpg',
        ]);

        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use ($read, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $read($entry, $path);
            },
        );

        $t->same([
            'wp-content',
            'wp-content/mu-plugins',
            'wp-content/plugins',
            'wp-content/themes',
            'wp-content/uploads',
            'wp-content/mu-plugins/Loader.PHP',
            'wp-content/plugins/gutenberg',
            'wp-content/themes/acme',
            'wp-content/uploads/2026',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/plugins/gutenberg/src',
            'wp-content/themes/acme/theme.json',
            'wp-content/uploads/2026/[hero].jpg',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/mu-plugins',
            'wp-content/plugins',
            'wp-content/themes',
            'wp-content/uploads',
            'wp-content/plugins/gutenberg',
            'wp-content/themes/acme',
            'wp-content/themes/twentytwentyfive',
            'wp-content/uploads/2026',
            'wp-content/plugins/gutenberg/src',
        ], $readPaths);
    },
    'walk can emit only non-tree materialization paths' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs(['wp-content/plugins/gutenberg/', ':!wp-content/plugins/gutenberg/build/']),
            $read,
            includeTrees: false,
        );

        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $walkPaths($records));
    },
    'empty pathspecs with prefix walk only prefixed subtrees' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([], 'wp-content/themes'),
            $read,
            includeTrees: false,
        );

        $search = PathspecSearch::fromSpecs([], 'wp-content/themes');
        $t->same('wp-content/themes', $search->prefixDirectory());
        $t->same('wp-content/themes', $search->longestCommonDirectory());
        $t->same([
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/style.css',
            'wp-content/themes/twentytwentyfive/style.css',
        ], $walkPaths($records));
    },
    'empty pathspecs without prefix match and walk every entry' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $search = PathspecSearch::fromSpecs([]);

        $match = $search->match('wp-content/plugins/gutenberg/block.json', false);
        $t->same(PathspecMatch::KIND_ALWAYS, $match?->kind);
        $t->same(0, $match?->sequenceNumber);
        $t->same(true, $search->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same(true, $search->canMatch('anything', null));
        $t->same(true, $search->directoryMatchesPrefix('anything', false));

        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            $read,
            includeTrees: false,
        );

        $t->same([
            'index.php',
            'wp-admin/admin.php',
            'wp-content/index.php',
            'wp-content/mu-plugins/Loader.PHP',
            'wp-content/plugins/akismet/akismet.php',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/style.css',
            'wp-content/themes/twentytwentyfive/style.css',
            'wp-content/uploads/2026/[hero].jpg',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $walkPaths($records));
    },
];
