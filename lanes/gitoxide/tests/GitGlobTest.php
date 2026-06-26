<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitGlob;

$pattern = static function (string $text, int $mode, ?int $firstWildcardPosition): array {
    return [
        'text' => $text,
        'mode' => $mode,
        'firstWildcardPosition' => $firstWildcardPosition,
    ];
};

$actual = static function (?GitGlob $pattern): ?array {
    if ($pattern === null) {
        return null;
    }

    return [
        'text' => $pattern->text,
        'mode' => $pattern->mode,
        'firstWildcardPosition' => $pattern->firstWildcardPosition,
    ];
};

return [
    'upstream gix-glob parse/mod.rs mark_ends_with_pattern_specifically' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('*literal', GitGlob::NO_SUB_DIR | GitGlob::ENDS_WITH, 0),
            $actual(GitGlob::parse('*literal')),
        );
        $t->same(
            $pattern('**literal', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::parse('**literal')),
            "double-asterisk won't allow for fast comparisons"
        );
        $t->same(
            $pattern('*litera[l]', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::parse('*litera[l]')),
        );
        $t->same(
            $pattern('*litera?', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::parse('*litera?')),
        );
        $t->same(
            $pattern('*litera\\?', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::parse('*litera\\?')),
            "for now we don't handle escapes properly like git seems to do"
        );
    },

    'upstream gix-glob parse/mod.rs whitespace_only_is_ignored' => static function (TestRunner $t): void {
        $t->same(null, GitGlob::parse("\n\r\n\t\t   \n"));
    },

    'upstream gix-glob parse/mod.rs hash_symbols_are_not_special' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('# hello world', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('# hello world')),
        );
    },

    'upstream gix-glob parse/mod.rs backslashes_before_hashes_are_considered_an_escape_sequence' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('#hello', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('\\#hello')),
        );
    },

    'upstream gix-glob parse/mod.rs backslashes_are_part_of_the_pattern_if_not_in_specific_positions' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('\\hello\\world', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::parse('\\hello\\world')),
        );
    },

    'upstream gix-glob parse/mod.rs leading_exclamation_mark_negates_pattern' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('hello', GitGlob::NEGATIVE | GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('!hello')),
        );
        $t->same(
            $pattern('!hello', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::fromBytesWithoutNegation('!hello')),
            'negation can be disabled entirely'
        );
    },

    'upstream gix-glob parse/mod.rs leading_exclamation_marks_can_be_escaped_with_backslash' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('!hello', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('\\!hello')),
        );
        $t->same(
            $pattern('\\!hello', GitGlob::NO_SUB_DIR, 0),
            $actual(GitGlob::fromBytesWithoutNegation('\\!hello')),
            'negation can be disabled entirely, leaving escapes in place'
        );
    },

    'upstream gix-glob parse/mod.rs leading_slashes_mark_patterns_as_absolute' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('absolute', GitGlob::NO_SUB_DIR | GitGlob::ABSOLUTE, null),
            $actual(GitGlob::parse('/absolute')),
        );
        $t->same(
            $pattern('absolute/path', GitGlob::ABSOLUTE, null),
            $actual(GitGlob::parse('/absolute/path')),
        );
    },

    'upstream gix-glob parse/mod.rs absence_of_sub_directories_are_marked' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('a/b', 0, null),
            $actual(GitGlob::parse('a/b')),
        );
        $t->same(
            $pattern('ab', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('ab')),
        );
    },

    'upstream gix-glob parse/mod.rs trailing_slashes_are_marked_and_removed' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('dir', GitGlob::MUST_BE_DIR | GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('dir/')),
        );
        $t->same(
            $pattern('dir//', GitGlob::MUST_BE_DIR, null),
            $actual(GitGlob::parse('dir///')),
            'but only the last slash is removed'
        );
    },

    'upstream gix-glob parse/mod.rs trailing_spaces_are_taken_literally' => static function (TestRunner $t) use ($pattern, $actual): void {
        $t->same(
            $pattern('a   ', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('a   ')),
        );
        $t->same(
            $pattern("a\t\t  ", GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse("a\t\t  ")),
            'trailing tabs are not ignored'
        );
    },

    'upstream gix-glob parse/mod.rs trailing_spaces_can_be_escaped_to_be_literal' => static function (TestRunner $t) use ($pattern, $actual): void {
        $threeBackslashes = str_repeat('\\', 3);
        $twoBackslashes = str_repeat('\\', 2);

        $t->same(
            $pattern('a  \\ ', GitGlob::NO_SUB_DIR, 3),
            $actual(GitGlob::parse('a  \\ ')),
            'there is no escaping'
        );
        $t->same(
            $pattern('a  b  c ', GitGlob::NO_SUB_DIR, null),
            $actual(GitGlob::parse('a  b  c ')),
            'spaces in the middle are fine and also at the end'
        );
        $t->same(
            $pattern('a\\ \\ \\ ', GitGlob::NO_SUB_DIR, 1),
            $actual(GitGlob::parse('a\\ \\ \\ ')),
            "one can also escape every single space, but it's interpreted by the globbing engine"
        );
        $t->same(
            $pattern('a   \\', GitGlob::NO_SUB_DIR, 4),
            $actual(GitGlob::parse('a   \\')),
            'escaping nothing also works'
        );
        $t->same(
            $pattern('a   ' . $threeBackslashes . ' ', GitGlob::NO_SUB_DIR, 4),
            $actual(GitGlob::parse('a   ' . $threeBackslashes . ' ')),
            'strange things like these work too'
        );
        $t->same(
            $pattern('a   ' . $twoBackslashes . ' ', GitGlob::NO_SUB_DIR, 4),
            $actual(GitGlob::parse('a   ' . $twoBackslashes . ' ')),
            'strange things like these work as well'
        );
    },
];
