<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitGlob;

$pat = static function (string $pattern): GitGlob {
    $parsed = GitGlob::parse($pattern);
    if ($parsed === null) {
        throw new RuntimeException("Pattern did not parse: {$pattern}");
    }

    return $parsed;
};

$matchPath = static function (GitGlob $pattern, string $path, ?bool $isDirectory, string $case): bool {
    return $pattern->matchesRepoRelativePath(
        $path,
        GitGlob::basenameStartPosition($path),
        $isDirectory,
        $case,
        GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
    );
};

$matchFile = static fn (GitGlob $pattern, string $path, string $case): bool => $matchPath(
    $pattern,
    $path,
    false,
    $case,
);

$assertMatch = static function (
    TestRunner $t,
    bool $expected,
    GitGlob $pattern,
    string $path,
    ?bool $isDirectory,
    string $case,
    string $message = '',
) use ($matchPath): void {
    $t->same(
        $expected,
        $matchPath($pattern, $path, $isDirectory, $case),
        $message === '' ? (string) $pattern . ' against ' . $path : $message,
    );
};

$assertFileMatch = static function (
    TestRunner $t,
    bool $expected,
    GitGlob $pattern,
    string $path,
    string $case,
    string $message = '',
) use ($matchFile): void {
    $t->same(
        $expected,
        $matchFile($pattern, $path, $case),
        $message === '' ? (string) $pattern . ' against file ' . $path : $message,
    );
};

$multiMatch = static function (string $patternText, string $text): array {
    $pattern = GitGlob::parse($patternText);
    if ($pattern === null) {
        throw new RuntimeException("Pattern did not parse: {$patternText}");
    }

    return [
        $pattern->matchesRepoRelativePath(
            $text,
            GitGlob::basenameStartPosition($text),
            false,
            GitGlob::CASE_SENSITIVE,
            GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
        ),
        $pattern->matchesRepoRelativePath(
            $text,
            GitGlob::basenameStartPosition($text),
            false,
            GitGlob::CASE_FOLD,
            GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
        ),
        GitGlob::wildmatch($pattern->text, $text),
        GitGlob::wildmatch($pattern->text, $text, GitGlob::WILDMATCH_IGNORE_CASE),
    ];
};

$baselinePairs = static function (string $text): array {
    $pairs = [];
    foreach (preg_split('/\r?\n/', trim($text)) ?: [] as $line) {
        if ($line === '') {
            continue;
        }

        $split = strpos($line, ' ');
        if ($split === false) {
            throw new RuntimeException("Invalid generated baseline row: {$line}");
        }

        $pairs[] = [substr($line, 0, $split), ltrim(substr($line, $split + 1))];
    }

    return $pairs;
};

