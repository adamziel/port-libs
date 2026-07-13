<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitGlob;
use PortLibs\Gitoxide\GitIgnore;

$fixture = static function (string $name): string {
    return (string) file_get_contents(
        dirname(__DIR__, 3) . '/.upstream-cache/gitoxide/gix-ignore/tests/fixtures/ignore/' . $name
    );
};

$pattern = static function (string $text, int $mode, int $line, string $kind = GitIgnore::KIND_EXPENDABLE): array {
    return [
        'pattern' => [
            'text' => $text,
            'mode' => $mode,
        ],
        'line' => $line,
        'kind' => $kind,
    ];
};

$flattenPattern = static function (GitGlob $pattern): array {
    return [
        'text' => $pattern->text,
        'mode' => $pattern->mode,
    ];
};

$flattenParsed = static function (array $parsed) use ($flattenPattern): array {
    return array_map(
        static fn (array $entry): array => [
            'pattern' => $flattenPattern($entry['pattern']),
            'line' => $entry['line'],
            'kind' => $entry['kind'],
        ],
        $parsed,
    );
};

$parseFlat = static fn (string $contents, bool $supportPrecious = false): array => $flattenParsed(
    GitIgnore::parse($contents, $supportPrecious),
);

$parseOne = static function (string $contents) use ($parseFlat): array {
    $actual = $parseFlat($contents);
    if (count($actual) !== 1) {
        throw new RuntimeException(var_export($contents, true) . ' should yield exactly one ignore pattern');
    }

    return $actual[0];
};

$flattenMatch = static function (?array $match) use ($flattenPattern): ?array {
    if ($match === null) {
        return null;
    }

    return [
        'pattern' => $flattenPattern($match['pattern']),
        'source' => $match['source'],
        'sequenceNumber' => $match['sequenceNumber'],
        'kind' => $match['kind'],
    ];
};

$patternToMatch = static function (GitGlob $pattern, int $sequenceNumber, string $kind) use ($flattenPattern): array {
    return [
        'pattern' => $flattenPattern($pattern),
        'source' => null,
        'sequenceNumber' => $sequenceNumber,
        'kind' => $kind,
    ];
};

$patternListsSnapshot = static function (GitIgnore $ignore) use ($flattenPattern): array {
    return array_map(
        static fn (array $list): array => array_map(
            static fn (array $mapping): array => [
                'pattern' => $flattenPattern($mapping['pattern']),
                'sequenceNumber' => $mapping['sequenceNumber'],
                'kind' => $mapping['kind'],
            ],
            $list,
        ),
        $ignore->patternLists(),
    );
};

