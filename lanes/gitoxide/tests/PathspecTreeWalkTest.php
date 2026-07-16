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
    'matches gix byte wildmatch newline paths during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $shellNewline = PathspecSearch::fromSpecs(['wp-content/plugins/new?line/block.json']);
        $shellStarNewline = PathspecSearch::fromSpecs(['wp-content/plugins/new*line/block.json']);
        $pathAwareNewline = PathspecSearch::fromSpecs([':(glob)wp-content/plugins/new?line/block.json']);

        $t->same(true, $shellNewline->isIncluded("wp-content/plugins/new\nline/block.json", false));
        $t->same(true, $shellStarNewline->isIncluded("wp-content/plugins/new\nline/block.json", false));
        $t->same(true, $pathAwareNewline->isIncluded("wp-content/plugins/new\nline/block.json", false));
        $t->same(true, $shellNewline->isIncluded('wp-content/plugins/new/line/block.json', false));
        $t->same(false, $pathAwareNewline->isIncluded('wp-content/plugins/new/line/block.json', false));

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
                    $tree("new\nline", new Tree([$blob('block.json')])),
                    $tree('new', new Tree([
                        $tree('line', new Tree([$blob('block.json')])),
                    ])),
                ])),
            ])),
        ]);
        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            $shellNewline,
            static function (TreeEntry $entry, string $path) use (&$objects, &$readPaths): GitObject {
                $readPaths[] = $path;
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            "wp-content/plugins/new\nline/block.json",
            'wp-content/plugins/new/line/block.json',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/plugins',
            "wp-content/plugins/new\nline",
            'wp-content/plugins/new',
            'wp-content/plugins/new/line',
        ], $readPaths);

        $pathAwareRecords = TreePathspecWalk::breadthFirst(
            $root,
            $pathAwareNewline,
            static function (TreeEntry $entry, string $path) use (&$objects): GitObject {
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );
        $t->same([
            "wp-content/plugins/new\nline/block.json",
        ], $walkPaths($pathAwareRecords));
    },
    'falls back to verbatim matches after dangling backslash wildmatch aborts' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $literalDangling = PathspecSearch::fromSpecs([':(glob)wp-content/plugins/dangling\\']);
        $wildcardDangling = PathspecSearch::fromSpecs([':(glob)wp-content/plugins/dang*\\']);

        $literalMatch = $literalDangling->match('wp-content/plugins/dangling\\', false);
        $t->same(PathspecMatch::KIND_VERBATIM, $literalMatch?->kind);
        $t->same(true, $literalDangling->isIncluded('wp-content/plugins/dangling\\', false));
        $t->same(false, $literalDangling->isIncluded('wp-content/plugins/dangling', false));
        $t->same(false, $wildcardDangling->isIncluded('wp-content/plugins/dangling\\', false));
        $t->same(PathspecMatch::KIND_VERBATIM, $wildcardDangling->match('wp-content/plugins/dang*\\', false)?->kind);

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
                    $blob('dangling\\'),
                    $blob('dang*\\'),
                    $blob('dangx\\'),
                ])),
            ])),
        ]);

        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([
                ':(glob)wp-content/plugins/dangling\\',
                ':(glob)wp-content/plugins/dang*\\',
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
            'wp-content/plugins/dangling\\',
            'wp-content/plugins/dang*\\',
        ], $walkPaths($records));
    },
    'keeps escaped byte pathspec prefixes traversable during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $search = PathspecSearch::fromSpecs([':(glob)wp-content/plugins/f\\oo/block.json']);

        $t->same(null, $search->match('wp-content/plugins/f', true));
        $t->same(true, $search->canMatch('wp-content/plugins/f', true));
        $t->same(true, $search->directoryMatchesPrefix('wp-content/plugins/f', true));
        $t->same(true, $search->canMatch('wp-content/plugins/foo', true));
        $t->same(true, $search->directoryMatchesPrefix('wp-content/plugins/foo', true));
        $t->same(PathspecMatch::KIND_WILDCARD, $search->match('wp-content/plugins/foo/block.json', false)?->kind);
        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/f\\oo/block.json', false)?->kind);
        $t->same(false, $search->isIncluded('wp-content/plugins/f/not-block.json', false));

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
                    $tree('f', new Tree([$blob('not-block.json')])),
                    $tree('foo', new Tree([$blob('block.json')])),
                    $tree('f\\oo', new Tree([$blob('block.json')])),
                    $tree('bar', new Tree([$blob('block.json')])),
                ])),
            ])),
        ]);

        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use (&$objects, &$readPaths): GitObject {
                $readPaths[] = $path;
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            'wp-content/plugins/foo/block.json',
            'wp-content/plugins/f\\oo/block.json',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/plugins',
            'wp-content/plugins/f',
            'wp-content/plugins/foo',
            'wp-content/plugins/f\\oo',
        ], $readPaths);
    },
    'matches gix wildmatch POSIX blank and invalid class boundaries during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $blankClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg']);
        $spaceClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/slot[[:space:]]/photo.jpg']);
        $invalidClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/[[:unknown:]]/photo.jpg']);
        $malformedPosixClass = PathspecSearch::fromSpecs([':(glob)wp-content/uploads/[[:alpha]/photo.jpg']);

        $t->same(false, $blankClass->isIncluded("wp-content/uploads/slot\v/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded("wp-content/uploads/slot\f/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded("wp-content/uploads/slot\r/photo.jpg", false));
        $t->same(true, $blankClass->isIncluded('wp-content/uploads/slot /photo.jpg', false));
        $t->same(false, $spaceClass->isIncluded("wp-content/uploads/slot\t/photo.jpg", false));
        $t->same(true, $spaceClass->isIncluded('wp-content/uploads/slot /photo.jpg', false));
        $t->same(true, $invalidClass->isIncluded('wp-content/uploads/[[:unknown:]]/photo.jpg', false));
        $t->same(false, $invalidClass->isIncluded('wp-content/uploads/unknown/photo.jpg', false));
        $t->same(true, $malformedPosixClass->isIncluded('wp-content/uploads/a/photo.jpg', false));
        $t->same(true, $malformedPosixClass->isIncluded('wp-content/uploads/[/photo.jpg', false));
        $t->same(PathspecMatch::KIND_VERBATIM, $malformedPosixClass->match('wp-content/uploads/[[:alpha]/photo.jpg', false)?->kind);

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
                    $tree("slot\t", new Tree([$blob('photo.jpg')])),
                    $tree("slot\f", new Tree([$blob('photo.jpg')])),
                    $tree('slot ', new Tree([$blob('photo.jpg')])),
                    $tree('[[:unknown:]]', new Tree([$blob('photo.jpg')])),
                    $tree('a', new Tree([$blob('photo.jpg')])),
                    $tree('[', new Tree([$blob('photo.jpg')])),
                    $tree('[[:alpha]', new Tree([$blob('photo.jpg')])),
                ])),
            ])),
        ]);

        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([
                ':(glob)wp-content/uploads/slot[[:blank:]]/photo.jpg',
                ':(glob)wp-content/uploads/[[:unknown:]]/photo.jpg',
                ':(glob)wp-content/uploads/[[:alpha]/photo.jpg',
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
            "wp-content/uploads/slot\t/photo.jpg",
            "wp-content/uploads/slot\f/photo.jpg",
            'wp-content/uploads/slot /photo.jpg',
            'wp-content/uploads/[[:unknown:]]/photo.jpg',
            'wp-content/uploads/a/photo.jpg',
            'wp-content/uploads/[/photo.jpg',
            'wp-content/uploads/[[:alpha]/photo.jpg',
        ], $walkPaths($records));
    },
    'all ascii whitespace directory pathspecs follow gix glob fallback during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $spaces = PathspecSearch::fromSpecs(['   /']);
        $formFeed = PathspecSearch::fromSpecs(["\f/"]);

        $t->same(true, $spaces->patterns()[0]->mustBeDirectory);
        $t->same(PathspecMatch::KIND_VERBATIM, $spaces->match('   ', false)?->kind);
        $t->same(true, $spaces->isIncluded('   ', false));
        $t->same(PathspecMatch::KIND_PREFIX, $spaces->match('   /index.php', false)?->kind);
        $t->same(false, $spaces->canMatch('   ', false));
        $t->same(PathspecMatch::KIND_VERBATIM, $formFeed->match("\f", false)?->kind);
        $t->same(true, $formFeed->isIncluded("\f", false));

        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $blob('   '),
            $blob("\f"),
            $tree('wp-content', new Tree([$blob('index.php')])),
        ]);
        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs(['   /', "\f/"]),
            static function (TreeEntry $entry, string $path) use (&$objects, &$readPaths): GitObject {
                $readPaths[] = $path;
                if (!isset($objects[$entry->oid])) {
                    throw new RuntimeException("Missing tree object for {$path}");
                }

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            '   ',
            "\f",
        ], $walkPaths($records));
        $t->same([], $readPaths);
    },
    'matches directory-only pathspecs as verbatim and prefix matches' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['wp-content/plugins/gutenberg/']);

        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/gutenberg', true)?->kind);
        $t->same(null, $search->match('wp-content/plugins/gutenberg', false));
        $t->same(PathspecMatch::KIND_PREFIX, $search->match('wp-content/plugins/gutenberg/block.json', false)?->kind);
        $t->same(false, $search->isIncluded('wp-content/plugins/akismet/akismet.php', false));
    },
    'walk treats gitlinks as directory pathspec candidates without descending' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
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
                    new TreeEntry('160000', 'commerce-submodule', str_repeat('2', 40)),
                    $tree('gutenberg', new Tree([$blob('block.json')])),
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
            'wp-content/plugins/commerce-submodule/',
            'wp-content/plugins/gutenberg/',
        ]);

        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/commerce-submodule', true)?->kind);
        $t->same(null, $search->match('wp-content/plugins/commerce-submodule', false));

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

        $t->same([
            'wp-content/plugins/commerce-submodule',
            'wp-content/plugins/gutenberg/block.json',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/plugins',
            'wp-content/plugins/gutenberg',
        ], $readPaths);

        $excluded = PathspecSearch::fromSpecs([
            'wp-content/plugins/**',
            ':!wp-content/plugins/commerce-submodule/',
        ]);
        $t->same(true, $excluded->match('wp-content/plugins/commerce-submodule', true)?->isExcluded());
        $t->same(false, $excluded->isIncluded('wp-content/plugins/commerce-submodule', true));
    },
    'walk keeps symlink entries outside directory-only pathspec descent' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
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
                    new TreeEntry('120000', 'linked-plugin', str_repeat('3', 40)),
                    $tree('real-plugin', new Tree([
                        $blob('manifest.json'),
                    ])),
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
            'wp-content/plugins/linked-plugin/',
            'wp-content/plugins/real-plugin/',
        ]);

        $t->same(null, $search->match('wp-content/plugins/linked-plugin', false));
        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/linked-plugin', true)?->kind);
        $t->same(PathspecMatch::KIND_PREFIX, $search->match('wp-content/plugins/linked-plugin/manifest.json', false)?->kind);
        $t->same(PathspecMatch::KIND_VERBATIM, $search->match('wp-content/plugins/real-plugin', true)?->kind);
        $t->same(PathspecMatch::KIND_PREFIX, $search->match('wp-content/plugins/real-plugin/manifest.json', false)?->kind);

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

        $t->same([
            'wp-content/plugins/real-plugin/manifest.json',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/plugins',
            'wp-content/plugins/real-plugin',
        ], $readPaths);
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
    'tree walk descends through excluded wildcard directories when descendants can match' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('wp-content', new Tree([
                $tree('generated-cache', new Tree([
                    $blob('manifest.json'),
                    $blob('stale.tmp'),
                ])),
                $tree('media-cache', new Tree([
                    $blob('manifest.json'),
                ])),
                $tree('generated', new Tree([
                    $blob('manifest.json'),
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
            'wp-content/generated-cache/manifest.json',
            'wp-content/media-cache/manifest.json',
            ':!wp-content/*-cache',
        ]);

        $excludedDirectory = $search->match('wp-content/generated-cache', true);
        $t->same(true, $excludedDirectory?->isExcluded());
        $t->same(PathspecMatch::KIND_WILDCARD, $excludedDirectory?->kind);
        $t->same(true, $search->canMatch('wp-content/generated-cache', true));
        $t->same(true, $search->directoryMatchesPrefix('wp-content/generated-cache', true));
        $t->same(true, $search->isIncluded('wp-content/generated-cache/manifest.json', false));
        $t->same(false, $search->isIncluded('wp-content/generated-cache/stale.tmp', false));

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

        $t->same([
            'wp-content/generated-cache/manifest.json',
            'wp-content/media-cache/manifest.json',
        ], $walkPaths($records));
        $t->same([
            'wp-content',
            'wp-content/generated-cache',
            'wp-content/media-cache',
        ], $readPaths);

        $withTrees = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            $read,
            includeTrees: true,
        );
        $t->same([
            'wp-content',
            'wp-content/generated-cache/manifest.json',
            'wp-content/media-cache/manifest.json',
        ], $walkPaths($withTrees));

        $directoryOnlyExclude = PathspecSearch::fromSpecs([
            'wp-content/**',
            ':!wp-content/generated-cache/',
        ]);
        $directoryOnlyMatch = $directoryOnlyExclude->match('wp-content/generated-cache', true);
        $t->same(true, $directoryOnlyMatch?->isExcluded());
        $t->same(PathspecMatch::KIND_VERBATIM, $directoryOnlyMatch?->kind);
        $t->same(false, $directoryOnlyExclude->isIncluded('wp-content/generated-cache/manifest.json', false));

        $directoryOnlyReadPaths = [];
        $directoryOnlyPaths = $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $directoryOnlyExclude,
            static function (TreeEntry $entry, string $path) use ($read, &$directoryOnlyReadPaths): GitObject {
                $directoryOnlyReadPaths[] = $path;

                return $read($entry, $path);
            },
            includeTrees: false,
        ));
        $t->same(false, in_array('wp-content/generated-cache', $directoryOnlyReadPaths, true));
        $t->same(false, in_array('wp-content/generated-cache/manifest.json', $directoryOnlyPaths, true));
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
    'empty icase pathspecs keep only parent prefixes case-sensitive during tree walks' => static function (TestRunner $t) use ($blobOid, $walkPaths): void {
        $objects = [];
        $blob = static fn (string $name): TreeEntry => new TreeEntry('100644', $name, $blobOid);
        $tree = static function (string $name, Tree $tree) use (&$objects): TreeEntry {
            $object = $tree->toObject();
            $objects[$object->oid()] = $object;

            return new TreeEntry('040000', $name, $object->oid());
        };
        $root = new Tree([
            $tree('WP-CONTENT', new Tree([
                $tree('plugins', new Tree([$blob('Safe.PHP')])),
                $tree('PLUGINS', new Tree([$blob('SAFE.PHP')])),
                $tree('themes', new Tree([$blob('style.css')])),
            ])),
            $tree('wp-content', new Tree([
                $tree('plugins', new Tree([$blob('safe.php')])),
            ])),
        ]);
        $search = PathspecSearch::fromSpecs([':(icase)'], 'WP-CONTENT/plugins');

        $t->same('WP-CONTENT/plugins', $search->patterns()[0]->path);
        $t->same('WP-CONTENT', $search->patterns()[0]->prefixDirectory());
        $t->same('WP-CONTENT', $search->commonPrefix());
        $t->same(true, $search->isIncluded('WP-CONTENT/plugins/Safe.PHP', false));
        $t->same(true, $search->isIncluded('WP-CONTENT/PLUGINS/SAFE.PHP', false));
        $t->same(false, $search->isIncluded('wp-content/plugins/safe.php', false));
        $t->same(false, $search->isIncluded('WP-CONTENT/themes/style.css', false));
        $t->same(true, $search->canMatch('WP-CONTENT/PLUGINS', true));
        $t->same(false, $search->canMatch('wp-content/plugins', true));
        $t->same(true, $search->directoryMatchesPrefix('WP-CONTENT/PLUGINS', true));
        $t->same(false, $search->directoryMatchesPrefix('wp-content/plugins', true));

        $readPaths = [];
        $records = TreePathspecWalk::breadthFirst(
            $root,
            $search,
            static function (TreeEntry $entry, string $path) use (&$objects, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $objects[$entry->oid];
            },
            includeTrees: false,
        );

        $t->same([
            'WP-CONTENT/plugins/Safe.PHP',
            'WP-CONTENT/PLUGINS/SAFE.PHP',
        ], $walkPaths($records));
        $t->same(['WP-CONTENT', 'WP-CONTENT/plugins', 'WP-CONTENT/PLUGINS'], $readPaths);
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
        $t->throws(
            InvalidArgumentException::class,
            static fn () => PathspecSearch::fromSpecs(['/wp-content/plugins/gutenberg/block.json']),
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
    'root-normalized dot pathspecs prune tree walks like upstream search' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();

        $rootDot = PathspecSearch::fromSpecs(['.']);
        $t->same('.', $rootDot->patterns()[0]->path);
        $t->same(true, $rootDot->patterns()[0]->nil);
        $t->same('.', $rootDot->commonPrefix());
        $t->same(null, $rootDot->match('index.php', false));
        $t->same(false, $rootDot->canMatch('wp-content', true));
        $t->same(false, $rootDot->directoryMatchesPrefix('wp-content', true));

        $readPaths = [];
        $t->same([], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $rootDot,
            static function (TreeEntry $entry, string $path) use ($read, &$readPaths): GitObject {
                $readPaths[] = $path;

                return $read($entry, $path);
            },
            includeTrees: false,
        )));
        $t->same([], $readPaths);

        $topDot = PathspecSearch::fromSpecs([':(top).'], 'wp-content/plugins');
        $t->same('.', $topDot->patterns()[0]->path);
        $t->same(true, $topDot->patterns()[0]->nil);
        $t->same('.', $topDot->commonPrefix());
        $t->same([], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $topDot,
            $read,
            includeTrees: false,
        )));

        $prefixConsumesToRoot = PathspecSearch::fromSpecs(['../..'], 'wp-content/plugins');
        $t->same('.', $prefixConsumesToRoot->patterns()[0]->path);
        $t->same(true, $prefixConsumesToRoot->patterns()[0]->nil);
        $t->same('.', $prefixConsumesToRoot->commonPrefix());
        $t->same(null, $prefixConsumesToRoot->match('index.php', false));
        $t->same([], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $prefixConsumesToRoot,
            $read,
            includeTrees: false,
        )));

        $prefixedDot = PathspecSearch::fromSpecs(['.'], 'wp-content/plugins');
        $t->same('wp-content/plugins', $prefixedDot->patterns()[0]->path);
        $t->same(false, $prefixedDot->patterns()[0]->nil);
        $t->same('wp-content/plugins', $prefixedDot->commonPrefix());
        $t->same('wp-content', $prefixedDot->prefixDirectory());
        $t->same('wp-content', $prefixedDot->longestCommonDirectory());
        $t->same([
            'wp-content/plugins/akismet/akismet.php',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $prefixedDot,
            $read,
            includeTrees: false,
        )));
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
    'empty pathspecs can ignore caller prefixes for repository-wide tree walks' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();
        $plainPaths = $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            PathspecSearch::fromSpecs([]),
            $read,
            includeTrees: false,
        ));
        $repositoryWide = PathspecSearch::fromSpecs(
            [],
            'wp-content/themes',
            emptyPatternsMatchPrefix: false,
        );

        $t->same([], $repositoryWide->patterns());
        $t->same('', $repositoryWide->commonPrefix());
        $t->same('', $repositoryWide->prefixDirectory());
        $t->same(null, $repositoryWide->longestCommonDirectory());
        $t->same(PathspecMatch::KIND_ALWAYS, $repositoryWide->match('index.php', false)?->kind);
        $t->same(true, $repositoryWide->isIncluded('wp-admin/admin.php', false));
        $t->same(true, $repositoryWide->canMatch('wp-admin', true));
        $t->same(true, $repositoryWide->directoryMatchesPrefix('wp-admin', true));

        $paths = $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $repositoryWide,
            $read,
            includeTrees: false,
        ));

        $t->same($plainPaths, $paths);
        $t->same(true, in_array('wp-admin/admin.php', $paths, true));
        $t->same(true, in_array('wp-content/plugins/gutenberg/build/index.js', $paths, true));
    },
    'explicit nil and empty magic pathspecs inherit caller prefixes during tree walks' => static function (TestRunner $t) use ($makeTreeStore, $walkPaths): void {
        [$root, $read] = $makeTreeStore();

        $nil = PathspecSearch::fromSpecs([':'], 'wp-content/themes');
        $t->same(true, $nil->patterns()[0]->nil);
        $t->same('wp-content/themes', $nil->patterns()[0]->path);
        $t->same('wp-content/themes', $nil->commonPrefix());
        $t->same(true, $nil->canMatch('wp-content', true));
        $t->same(false, $nil->canMatch('wp-admin', true));
        $t->same(null, $nil->match('index.php', false));
        $t->same(PathspecMatch::KIND_ALWAYS, $nil->match('wp-content/themes/acme/style.css', false)?->kind);
        $t->same([
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/style.css',
            'wp-content/themes/twentytwentyfive/style.css',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $nil,
            $read,
            includeTrees: false,
        )));

        $emptyMagic = PathspecSearch::fromSpecs([':()'], 'wp-content/plugins/gutenberg');
        $t->same('wp-content/plugins/gutenberg', $emptyMagic->patterns()[0]->path);
        $t->same(false, $emptyMagic->patterns()[0]->nil);
        $t->same(null, $emptyMagic->match('wp-content/plugins/akismet/akismet.php', false));
        $t->same(PathspecMatch::KIND_PREFIX, $emptyMagic->match('wp-content/plugins/gutenberg/src/editor.js', false)?->kind);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/readme.txt',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $walkPaths(TreePathspecWalk::breadthFirst(
            $root,
            $emptyMagic,
            $read,
            includeTrees: false,
        )));

        $excludePrefix = PathspecSearch::fromSpecs([':(exclude)'], 'wp-content/themes');
        $t->same(true, $excludePrefix->isIncluded('index.php', false));
        $t->same(false, $excludePrefix->isIncluded('wp-content/themes/acme/style.css', false));
        $t->same(true, $excludePrefix->match('wp-content/themes/acme/style.css', false)?->isExcluded());
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
        $t->same(true, $example['rootlessAbsolutePathspecRejected']);
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
        $t->same([
            'wp-content/plugins/commerce-submodule',
        ], $example['gitlinkDirectoryContentPaths']);
        $t->same([
            'wp-content',
            'wp-content/plugins',
        ], $example['gitlinkDirectoryReadPaths']);
        $t->same(PathspecMatch::KIND_VERBATIM, $example['gitlinkDirectoryMatchKind']);
        $t->same(true, $example['gitlinkDirectoryFileModeSkipped']);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/block.gson',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $example['symlinkDirectoryBoundaryContentPaths']);
        $t->same([
            'wp-content',
            'wp-content/plugins',
            'wp-content/plugins/gutenberg',
            'wp-content/plugins/gutenberg/build',
            'wp-content/plugins/gutenberg/src',
        ], $example['symlinkDirectoryBoundaryReadPaths']);
        $t->same(true, $example['symlinkDirectoryBoundaryFileModeSkipped']);
        $t->same(true, $example['symlinkDirectoryBoundaryDirectoryModeWouldMatch']);
        $t->same(false, $example['excludeNilCanDescendIntoContent']);
        $t->same([], $example['excludeNilContentPaths']);
        $t->same([], $example['excludeNilReadPaths']);
        $t->same('wp-content/themes', $example['prefixedNilCommonPrefix']);
        $t->same([
            'wp-content/themes/acme/theme.json',
            'wp-content/themes/acme/theme.?son',
            'wp-content/themes/acme/style.css',
        ], $example['prefixedNilContentPaths']);
        $t->same(true, $example['prefixedNilRootIndexSkipped']);
        $t->same([
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/block.gson',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $example['prefixedEmptyMagicContentPaths']);
        $t->same(true, $example['prefixedEmptyMagicAkismetSkipped']);
        $t->same(true, $example['prefixedExcludeNilKeepsIndex']);
        $t->same(true, $example['prefixedExcludeNilSkipsTheme']);
        $t->same($example['noPathspecWalkCount'], $example['emptyPatternsRepositoryWideCount']);
        $t->same(true, $example['emptyPatternsRepositoryWideAdminIncluded']);
        $t->same(true, $example['emptyPatternsRepositoryWideThemePrefixIgnored']);
        $t->same(['wp-content/plugins/safe.php'], $example['rawComponentGuardContentPaths']);
        $t->same(['wp-content', 'wp-content/plugins'], $example['rawComponentGuardReadPaths']);
        $t->same(true, $example['rawParentComponentSkipped']);
        $t->same(true, $example['rawBackslashComponentSkipped']);
        $t->same('.', $example['rootDotCommonPrefix']);
        $t->same([], $example['rootDotContentPaths']);
        $t->same([], $example['rootDotReadPaths']);
        $t->same([], $example['topDotContentPaths']);
        $t->same([], $example['parentToRootDotContentPaths']);
        $t->same('.', $example['parentToRootDotCommonPrefix']);
        $t->same('wp-content', $example['prefixedDotPrefixDirectory']);
        $t->same([
            'wp-content/plugins/commerce-submodule',
            'wp-content/plugins/linked-plugin',
            'wp-content/plugins/safe.php',
            'wp-content/plugins/weird\\name.php',
            'wp-content/plugins/dangling\\',
            'wp-content/plugins/dang*\\',
            'wp-content/plugins/dangx\\',
            'wp-content/plugins/../secret.php',
            'wp-content/plugins/akismet/akismet.php',
            'wp-content/plugins/akismet/block.json',
            'wp-content/plugins/f/not-block.json',
            'wp-content/plugins/foo/block.json',
            'wp-content/plugins/f\\oo/block.json',
            'wp-content/plugins/gutenberg/block.json',
            'wp-content/plugins/gutenberg/block.gson',
            "wp-content/plugins/new\nline/block.json",
            'wp-content/plugins/[literal]/block.?son',
            'wp-content/plugins/gutenberg/build/index.js',
            'wp-content/plugins/gutenberg/src/editor.js',
        ], $example['prefixedDotContentPaths']);
        $t->same([
            "wp-content/plugins/new\nline/block.json",
        ], $example['shellGlobNewlineContentPaths']);
        $t->same(true, $example['shellGlobNewlineIncluded']);
        $t->same([
            'wp-content/plugins/dangling\\',
            'wp-content/plugins/dang*\\',
        ], $example['danglingBackslashContentPaths']);
        $t->same(PathspecMatch::KIND_VERBATIM, $example['danglingBackslashExactMatchKind']);
        $t->same(true, $example['danglingBackslashWildcardSkipped']);
        $t->same(true, $example['danglingBackslashLiteralStarIncluded']);
        $t->same([
            'wp-content/plugins/foo/block.json',
            'wp-content/plugins/f\\oo/block.json',
        ], $example['escapedByteTraversalContentPaths']);
        $t->same([
            'wp-content',
            'wp-content/plugins',
            'wp-content/plugins/f',
            'wp-content/plugins/foo',
            'wp-content/plugins/f\\oo',
        ], $example['escapedByteTraversalReadPaths']);
        $t->same(true, $example['escapedBytePrefixDirectoryTraversed']);
        $t->same(true, $example['escapedByteResolvedDirectoryTraversed']);
        $t->same(true, $example['escapedByteWildcardIncluded']);
        $t->same(true, $example['escapedByteVerbatimFallbackIncluded']);
        $t->same([
            'wp-content/uploads/a/hero.jpg',
            'wp-content/uploads/[/hero.jpg',
            'wp-content/uploads/[[:alpha]/hero.jpg',
        ], $example['malformedPosixClassContentPaths']);
        $t->same(false, $example['malformedPosixClassLetterSkipped']);
        $t->same(false, $example['malformedPosixClassBracketSkipped']);
        $t->same(true, $example['malformedPosixClassLetterIncluded']);
        $t->same(true, $example['malformedPosixClassBracketIncluded']);
        $t->same(true, $example['malformedPosixClassLiteralIncluded']);
        $t->same([
            '   ',
            "\f",
        ], $example['whitespaceDirectoryOnlyContentPaths']);
        $t->same(true, $example['whitespaceDirectoryOnlySpaceFileIncluded']);
        $t->same(true, $example['whitespaceDirectoryOnlyFormFeedFileIncluded']);
        $t->same(PathspecMatch::KIND_VERBATIM, $example['whitespaceDirectoryOnlySpaceMatchKind']);
        $t->same(true, $example['negativeWildcardCacheDirectoryExcluded']);
        $t->same(true, $example['negativeWildcardCacheCanDescend']);
        $t->same(true, in_array('wp-content/generated-cache', $example['negativeWildcardCacheReadPaths'], true));
        $t->same([
            'wp-content/generated-cache/manifest.json',
        ], $example['negativeWildcardCacheContentPaths']);
        $t->same(true, $example['negativeWildcardCacheStaleSkipped']);
        $t->same('WP-CONTENT', $example['emptyIcasePrefixDirectory']);
        $t->same('WP-CONTENT', $example['emptyIcaseCommonPrefix']);
        $t->same([
            'WP-CONTENT/plugins/Safe.PHP',
            'WP-CONTENT/PLUGINS/SAFE.PHP',
        ], $example['emptyIcasePrefixContentPaths']);
        $t->same(true, $example['emptyIcaseFoldedFinalPrefixIncluded']);
        $t->same(true, $example['emptyIcaseLowerRootSkipped']);
    },
];
