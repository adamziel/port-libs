<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitAttributes;
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
    'applies upstream default search modes and inherited icase during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $tree('plugins', new Tree([
                    $blob('*.php'),
                    $blob('gutenberg.php'),
                    $blob('Gutenberg.PHP'),
                    $tree('nested', new Tree([$blob('plugin.php')])),
                ])),
            ])),
            $tree('WP-CONTENT', new Tree([
                $tree('plugins', new Tree([$blob('Plugin.PHP')])),
            ])),
            $blob(':'),
            $blob('index.php'),
        ]);
        $read = static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
            if (!isset($objects[$entry->oid])) {
                throw new RuntimeException("Missing tree object for {$path}");
            }

            return $objects[$entry->oid];
        };

        $literal = PathspecSearch::fromSpecs(
            ['wp-content/plugins/*.php'],
            defaultSearchMode: PathspecPattern::SEARCH_LITERAL,
        );
        $t->same(PathspecPattern::SEARCH_LITERAL, $literal->patterns()[0]->searchMode);
        $t->same(true, $literal->isIncluded('wp-content/plugins/*.php', false));
        $t->same(false, $literal->isIncluded('wp-content/plugins/gutenberg.php', false));
        $t->same(['wp-content/plugins/*.php'], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $literal,
            $read,
            includeTrees: false,
        )));

        $pathAware = PathspecSearch::fromSpecs(
            ['wp-content/plugins/*.php'],
            defaultSearchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB,
        );
        $t->same(true, $pathAware->isIncluded('wp-content/plugins/gutenberg.php', false));
        $t->same(false, $pathAware->isIncluded('wp-content/plugins/nested/plugin.php', false));
        $t->same([
            'wp-content/plugins/*.php',
            'wp-content/plugins/gutenberg.php',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $pathAware,
            $read,
            includeTrees: false,
        )));

        $globOverridesNoGlob = PathspecSearch::fromSpecs(
            [':(glob)wp-content/plugins/*.php'],
            defaultSearchMode: PathspecPattern::SEARCH_LITERAL,
        );
        $t->same(PathspecPattern::SEARCH_PATH_AWARE_GLOB, $globOverridesNoGlob->patterns()[0]->searchMode);
        $t->same(true, $globOverridesNoGlob->isIncluded('wp-content/plugins/gutenberg.php', false));
        $t->same(false, $globOverridesNoGlob->isIncluded('wp-content/plugins/nested/plugin.php', false));

        $literalDefault = PathspecSearch::fromSpecs([':'], literalDefault: true);
        $t->same(false, $literalDefault->patterns()[0]->nil);
        $t->same([':'], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $literalDefault,
            $read,
            includeTrees: false,
        )));

        $inheritedIcase = PathspecSearch::fromSpecs(
            ['plugins/*.php'],
            'WP-CONTENT',
            defaultIgnoreCase: true,
        );
        $readPaths = [];
        $t->same('WP-CONTENT', $inheritedIcase->commonPrefix());
        $t->same(true, $inheritedIcase->isIncluded('WP-CONTENT/plugins/plugin.php', false));
        $t->same(false, $inheritedIcase->isIncluded('wp-content/plugins/Plugin.PHP', false));
        $t->same(['WP-CONTENT/plugins/Plugin.PHP'], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $inheritedIcase,
            static function (TreeEntry $entry, string $path) use ($read, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $read($entry, $path);
            },
            includeTrees: false,
        )));
        $t->same(['WP-CONTENT', 'WP-CONTENT/plugins'], $readPaths);

        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs(['wp-content/**'], defaultSearchMode: 'unsupported'),
        );
    },
    'matches upstream wildmatch brackets escapes and recursive directory globs during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $pathAwareSlashClass = PathspecSearch::fromSpecs([':(glob)wp-content/plugins/foo[/]bar.php']);
        $shellSlashClass = PathspecSearch::fromSpecs(['wp-content/plugins/foo[/]bar.php']);
        $digitClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/2026/[[:digit:]][[:digit:]]/photo.jpg']);

        $t->same(false, $pathAwareSlashClass->isIncluded('wp-content/plugins/foo/bar.php', false));
        $t->same(true, $shellSlashClass->isIncluded('wp-content/plugins/foo/bar.php', false));
        $t->same(true, $digitClass->isIncluded('wp-content/uploads/2026/05/photo.jpg', false));
        $t->same(false, $digitClass->isIncluded('wp-content/uploads/2026/ab/photo.jpg', false));

        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $blob('theme.?son'),
                $tree('plugins', new Tree([
                    $tree('akismet', new Tree([$blob('block.json')])),
                    $tree('gutenberg', new Tree([$blob('block.json'), $blob('block.gson')])),
                    $tree('cache', new Tree([$blob('block.json')])),
                    $tree('[literal]', new Tree([$blob('block.?son')])),
                    $tree('aliteral', new Tree([$blob('block.?son')])),
                ])),
                $tree('themes', new Tree([
                    $tree('site', new Tree([$blob('theme.?son'), $blob('theme.json')])),
                ])),
                $tree('uploads', new Tree([
                    $tree('2026', new Tree([
                        $tree('05', new Tree([$blob('photo.jpg')])),
                        $tree('02', new Tree([$blob('photo.jpg')])),
                    ])),
                ])),
            ])),
        ]);
        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([
                ':(glob)wp-content/plugins/[ag]*/block.[jt]son',
                ':(glob)wp-content/uploads/2026/0[!1-4]/**',
                ':(glob)wp-content/**/theme.\?son',
                ':(glob)wp-content/plugins/\[literal\]/block.\?son',
            ]),
            static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            'wp-content/theme.?son',
            'wp-content/plugins/akismet/block.json',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/[literal]/block.?son',
            'wp-content/themes/site/theme.?son',
            'wp-content/uploads/2026/05/photo.jpg',
        ], $walkPaths($records));
    },
    'matches gix wildmatch POSIX blank and invalid class boundaries during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $blankClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg']);
        $spaceClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/slot[[:space:]]/photo.jpg']);
        $invalidClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/[[:unknown:]]/photo.jpg']);

        $t->same(true, $blankClass->isIncluded("wp-content/uploads/slot\v/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded('wp-content/uploads/slot /photo.jpg', false));
        $t->same(false, $spaceClass->isIncluded("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(true, $spaceClass->isIncluded('wp-content/uploads/slot /photo.jpg', false));
        $t->same(true, $invalidClass->isIncluded('wp-content/uploads/[[:unknown:]]/photo.jpg', false));
        $t->same(false, $invalidClass->isIncluded('wp-content/uploads/unknown/photo.jpg', false));

        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $tree('uploads', new Tree([
                    $tree("slot\v", new Tree([$blob('photo.jpg')])),
                    $tree('slot ', new Tree([$blob('photo.jpg')])),
                    $tree('[[:unknown:]]', new Tree([$blob('photo.jpg')])),
                ])),
            ])),
        ]);

        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([
                ':(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg',
                ':(glob)wp-content/uploads/[[:unknown:]]/photo.jpg',
            ]),
            static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            "wp-content/uploads/slot\v/photo.jpg",
            'wp-content/uploads/slot /photo.jpg',
            'wp-content/uploads/[[:unknown:]]/photo.jpg',
        ], $walkPaths($records));
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
    'exclude nil pathspec prunes tree walks like upstream simplified search' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $search = PathspecSearch::fromSpecs([':(exclude)']);

        $match = $search->match('wp-content/plugins/gutenberg/block.json', false);
        $t->same(true, $match?->isExcluded());
        $t->same(PathspecMatch::KIND_ALWAYS, $match?->kind);
        $t->same(false, $search->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same(false, $search->canMatch('wp-content', null));
        $t->same(false, $search->canMatch('wp-content', false));
        $t->same(false, $search->canMatch('wp-content', true));
        $t->same(false, $search->directoryMatchesPrefix('wp-content', false));
        $t->same(false, $search->directoryMatchesPrefix('wp-content', true));

        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use ($read, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $read($entry, $path);
            },
            includeTrees: false,
        );

        $t->same([], $walkPaths($records));
        $t->same([], $readPaths);

        $specificExcludes = PathspecSearch::fromSpecs([
            ':(exclude)wp-content/cache/page.html',
            ':(exclude)wp-content/uploads/private.php',
        ]);
        $t->same(true, $specificExcludes->canMatch('wp-content', true));
        $t->same(true, $specificExcludes->directoryMatchesPrefix('wp-content', true));
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

        $wildcardFiles = PathspecSearch::fromSpecs([
            'wp-content/plugins/gutenberg/*.json',
            'wp-content/plugins/gutenberg/*.gson',
        ]);
        $t->same('wp-content/plugins/gutenberg', $wildcardFiles->longestCommonDirectory());
        $t->same('wp-content/plugins', PathspecSearch::fromSpecs([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/akismet/block.json',
        ])->longestCommonDirectory());
        $t->same(null, PathspecSearch::fromSpecs(['foo', 'fob'])->longestCommonDirectory());
        $t->same('wp-content/plugins/gutenberg', PathspecSearch::fromSpecs([
            'wp-content/plugins/gutenberg/',
        ])->longestCommonDirectory());
    },
    'keeps caller prefixes case sensitive under icase pathspecs' => static function (TestRunner $t): void {
        $noCallerPrefix = PathspecSearch::fromSpecs(['foo/bar', 'foo']);
        $t->same('foo', $noCallerPrefix->commonPrefix());
        $t->same('', $noCallerPrefix->prefixDirectory());

        $search = PathspecSearch::fromSpecs([':(icase)bar'], 'FOO');

        $t->same('FOO', $search->commonPrefix());
        $t->same('FOO', $search->prefixDirectory());
        $t->same(null, $search->longestCommonDirectory());
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
    'mixed top pathspecs keep icase caller prefixes case sensitive during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('FOO', new Tree([$blob('BAR')])),
            $tree('foo', new Tree([$blob('bar')])),
            $tree('other', new Tree([$blob('path')])),
        ]);
        $search = PathspecSearch::fromSpecs([':(icase)bar', ':(top)other/path'], 'FOO');

        $t->same('', $search->commonPrefix());
        $t->same('FOO', $search->patterns()[0]->prefixDirectory());
        $t->same(true, $search->isIncluded('FOO/BAR', false));
        $t->same(false, $search->isIncluded('foo/bar', false));
        $t->same(true, $search->isIncluded('other/path', false));

        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            'FOO/BAR',
            'other/path',
        ], $walkPaths($records));
    },
    'guards prefix normalization from escaping the worktree during tree walks' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();

        $sibling = PathspecSearch::fromSpecs(['../themes/acme/theme.json'], 'wp-content/plugins');
        $t->same('wp-content', $sibling->prefixDirectory());
        $t->same(true, $sibling->isIncluded('wp-content/themes/acme/theme.json', false));
        $t->same([
            'wp-content/themes/acme/theme.json',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $sibling,
            $read,
            includeTrees: false,
        )));

        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs(['../../../index.php'], 'wp-content/plugins'),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs([':(top)../index.php'], 'wp-content/plugins'),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs(['../..'], 'wp-content'),
        );
    },
    'normalizes absolute worktree root pathspecs during tree walks' => static function (TestRunner $t) use ($makeTreeStore, $blobOid, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $worktreeRoot = '/srv/www/example.com/current';

        $absolute = PathspecSearch::fromSpecs(
            [$worktreeRoot . '/wp-content/plugins/gutenberg/block.json'],
            'wp-content/plugins',
            root: $worktreeRoot,
        );
        $t->same('wp-content/plugins/gutenberg/block.json', $absolute->patterns()[0]->path);
        $t->same(strlen('wp-content/plugins/gutenberg'), $absolute->patterns()[0]->prefixLength);
        $t->same('wp-content/plugins/gutenberg', $absolute->prefixDirectory());
        $t->same(true, $absolute->isIncluded('wp-content/plugins/gutenberg/block.json', false));
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $absolute,
            $read,
            includeTrees: false,
        )));

        $topAbsolute = PathspecSearch::fromSpecs(
            [':(top)' . $worktreeRoot . '/index.php'],
            'wp-content/plugins',
            root: $worktreeRoot,
        );
        $t->same('index.php', $topAbsolute->patterns()[0]->path);
        $t->same([
            'index.php',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $topAbsolute,
            $read,
            includeTrees: false,
        )));

        $absoluteDirectory = PathspecSearch::fromSpecs(
            [$worktreeRoot . '/wp-content/themes/acme/'],
            root: $worktreeRoot,
        );
        $t->same('wp-content/themes/acme', $absoluteDirectory->patterns()[0]->path);
        $t->same(strlen('wp-content/themes/acme'), $absoluteDirectory->patterns()[0]->prefixLength);
        $t->same([
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/style.css',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $absoluteDirectory,
            $read,
            includeTrees: false,
        )));

        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $wildcardRoot = new Tree([
            $tree('*', new Tree([$blob('README.md')])),
            $tree('WP-CONTENT', new Tree([$blob('README.md')])),
            $tree('wp-content', new Tree([$blob('README.md')])),
        ]);
        $foldedAbsoluteWildcard = PathspecSearch::fromSpecs([
            ':(icase)' . $worktreeRoot . '/*/readme.md',
        ], root: $worktreeRoot);
        $t->same('*/readme.md', $foldedAbsoluteWildcard->patterns()[0]->path);
        $t->same(1, $foldedAbsoluteWildcard->patterns()[0]->prefixLength);
        $t->same('*', $foldedAbsoluteWildcard->prefixDirectory());
        $t->same(false, $foldedAbsoluteWildcard->isIncluded('wp-content/README.md', false));
        $t->same(false, $foldedAbsoluteWildcard->isIncluded('WP-CONTENT/README.md', false));
        $t->same(true, $foldedAbsoluteWildcard->isIncluded('*/README.md', false));
        $t->same(false, $foldedAbsoluteWildcard->canMatch('wp-content', true));
        $t->same(true, $foldedAbsoluteWildcard->canMatch('*', true));

        $wildcardReadPaths = [];
        $wildcardRecords = TreePathspecWalk::breadthFirst(
            $wildcardRoot,
            $foldedAbsoluteWildcard,
            static function (TreeEntry $entry, string $path) use (&$objects, &$wildcardReadPaths): GitObject {
                $wildcardReadPaths[] = $path;

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );
        $t->same(['*/README.md'], $walkPaths($wildcardRecords));
        $t->same(['*'], $wildcardReadPaths);

        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs([$worktreeRoot . '/../shared/wp-config.php'], root: $worktreeRoot),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs(['/srv/www/other/wp-config.php'], root: $worktreeRoot),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs([$worktreeRoot . '/wp-config.php'], root: 'relative/root'),
        );
    },
    'tree pathspec matching preserves raw candidate path components' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $tree('plugins', new Tree([
                    $tree('..', new Tree([$blob('secret.php')])),
                    $blob('safe.php'),
                    $blob('weird\\name.php'),
                ])),
            ])),
        ]);
        $read = static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
            if (!isset($objects[$entry->oid])) {
                throw new RuntimeException("Missing tree object for {$path}");
            }

            return $objects[$entry->oid];
        };
        $search = PathspecSearch::fromSpecs([
            'wp-content/secret.php',
            'wp-content/plugins/safe.php',
            'wp-content/plugins/weird/name.php',
        ]);

        $t->same(null, $search->match('wp-content/plugins/../secret.php', false));
        $t->same(null, $search->match('wp-content/plugins/weird\\name.php', false));
        $t->same(false, $search->canMatch('wp-content/plugins/..', true));
        $t->same(true, $search->directoryMatchesPrefix('wp-content/plugins/..', true));
        $t->same(true, $search->isIncluded('wp-content/plugins/safe.php', false));

        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use ($read, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $read($entry, $path);
            },
            includeTrees: false,
        );

        $t->same(['wp-content/plugins/safe.php'], $walkPaths($records));
        $t->same(['wp-content', 'wp-content/plugins'], $readPaths);
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
    'tree walk applies attr pathspec filters like upstream gix dir traversal' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $tree('cache', new Tree([$blob('page.html')])),
                $tree('plugins', new Tree([
                    $tree('gutenberg', new Tree([$blob('block.json'), $blob('readme.txt')])),
                    $tree('private', new Tree([$blob('secret.php')])),
                ])),
                $tree('themes', new Tree([
                    $tree('acme', new Tree([$blob('style.css'), $blob('theme.json')])),
                ])),
                $tree('uploads', new Tree([$blob('logo.png')])),
            ])),
        ]);
        $read = static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
            if (!isset($objects[$entry->oid])) {
                throw new RuntimeException("Missing tree object for {$path}");
            }

            return $objects[$entry->oid];
        };
        $attributes = GitAttributes::fromString(
            "wp-content/plugins/** deploy=plugin merge=union\n"
            . "wp-content/plugins/private/** !deploy\n"
            . "wp-content/themes/** deploy=theme\n"
            . "wp-content/cache/** export-ignore\n",
            withBuiltInMacros: false,
        );
        $search = PathspecSearch::fromSpecs([
            ':(attr:deploy=plugin)wp-content/plugins/**',
            ':(attr:deploy=theme)wp-content/themes/**',
            ':!:(attr:!deploy)wp-content/plugins/private/**',
            ':!:(attr:export-ignore)wp-content/cache/**',
        ]);

        $t->same([], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $search,
            $read,
            includeTrees: false,
        )));

        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            $read,
            includeTrees: false,
            attributes: $attributes,
        );

        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/themes/acme/style.css',
            'wp-content/themes/acme/theme.json',
        ], $walkPaths($records));
        $t->same([
            PathspecMatch::KIND_WILDCARD,
            PathspecMatch::KIND_WILDCARD,
            PathspecMatch::KIND_WILDCARD,
            PathspecMatch::KIND_WILDCARD,
        ], array_map(static fn (TreeWalkEntry $record): string => $record->matchKind, $records));
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
    'wordpress tree pathspec example records root escape guard' => static function (TestRunner $t): void {
        $example = require dirname(__DIR__) . '/examples/wordpress-tree-pathspec-walk.php';

        $t->same([
            'wp-content/themes/acme/theme.json',
        ], $example['siblingPrefixContentPaths']);
        $t->same([
            'index.php',
            'wp-content/plugins/gutenberg/block.json',
        ], $example['absoluteRootContentPaths']);
        $t->same('wp-content/plugins/gutenberg', $example['absoluteRootPluginPrefix']);
        $t->same(true, $example['absoluteRootOutsideRejected']);
        $t->same(true, $example['absoluteRootRelativeRejected']);
        $t->same(true, $example['rootEscapingPathspecRejected']);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/block.gson',
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/theme.?son',
            'wp-content/themes/acme/style.css',
        ], $example['attrFilteredContentPaths']);
        $t->same(true, $example['attrFilteredWithoutProviderEmpty']);
        $t->same(true, $example['mixedPrefixCommonPrefixCollapsed']);
        $t->same([
            'index.php',
            'WP-CONTENT/mu-plugins/Loader.PHP',
        ], $example['mixedPrefixContentPaths']);
        $t->same(true, $example['mixedPrefixLowerContentSkipped']);
        $t->same(true, $example['mixedPrefixUpperContentIncluded']);
        $t->same('wp-content/plugins/gutenberg', $example['pluginPruningHintDirectory']);
        $t->same(null, $example['callerPrefixOnlyPruningHint']);
        $t->same('wp-content/plugins/gutenberg', $example['directoryOnlyPruningHint']);
        $t->same(false, $example['excludeNilCanDescendIntoContent']);
        $t->same([], $example['excludeNilContentPaths']);
        $t->same([], $example['excludeNilReadPaths']);
        $t->same(['wp-content/plugins/safe.php'], $example['rawComponentGuardContentPaths']);
        $t->same(['wp-content', 'wp-content/plugins'], $example['rawComponentGuardReadPaths']);
        $t->same(true, $example['rawParentComponentSkipped']);
        $t->same(true, $example['rawBackslashComponentSkipped']);
    },
];