return [
    'upstream gix-ignore ignore/parse.rs precious' => static function (TestRunner $t) use ($fixture, $parseFlat, $pattern): void {
        $input = $fixture('precious.txt');

        $t->same([
            $pattern('.config', GitGlob::NO_SUB_DIR, 1, GitIgnore::KIND_PRECIOUS),
            $pattern('$starts-with-dollar', GitGlob::NO_SUB_DIR, 2),
            $pattern('*.html', GitGlob::NO_SUB_DIR | GitGlob::ENDS_WITH, 4, GitIgnore::KIND_PRECIOUS),
            $pattern('foo.html', GitGlob::NO_SUB_DIR | GitGlob::NEGATIVE, 6),
            $pattern('!/*', 0, 12, GitIgnore::KIND_PRECIOUS),
        ], $parseFlat($input, true));

        $t->same([
            $pattern('$.config', GitGlob::NO_SUB_DIR, 1),
            $pattern('$starts-with-dollar', GitGlob::NO_SUB_DIR, 2),
            $pattern('$*.html', GitGlob::NO_SUB_DIR, 4),
            $pattern('foo.html', GitGlob::NO_SUB_DIR | GitGlob::NEGATIVE, 6),
            $pattern('$!/*', 0, 12),
        ], $parseFlat($input, false));
    },

    'upstream gix-ignore ignore/parse.rs byte_order_marks_are_no_patterns' => static function (TestRunner $t) use ($parseFlat, $pattern): void {
        $t->same([
            $pattern('hello', GitGlob::NO_SUB_DIR, 1),
        ], $parseFlat("\xEF\xBB\xBFhello"));
    },

    'upstream gix-ignore ignore/parse.rs line_numbers_are_counted_correctly' => static function (TestRunner $t) use ($fixture, $parseFlat, $pattern): void {
        $t->same([
            $pattern('*.[oa]', GitGlob::NO_SUB_DIR, 2),
            $pattern('*.html', GitGlob::NO_SUB_DIR | GitGlob::ENDS_WITH, 5),
            $pattern('foo.html', GitGlob::NO_SUB_DIR | GitGlob::NEGATIVE, 8),
            $pattern('*', GitGlob::NO_SUB_DIR | GitGlob::ENDS_WITH | GitGlob::ABSOLUTE, 11),
            $pattern('foo', GitGlob::NEGATIVE | GitGlob::NO_SUB_DIR | GitGlob::ABSOLUTE, 12),
            $pattern('foo/*', GitGlob::ABSOLUTE, 13),
            $pattern('foo/bar', GitGlob::ABSOLUTE | GitGlob::NEGATIVE, 14),
        ], $parseFlat($fixture('various.txt')));
    },

    'upstream gix-ignore ignore/parse.rs line_endings_can_be_windows_or_unix' => static function (TestRunner $t) use ($parseFlat, $pattern): void {
        $t->same([
            $pattern('unix', GitGlob::NO_SUB_DIR, 1),
            $pattern('windows', GitGlob::NO_SUB_DIR, 2),
            $pattern('last', GitGlob::NO_SUB_DIR, 3),
        ], $parseFlat("unix\nwindows\r\nlast"));
    },

    'upstream gix-ignore ignore/parse.rs comments_are_ignored_as_well_as_empty_ones' => static function (TestRunner $t) use ($parseFlat): void {
        $t->same([], $parseFlat('# hello world'));
        $t->same([], $parseFlat("\n\r\n\t\t   \n"));
    },

    'upstream gix-ignore ignore/parse.rs backslashes_before_hashes_are_no_comments' => static function (TestRunner $t) use ($parseFlat, $pattern): void {
        $t->same([
            $pattern('#hello', GitGlob::NO_SUB_DIR, 1),
        ], $parseFlat('\\#hello'));
    },

    'upstream gix-ignore ignore/parse.rs trailing_spaces_can_be_escaped_to_be_literal' => static function (TestRunner $t) use ($parseOne, $pattern): void {
        $t->same($pattern('a  \\ ', GitGlob::NO_SUB_DIR, 1), $parseOne('a  \\ '));
        $t->same($pattern('a  b  c', GitGlob::NO_SUB_DIR, 1), $parseOne('a  b  c '));
        $t->same($pattern('a\\ \\ \\ ', GitGlob::NO_SUB_DIR, 1), $parseOne('a\\ \\ \\ '));
        $t->same($pattern('a \\ ', GitGlob::NO_SUB_DIR, 1), $parseOne('a \\  '));
        $t->same($pattern('a   \\', GitGlob::NO_SUB_DIR, 1), $parseOne('a   \\'));
        $t->same($pattern('a   \\\\\\ ', GitGlob::NO_SUB_DIR, 1), $parseOne('a   \\\\\\ '));
        $t->same($pattern('a   \\\\', GitGlob::NO_SUB_DIR, 1), $parseOne('a   \\\\ '));
    },

    'upstream gix-ignore ignore/search.rs from_overrides_with_precious' => static function (TestRunner $t) use ($flattenMatch, $patternToMatch): void {
        $group = GitIgnore::fromOverrides(['$s?mple', 'pattern/'], supportPrecious: true);

        $t->same(
            $patternToMatch(GitGlob::parse('s?mple'), 1, GitIgnore::KIND_PRECIOUS),
            $flattenMatch($group->patternMatchingRelativePath('Simple', null, GitIgnore::CASE_FOLD)),
        );
    },

    'upstream gix-ignore ignore/search.rs from_overrides_with_excludes' => static function (TestRunner $t) use ($flattenMatch, $patternToMatch): void {
        $group = GitIgnore::fromOverrides(['$simple', '!simple', 'pattern/']);

        $t->same(
            $patternToMatch(GitGlob::parse('!simple'), 2, GitIgnore::KIND_EXPENDABLE),
            $flattenMatch($group->patternMatchingRelativePath('Simple', null, GitIgnore::CASE_FOLD)),
        );
    },

    'upstream gix-ignore ignore/search.rs from_overrides' => static function (TestRunner $t) use ($flattenMatch, $patternToMatch, $patternListsSnapshot): void {
        $group = GitIgnore::fromOverrides(['simple', 'pattern/']);

        $t->same(
            $patternToMatch(GitGlob::parse('simple'), 1, GitIgnore::KIND_EXPENDABLE),
            $flattenMatch($group->patternMatchingRelativePath('Simple', null, GitIgnore::CASE_FOLD)),
        );
        $t->same(
            $patternToMatch(GitGlob::parse('pattern/'), 2, GitIgnore::KIND_EXPENDABLE),
            $flattenMatch($group->patternMatchingRelativePath('pattern', true, GitIgnore::CASE_SENSITIVE)),
        );
        $t->same(1, count($group->patternLists()));
        $t->same(
            $patternListsSnapshot(GitIgnore::fromOverrides(['simple', 'pattern/']))[0],
            $patternListsSnapshot($group)[0],
        );
    },
];