$generatedBaselineCorpus = static function () use ($baselinePairs): array {
    return [
        [
            true,
            GitGlob::CASE_SENSITIVE,
            $baselinePairs(<<<'BASELINE'
*/' XXX/'
\a a
\\[a-z] \a
\\? \a
\\* \
/*foo.txt barfoo.txt
*foo.txt bar/foo.txt
*.c mozilla-sha1/sha1.c
*.rs .rs
*hello.txt hello.txt
*hello.txt gareth_says_hello.txt
*hello.txt some/path/to/hello.txt
/*foo.txt foo.txt
*hello.txt some\path\to\hello.txt
*hello.txt an/absolute/path/to/hello.txt
*some/path/to/hello.txt some/path/to/hello.txt
a foo/a
a a
a*b a_b
a*b*c abc
a*b*c a_b_c
a*b*c a___b___c
abc*abc*abc abcabcabcabcabcabcabc
a*a*a*a*a*a*a*a*a aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
a*b[xyz]c*d abxcdbxcddd
☃ ☃
** abcde
** .asdf
** x/.asdf
a[0-9]b a0b
a[0-9]b a9b
a[!0-9]b a_b
[a-z123] 1
[1a-z23] 1
[123a-z] 1
[abc-] -
[-abc] -
[-a-c] b
[a-c-] b
[-] -
a[^0-9]b a_b
_[[]_[]]_[?]_[*]_!_ _[_]_?_*_!_
a,b a,b
\[ [
\? ?
\* *
aBcDeFg aBcDeFg
some/**/needle.txt some/needle.txt
some/**/needle.txt some/one/needle.txt
some/**/needle.txt some/one/two/needle.txt
some/**/needle.txt some/other/needle.txt
some/**/**/needle.txt some/needle.txt
some/**/**/needle.txt some/one/needle.txt
some/**/**/needle.txt some/one/two/needle.txt
some/**/**/needle.txt some/other/needle.txt
**/test one/two/test
**/test one/test
**/test test
/**/test one/two/test
/**/test one/test
/**/test test
**/.* .abc
**/.* abc/.abc
**/foo/bar foo/bar
.*/** .abc/abc
test/** test/
test/** test/one
test/** test/one/two
some/*/needle.txt some/one/needle.txt
abc/def abc/def
BASELINE),
        ],
        [
            false,
            GitGlob::CASE_SENSITIVE,
            $baselinePairs(<<<'BASELINE'
/*foo bam/barfoo/baz/bam
/*foo bar/bam/barfoo/baz/bam
foo foobaz
*/\' XXX/\'
/*foo bar/foo
/*foo bar/bazfoo
foo*bar foo/baz/bar
/*foo.txt hello/foo.txt
bar/foo baz/bar/foo
*hello.txt hello.txt-and-then-some
*hello.txt goodbye.txt
*some/path/to/hello.txt some/path/to/hello.txt-and-then-some
*some/path/to/hello.txt some/other/path/to/hello.txt
*some/path/to/hello.txt a/bigger/some/path/to/hello.txt
abc?def abc/def
a*b*c abcd
abc*abc*abc abcabcabcabcabcabcabca
a[0-9]b a_b
a[!0-9]b a0b
a[!0-9]b a9b
[!-] -
a[^0-9]b a0b
a[^0-9]b a9b
[^-] -
{a,b} a
{a,b} b
{[}],foo} }
{foo} foo
{*.foo,*.bar,*.wat} test.foo
{*.foo,*.bar,*.wat} test.bar
{*.foo,*.bar,*.wat} test.wat
abc*def abc/def
aBcDeFg abcdefg
aBcDeFg ABCDEFG
aBcDeFg AbCdEfG
some/**/needle.txt some/other/notthis.txt
some/**/**/needle.txt some/other/notthis.txt
/**/test one/notthis
/**/test notthis
**/.* ab.c
**/.* abc/ab.c
.*/** a.bc
.*/** abc/a.bc
./foo foo
**/foo foofoo
**/foo/bar foofoo/bar
/*.c mozilla-sha1/sha1.c
**/m4/ltoptions.m4 csharp/src/packages/repositories.config
some/*/needle.txt some/needle.txt
some/*/needle.txt some/one/two/needle.txt
some/*/needle.txt some/one/two/three/needle.txt
.*/** .abc
foo/** foo
{**/src/**,foo} abc/src/bar
{**/src/**,foo} foo
abc[/]def abc/def
BASELINE),
        ],
        [
            true,
            GitGlob::CASE_FOLD,
            $baselinePairs(<<<'BASELINE'
aBcDeFg  aBcDeFg
aBcDeFg  abcdefg
aBcDeFg  ABCDEFG
aBcDeFg  AbCdEfG
BASELINE),
        ],
    ];
};

return [
    'upstream gix-glob pattern/mod.rs display' => static function (TestRunner $t): void {
        $display = static fn (int $mode): string => (string) new GitGlob('a', $mode, null);

        $t->same('/a', $display(GitGlob::ABSOLUTE));
        $t->same('a/', $display(GitGlob::MUST_BE_DIR));
        $t->same('!a', $display(GitGlob::NEGATIVE));
        $t->same('!/a/', $display(GitGlob::ABSOLUTE | GitGlob::NEGATIVE | GitGlob::MUST_BE_DIR));
    },

    'upstream gix-glob pattern/matching.rs non_dirs_for_must_be_dir_patterns_are_ignored' => static function (TestRunner $t) use ($pat, $assertMatch): void {
        $pattern = $pat('hello/');

        $t->true($pattern->hasMode(GitGlob::MUST_BE_DIR));
        $t->same('hello', $pattern->text);
        $assertMatch($t, false, $pattern, 'hello', false, GitGlob::CASE_SENSITIVE, 'non-dirs never match a dir pattern');
        $assertMatch($t, true, $pattern, 'hello', true, GitGlob::CASE_SENSITIVE, 'dirs can match a dir pattern');
    },

    'upstream gix-glob pattern/matching.rs matches_of_absolute_paths_work' => static function (TestRunner $t): void {
        $pattern = '/hello/git';

        $t->same(true, GitGlob::wildmatch($pattern, $pattern));
        $t->same(true, GitGlob::wildmatch($pattern, $pattern, GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL));
    },

    'upstream gix-glob pattern/matching.rs basename_matches_from_end' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('foo');

        $assertFileMatch($t, true, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'FoOo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'Foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'Foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'barfoo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs absolute_basename_matches_only_from_beginning' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/foo');

        $assertFileMatch($t, true, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'bar/Foo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, true, $pattern, 'foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'Foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'bar/foo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs absolute_path_matches_only_from_beginning' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/bar/foo');

        $assertFileMatch($t, false, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, true, $pattern, 'bar/Foo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'bar/foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'bar/Foo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs absolute_path_with_recursive_glob_detects_mismatches_quickly' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/bar/foo/**');

        $assertFileMatch($t, false, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'bar/Fooo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'baz/bar/Foo', GitGlob::CASE_FOLD);
    },

    'upstream gix-glob pattern/matching.rs absolute_path_with_recursive_glob_can_do_case_insensitive_prefix_search' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/bar/foo/**');

        $assertFileMatch($t, false, $pattern, 'bar/Foo/match', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'bar/Foo/match', GitGlob::CASE_FOLD);
    },

    'upstream gix-glob pattern/matching.rs relative_path_does_not_match_from_end' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        foreach (['bar/foo', '/bar/foo'] as $patternText) {
            $pattern = $pat($patternText);
            $assertFileMatch($t, false, $pattern, 'FoO', GitGlob::CASE_FOLD);
            $assertFileMatch($t, true, $pattern, 'bar/Foo', GitGlob::CASE_FOLD);
            $assertFileMatch($t, false, $pattern, 'baz/bar/Foo', GitGlob::CASE_FOLD);
            $assertFileMatch($t, false, $pattern, 'foo', GitGlob::CASE_SENSITIVE);
            $assertFileMatch($t, true, $pattern, 'bar/foo', GitGlob::CASE_SENSITIVE);
            $assertFileMatch($t, false, $pattern, 'baz/bar/foo', GitGlob::CASE_SENSITIVE);
            $assertFileMatch($t, false, $pattern, 'Baz/bar/Foo', GitGlob::CASE_SENSITIVE);
        }
    },

    'upstream gix-glob pattern/matching.rs basename_glob_and_literal_is_ends_with' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('*foo');

        $assertFileMatch($t, true, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, true, $pattern, 'BarFoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'BarFoOo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'Foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'BarFoo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'barfoo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'barfooo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'bar/foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'bar/bazfoo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs special_cases_from_corpus' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $assertFileMatch($t, false, $pat('foo*bar'), 'foo/baz/bar', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pat('*some/path/to/hello.txt'), 'a/bigger/some/path/to/hello.txt', GitGlob::CASE_SENSITIVE);

        $pattern = $pat('/*foo.txt');
        $assertFileMatch($t, true, $pattern, 'hello-foo.txt', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'hello/foo.txt', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs absolute_basename_glob_and_literal_is_ends_with_in_basenames' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/*foo');

        $assertFileMatch($t, true, $pattern, 'FoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, true, $pattern, 'BarFoO', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'BarFoOo', GitGlob::CASE_FOLD);
        $assertFileMatch($t, false, $pattern, 'Foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'BarFoo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, true, $pattern, 'barfoo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'barfooo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs absolute_basename_glob_and_literal_is_glob_in_paths' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('/*foo');

        $assertFileMatch($t, false, $pattern, 'bar/foo', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'bar/bazfoo', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs negated_patterns_are_handled_by_caller' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('!foo');

        $assertFileMatch($t, true, $pattern, 'foo', GitGlob::CASE_SENSITIVE);
        $t->same(true, $pattern->isNegative());
    },

    'upstream gix-glob pattern/matching.rs names_do_not_automatically_match_entire_directories' => static function (TestRunner $t) use ($pat, $assertFileMatch): void {
        $pattern = $pat('foo');

        $assertFileMatch($t, false, $pattern, 'foobar', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'foo/bar', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'foo/bar/baz', GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs directory_patterns_do_not_match_files_within_a_directory_as_well_like_slash_star_star' => static function (TestRunner $t) use ($pat, $assertMatch): void {
        $pattern = $pat('dir/');
        foreach ([
            ['dir/file', GitGlob::CASE_SENSITIVE],
            ['base/dir/file', GitGlob::CASE_SENSITIVE],
            ['base/ndir/file', GitGlob::CASE_SENSITIVE],
            ['Dir/File', GitGlob::CASE_FOLD],
            ['Base/Dir/File', GitGlob::CASE_FOLD],
            ['dir2/file', GitGlob::CASE_SENSITIVE],
        ] as [$path, $case]) {
            $assertMatch($t, false, $pattern, $path, null, $case);
        }

        $pattern = $pat('dir/sub-dir/');
        foreach ([
            ['dir/sub-dir/file', GitGlob::CASE_SENSITIVE],
            ['dir/Sub-dir/File', GitGlob::CASE_FOLD],
            ['dir/Sub-dir2/File', GitGlob::CASE_FOLD],
        ] as [$path, $case]) {
            $assertMatch($t, false, $pattern, $path, null, $case);
        }
    },

    'upstream gix-glob pattern/matching.rs single_paths_match_anywhere' => static function (TestRunner $t) use ($pat, $assertFileMatch, $assertMatch): void {
        $pattern = $pat('target');
        $assertFileMatch($t, true, $pattern, 'dir/target', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'dir/atarget', GitGlob::CASE_SENSITIVE);
        $assertFileMatch($t, false, $pattern, 'dir/targeta', GitGlob::CASE_SENSITIVE);
        $assertMatch($t, true, $pattern, 'dir/target', true, GitGlob::CASE_SENSITIVE);

        $pattern = $pat('target/');
        $assertFileMatch($t, false, $pattern, 'dir/target', GitGlob::CASE_SENSITIVE);
        $assertMatch($t, false, $pattern, 'dir/target', null, GitGlob::CASE_SENSITIVE);
        $assertMatch($t, true, $pattern, 'dir/target', true, GitGlob::CASE_SENSITIVE);
        $assertMatch($t, false, $pattern, 'dir/target/', true, GitGlob::CASE_SENSITIVE);
    },

    'upstream gix-glob pattern/matching.rs compare_baseline_with_ours generated corpus' => static function (TestRunner $t) use ($pat, $generatedBaselineCorpus): void {
        $total = 0;
        foreach ($generatedBaselineCorpus() as [$expected, $case, $pairs]) {
            foreach ($pairs as [$patternText, $path]) {
                $total++;
                $pattern = $pat($patternText);
                $t->same(
                    $expected,
                    $pattern->matchesRepoRelativePath(
                        $path,
                        GitGlob::basenameStartPosition($path),
                        null,
                        $case,
                        GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
                    ),
                    "{$patternText} against {$path}",
                );
            }
        }

        $t->same(130, $total);
    },

    'upstream gix-glob pattern/matching.rs fuzzed_exponential_runaway_denial_of_service reduced cases' => static function (TestRunner $t) use ($pat, $assertMatch): void {
        foreach ([
            "*?[wxxxxxx\0!t[:rt]\x14*",
            "?[at(/\x1d\0\x04\x14[[[[:[\0\0\0\0\0\0\0\0\0\0/s\0\0\0*\0\0\0\0\0\0\0\0]\0\0\0\0\0\0\0\0\0",
            '[[:digit]ab]',
            '[[:]ab]',
            '[[:[:x]',
        ] as $patternText) {
            $assertMatch($t, false, $pat($patternText), 'relative/path', false, GitGlob::CASE_SENSITIVE);
        }
    },

    'upstream gix-glob pattern/matching.rs star_literal_scan_propagates_abort_to_avoid_pathological_retry' => static function (TestRunner $t) use ($pat, $assertMatch): void {
        $pattern = $pat("lud\xd1\xd6/////=////\xa4/'///////x*x**x*x*xxx*x**x;\x01R:\xfb\xfe\xffxxxx\x01\xaa:F");
        $path = "lud\xd1\xd6/////=////\xa4/'///////x*x**x*x*xxx*x**x;\x01*R*:\xfb\xfe\xffxxxx***\x01*\xaa*:F\xfb\xfe\xffxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx**\x01*x*\xaa*\xa6\xa6\xe1";

        $assertMatch($t, false, $pattern, $path, false, GitGlob::CASE_SENSITIVE);
        $assertMatch($t, false, $pattern, $path, false, GitGlob::CASE_FOLD);
    },

    'upstream gix-glob pattern/matching.rs pathological_recursive_globstar_matches_at_various_depths' => static function (TestRunner $t) use ($pat, $assertMatch): void {
        $pattern = $pat('***/**/****/***/onfig');

        foreach ([
            'onfig',
            'a/onfig',
            'a/b/onfig',
            'a/b/c/onfig',
            'a/b/c/d/onfig',
            'a/b/c/d/e/onfig',
            'dir/dir/dir/dir/dir/dir/onfig',
        ] as $path) {
            $assertMatch($t, true, $pattern, $path, false, GitGlob::CASE_SENSITIVE);
        }
    },

    'upstream gix-glob search/pattern.rs list::from_bytes_base' => static function (TestRunner $t): void {
        $list = GitGlob::searchListFromBytes('', 'a/b/source', null);
        $t->same(null, $list['base']);
        $t->same('a/b/source', $list['source']);

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('cwd unavailable');
        }
        $source = $cwd . '/a/b/source';
        $list = GitGlob::searchListFromBytes('', $source, $cwd);
        $t->same('a/b/', $list['base']);
        $t->same($source, $list['source']);

        $list = GitGlob::searchListFromBytes('', 'a/b/source', 'c/');
        $t->same(null, $list['base']);
        $t->same('a/b/source', $list['source']);
    },

    'upstream gix-glob search/pattern.rs list::strip_base_handle_recompute_basename_pos' => static function (TestRunner $t): void {
        $list = GitGlob::searchListFromBytes('', 'a/b/source', '');
        $t->same('a/b/', $list['base']);

        $t->same(
            ['file', null],
            GitGlob::stripBaseHandleRecomputeBasenamePosition('a/b/', 'a/b/file', 4, GitGlob::CASE_SENSITIVE),
        );
        $t->same(
            ['c/File', 2],
            GitGlob::stripBaseHandleRecomputeBasenamePosition('a/b/', 'a/B/c/File', 6, GitGlob::CASE_FOLD),
        );
    },

    'upstream gix-glob search/pattern.rs list::from_file_that_does_not_exist' => static function (TestRunner $t): void {
        $token = str_replace('.', '', uniqid('gitglob-', true));
        $missingNested = sys_get_temp_dir() . '/' . $token . '/pattern-file';
        $missingFile = sys_get_temp_dir() . '/' . $token . '-file';

        $t->same(null, GitGlob::searchListFromFile($missingNested));
        $t->same(null, GitGlob::searchListFromFile($missingFile));
    },

    'upstream gix-glob search/pattern.rs list::from_file_that_is_a_directory' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/' . str_replace('.', '', uniqid('gitglob-dir-', true));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create temp directory: {$dir}");
        }

        try {
            $t->same(null, GitGlob::searchListFromFile($dir));
        } finally {
            @rmdir($dir);
        }
    },

    'upstream gix-glob wildmatch/mod.rs brackets' => static function (TestRunner $t) use ($multiMatch): void {
        $t->same([false, true, false, true], $multiMatch('[B-a]', 'A'));
    },
];
