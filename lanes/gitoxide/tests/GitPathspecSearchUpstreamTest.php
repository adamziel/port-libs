<?php

declare(strict_types=1);

use PortLibs\Gitoxide\PathspecMatch;
use PortLibs\Gitoxide\PathspecSearch;

$assertMatch = static function (
    TestRunner $t,
    PathspecSearch $search,
    string $path,
    ?bool $isDirectory,
    ?string $expectedKind,
    bool $expectedExcluded = false,
    string $message = '',
): void {
    $match = $search->match($path, $isDirectory);
    if ($expectedKind === null) {
        $t->same(null, $match, $message);

        return;
    }

    $t->same($expectedKind, $match?->kind, $message);
    $t->same($expectedExcluded, $match?->isExcluded(), $message);
};

$assertIncludedPaths = static function (
    TestRunner $t,
    PathspecSearch $search,
    array $items,
    array $expected,
    string $message,
): void {
    $actual = array_values(array_filter(
        $items,
        static fn (string $relativePath): bool => $search->match($relativePath, false) !== null,
    ));

    $t->same($expected, $actual, $message);
};

return [
    'upstream search directory_matches_prefix' => static function (TestRunner $t): void {
        foreach (['dir', 'dir/', 'di*', 'dir/*', 'dir/*.o'] as $spec) {
            foreach ([[$spec], [$spec, 'other']] as $specs) {
                $search = PathspecSearch::fromSpecs($specs);
                $label = implode('+', $specs);

                $t->same(true, $search->directoryMatchesPrefix('dir', false), "{$label}: must match");
                $t->same(false, $search->directoryMatchesPrefix('d', false), "{$label}: must not match");
            }
        }

        foreach (['dir/d', 'dir/d/', 'dir/*/*', 'dir/d/*.o'] as $spec) {
            foreach ([[$spec], [$spec, 'other']] as $specs) {
                $search = PathspecSearch::fromSpecs($specs);
                $label = implode('+', $specs);

                $t->same(true, $search->directoryMatchesPrefix('dir/d', false), "{$label}: must match");
                $t->same(true, $search->directoryMatchesPrefix('dir/d', true), "{$label}: leading must match");
                foreach ([false, true] as $leading) {
                    $t->same(false, $search->directoryMatchesPrefix('d', $leading), "{$label}: d must not match");
                    $t->same(false, $search->directoryMatchesPrefix('di', $leading), "{$label}: di must not match");
                }
            }
        }
    },
    'upstream search directory_matches_prefix_starting_wildcards_always_match' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['*ir']);

        $t->same(true, $search->directoryMatchesPrefix('dir', false));
        $t->same(true, $search->directoryMatchesPrefix('d', false));
    },
    'upstream search empty_dir_always_matches' => static function (TestRunner $t): void {
        foreach ([
            ['*ir'],
            [],
            ['included', ':!excluded'],
            [':!all', ':!excluded'],
        ] as $specs) {
            $search = PathspecSearch::fromSpecs($specs);
            $label = json_encode($specs);

            $match = $search->match('', null);
            $t->same(PathspecMatch::KIND_ALWAYS, $match?->kind, "{$label}: empty path always matches");
            $t->same(true, $search->directoryMatchesPrefix('', false), "{$label}: empty directory matches");
            $t->same(true, $search->directoryMatchesPrefix('', false), "{$label}: empty directory matches twice");

            foreach ([true, false, null] as $isDirectory) {
                $t->same(true, $search->canMatch('', $isDirectory), "{$label}: can match empty path");
            }
        }
    },
    'upstream search directory_matches_prefix_leading' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['d/d/generated/b']);

        $t->same(false, $search->directoryMatchesPrefix('di', false));
        $t->same(false, $search->directoryMatchesPrefix('di', true));
        $t->same(true, $search->directoryMatchesPrefix('d', true));
        $t->same(false, $search->directoryMatchesPrefix('d', false));
        $t->same(true, $search->directoryMatchesPrefix('d/d', true));
        $t->same(false, $search->directoryMatchesPrefix('d/d', false));
        $t->same(true, $search->directoryMatchesPrefix('d/d/generated', true));
        $t->same(false, $search->directoryMatchesPrefix('d/d/generated', false));
        $t->same(false, $search->directoryMatchesPrefix('d/d/generatedfoo', false));
        $t->same(false, $search->directoryMatchesPrefix('d/d/generatedfoo', true));

        $icase = PathspecSearch::fromSpecs([':(icase)d/d/GENERATED/b']);
        $t->same(true, $icase->directoryMatchesPrefix('d/d/generated', true));
        $t->same(false, $icase->directoryMatchesPrefix('d/d/generated', false));
    },
    'upstream search directory_matches_prefix_negative_wildcard' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs([':!*generated*']);

        foreach (['di', 'd', 'd/d', 'd/d/generated', 'd/d/generatedfoo'] as $path) {
            $t->same(true, $search->directoryMatchesPrefix($path, false), "{$path}: no-leading");
            $t->same(true, $search->directoryMatchesPrefix($path, true), "{$path}: leading");
        }

        $icase = PathspecSearch::fromSpecs([':(exclude,icase)*GENERATED*']);
        $t->same(true, $icase->directoryMatchesPrefix('d/d/generated', true));
        $t->same(true, $icase->directoryMatchesPrefix('d/d/generated', false));
    },
    'upstream search directory_matches_prefix_all_excluded' => static function (TestRunner $t): void {
        foreach (['!dir', '!dir/', '!d*', '!di*', '!dir/*', '!dir/*.o', '!*ir'] as $spec) {
            foreach ([[$spec], [$spec, 'other']] as $specs) {
                $search = PathspecSearch::fromSpecs($specs);

                $t->same(
                    false,
                    $search->directoryMatchesPrefix('dir', false),
                    implode('+', $specs) . ': dir must not match',
                );
            }
        }
    },
    'upstream search no_pathspecs_match_everything' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs([]);

        $t->same(0, count($search->patterns()));
        $match = $search->match('hello', null);
        $t->same('', $match?->pattern->prefixDirectory());
        $t->same(PathspecMatch::KIND_ALWAYS, $match?->kind);
        $t->same(0, $match?->sequenceNumber);
        $t->same(true, $search->canMatch('anything', null));
        $t->same(true, $search->directoryMatchesPrefix('anything', false));
    },
    'upstream search included_directory_and_excluded_subdir_top_level_with_prefix' => static function (TestRunner $t) use ($assertMatch): void {
        $search = PathspecSearch::fromSpecs([':/foo', ':!/foo/target/'], 'foo');

        $assertMatch($t, $search, 'foo', true, PathspecMatch::KIND_VERBATIM);
        $assertMatch($t, $search, 'foo/bar', false, PathspecMatch::KIND_PREFIX);
        $assertMatch($t, $search, 'foo/target', false, PathspecMatch::KIND_PREFIX);
        $assertMatch($t, $search, 'foo/target', true, PathspecMatch::KIND_VERBATIM, true);
        $assertMatch($t, $search, 'foo/target/file', false, PathspecMatch::KIND_PREFIX, true);

        foreach ([false, true] as $leading) {
            $t->same(true, $search->directoryMatchesPrefix('foo/bar', $leading));
            $t->same(true, $search->directoryMatchesPrefix('foo', $leading));
        }
        foreach ([true, false] as $isDirectory) {
            $t->same(true, $search->canMatch('foo', $isDirectory));
            $t->same(true, $search->canMatch('foo/hi', $isDirectory));
        }
    },
    'upstream search starts_with' => static function (TestRunner $t) use ($assertMatch): void {
        $search = PathspecSearch::fromSpecs(['a/*']);

        $assertMatch($t, $search, 'a', false, null);
        $assertMatch($t, $search, 'a', true, null);
        $t->same(true, $search->canMatch('a', true));
        $t->same(true, $search->canMatch('a', false));
        $t->same(true, $search->canMatch('a', null));
        $t->same(true, $search->directoryMatchesPrefix('a', false));
        $t->same(false, $search->directoryMatchesPrefix('ab', false));
        $assertMatch($t, $search, 'a/file', null, PathspecMatch::KIND_WILDCARD);
    },
    'upstream search simplified_search_respects_must_be_dir' => static function (TestRunner $t) use ($assertMatch): void {
        $search = PathspecSearch::fromSpecs(['a/be/']);

        $assertMatch($t, $search, 'a/be/file', false, PathspecMatch::KIND_PREFIX);
        $t->same(false, $search->canMatch('any', false));
        $t->same(false, $search->canMatch('any', true));
        $t->same(false, $search->canMatch('any', null));
        $t->same(false, $search->canMatch('a/bei', null));
        $t->same(false, $search->canMatch('a', false));
        $t->same(true, $search->canMatch('a', true));
        $t->same(true, $search->canMatch('a', null));
        $t->same(true, $search->canMatch('a/be', true));
        $t->same(true, $search->canMatch('a/be', null));
        $t->same(false, $search->canMatch('a/be', false));
        $t->same(true, $search->canMatch('a/be/file', false));
        $t->same(false, $search->canMatch('a/b', false));
        $t->same(false, $search->canMatch('a/b', null));
        $assertMatch($t, $search, 'a/b', null, null);
        $t->same(false, $search->canMatch('a/b', true));
    },
    'upstream search simplified_search_respects_ignore_case' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs([':(icase)foo/**/bar']);

        $t->same(true, $search->canMatch('Foo', null));
        $t->same(true, $search->canMatch('foo', true));
        $t->same(true, $search->canMatch('FOO/', true));
    },
    'upstream search simplified_search_respects_all_excluded' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs([':(exclude)a/file', ':(exclude)b/file']);

        $t->same(true, $search->canMatch('b', null));
        $t->same(true, $search->canMatch('a', null));
        $t->same(true, $search->canMatch('c', null));
        $t->same(true, $search->canMatch('c/', null));
    },
    'upstream search simplified_search_wildcards' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['**/a*']);

        $t->same(true, $search->canMatch('a', null));
        $t->same(true, $search->canMatch('a/a', false));
        $t->same(true, $search->canMatch('a/a.o', false));
        $t->same(true, $search->canMatch('b-unrelated', null));
    },
    'upstream search simplified_search_wildcards_simple' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['dir/*']);

        foreach ([null, false, true] as $isDirectory) {
            $t->same(false, $search->canMatch('a', $isDirectory));
            $t->same(false, $search->canMatch('di', $isDirectory));
            $t->same(true, $search->canMatch('dir', $isDirectory));
            $t->same(true, $search->canMatch('dir/file', $isDirectory));
        }
    },
    'upstream search simplified_search_handles_nil' => static function (TestRunner $t): void {
        $nil = PathspecSearch::fromSpecs([':']);

        $t->same(true, $nil->canMatch('a', null));
        $t->same(true, $nil->canMatch('a', false));
        $t->same(true, $nil->canMatch('a', true));
        $t->same(true, $nil->canMatch('a/b', true));

        $exclude = PathspecSearch::fromSpecs([':(exclude)']);
        $t->same(false, $exclude->canMatch('a', null));
        $t->same(false, $exclude->canMatch('a', false));
        $t->same(false, $exclude->canMatch('a', true));
        $t->same(false, $exclude->canMatch('a/b', true));
    },
    'upstream search longest_common_directory_no_prefix' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['tests/a/', 'tests/b/', ':!*.sh']);

        $t->same('tests/', $search->commonPrefix());
        $t->same('', $search->prefixDirectory());
        $t->same('tests/', $search->longestCommonDirectory());
    },
    'upstream search longest_common_directory_with_prefix' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['tests/a/', 'tests/b/', ':!*.sh'], 'a/b');

        $t->same('a/b/tests/', $search->commonPrefix());
        $t->same('a/b', $search->prefixDirectory());
        $t->same('a/b/tests/', $search->longestCommonDirectory());
    },
    'upstream search init_with_exclude' => static function (TestRunner $t): void {
        $search = PathspecSearch::fromSpecs(['tests/', ':!*.sh']);
        $patterns = $search->patterns();

        $t->same(2, count($patterns));
        $t->same(true, $patterns[0]->exclude);
        $t->same('tests', $search->commonPrefix());
        $t->same('', $search->prefixDirectory());
        $t->same('tests', $search->longestCommonDirectory());
        $t->same(true, $search->canMatch('tests', true));
        $t->same(false, $search->canMatch('test', true));
        $t->same(false, $search->canMatch('outside-of-tests', null));
    },
    'upstream search no_pathspecs_respect_prefix' => static function (TestRunner $t) use ($assertMatch): void {
        $search = PathspecSearch::fromSpecs([], 'a');

        $t->same(1, count($search->patterns()));
        $assertMatch($t, $search, 'hello', null, null);
        $t->same(false, $search->canMatch('hello', null));

        $match = $search->match('a/b', null);
        $t->same('a', $match?->pattern->prefixDirectory());
        $t->same(PathspecMatch::KIND_PREFIX, $match?->kind);
        $t->same(true, $search->canMatch('a/', true));
        $t->same(true, $search->canMatch('a', true));
        $t->same(false, $search->canMatch('a', false));
        $t->same(true, $search->canMatch('a', null));
    },
    'upstream search prefixes_are_always_case_sensitive' => static function (TestRunner $t) use ($assertIncludedPaths): void {
        $items = [
            'FOO/BAR',
            'FOO/bAr',
            'FOO/bar',
            'fOo/BAR',
            'fOo/bAr',
            'fOo/bar',
            'foo/BAR',
            'foo/bAr',
            'foo/bar',
            'BAR',
            'bAr',
            'bar',
            '    ',
            '  hi  ',
        ];

        foreach ([
            [':(icase)bar', 'FOO', 'FOO', ['FOO/BAR', 'FOO/bAr', 'FOO/bar'], 'FOO'],
            [':(icase)bar', 'F', 'F', [], 'F'],
            [':(icase)bar', 'FO', 'FO', [], 'FO'],
            [':(icase)../bar', 'fOo', '', ['BAR', 'bAr', 'bar'], ''],
            ['../bar', 'fOo', 'bar', ['bar'], ''],
            ['    ', '', '    ', ['    '], ''],
            ['  hi*', '', '  hi', ['  hi  '], ''],
            [':(icase)../bar', 'fO', '', ['BAR', 'bAr', 'bar'], ''],
            [':(icase)../foo/bar', 'FOO', '', [
                'FOO/BAR',
                'FOO/bAr',
                'FOO/bar',
                'fOo/BAR',
                'fOo/bAr',
                'fOo/bar',
                'foo/BAR',
                'foo/bAr',
                'foo/bar',
            ], ''],
            ['../foo/bar', 'FOO', 'foo/bar', ['foo/bar'], ''],
            [':(icase)../foo/../fOo/bar', 'FOO', '', [
                'FOO/BAR',
                'FOO/bAr',
                'FOO/bar',
                'fOo/BAR',
                'fOo/bAr',
                'fOo/bar',
                'foo/BAR',
                'foo/bAr',
                'foo/bar',
            ], ''],
            ['../foo/../fOo/BAR', 'FOO', 'fOo/BAR', ['fOo/BAR'], ''],
        ] as [$spec, $prefix, $expectedCommonPrefix, $expected, $expectedPrefixDirectory]) {
            $search = PathspecSearch::fromSpecs([$spec], $prefix);
            $label = "{$spec} {$prefix}";

            $t->same($expectedCommonPrefix, $search->commonPrefix(), $label);
            $t->same($expectedPrefixDirectory, $search->prefixDirectory(), $label);
            $assertIncludedPaths($t, $search, $items, $expected, $label);
        }

        $search = PathspecSearch::fromSpecs([':(icase)bar'], 'FOO');
        $t->same(false, $search->canMatch('foo', true));
        $t->same(true, $search->canMatch('FOO', true));
        $t->same(false, $search->canMatch('FOO/ba', true));
        $t->same(true, $search->canMatch('FOO/bar', true));
    },
    'upstream search common_prefix' => static function (TestRunner $t): void {
        foreach ([
            [['foo/bar', ':(icase)foo/bar'], '', '', ''],
            [['foo/bar', 'foo'], '', 'foo', ''],
            [['foo/bar/baz', 'foo/bar/'], '', 'foo/bar', ''],
            [[':(icase)bar', ':(icase)bart'], 'foo', 'foo', 'foo'],
            [['bar', 'bart'], 'foo', 'foo/bar', 'foo'],
            [['bar', 'bart', 'ba'], 'foo', 'foo/ba', 'foo'],
        ] as [$specs, $prefix, $expectedCommonPrefix, $expectedPrefixDirectory]) {
            $search = PathspecSearch::fromSpecs($specs, $prefix);
            $label = json_encode($specs) . ' ' . $prefix;

            $t->same($expectedCommonPrefix, $search->commonPrefix(), $label);
            $t->same($expectedPrefixDirectory, $search->prefixDirectory(), $label);
        }
    },
];
