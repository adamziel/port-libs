<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitAttributes;
use PortLibs\Gitoxide\PathspecPattern;

$attr = static fn (string $name, string $state = GitAttributes::STATE_SET, ?string $value = null): array => [
    'name' => $name,
    'state' => $state,
    'value' => $value,
];

$expectedPattern = static function (
    string $path = '',
    bool $top = false,
    bool $exclude = false,
    bool $ignoreCase = false,
    bool $mustBeDirectory = false,
    string $searchMode = PathspecPattern::SEARCH_SHELL_GLOB,
    bool $nil = false,
    array $attributes = [],
    string $prefixDirectory = '',
): array {
    return [
        'path' => $path,
        'top' => $top,
        'exclude' => $exclude,
        'ignoreCase' => $ignoreCase,
        'mustBeDirectory' => $mustBeDirectory,
        'searchMode' => $searchMode,
        'nil' => $nil,
        'prefixDirectory' => $prefixDirectory,
        'attributes' => $attributes,
    ];
};

$actualPattern = static fn (PathspecPattern $pattern): array => [
    'path' => $pattern->path,
    'top' => $pattern->top,
    'exclude' => $pattern->exclude,
    'ignoreCase' => $pattern->ignoreCase,
    'mustBeDirectory' => $pattern->mustBeDirectory,
    'searchMode' => $pattern->searchMode,
    'nil' => $pattern->nil,
    'prefixDirectory' => $pattern->prefixDirectory(),
    'attributes' => $pattern->attributes,
];

$assertParsed = static function (TestRunner $t, string $input, array $expected, array $options = []) use ($actualPattern): void {
    $pattern = PathspecPattern::parse(
        $input,
        literalDefault: $options['literalDefault'] ?? false,
        defaultSearchMode: $options['defaultSearchMode'] ?? PathspecPattern::SEARCH_SHELL_GLOB,
        defaultIgnoreCase: $options['defaultIgnoreCase'] ?? false,
        defaultTop: $options['defaultTop'] ?? false,
        defaultExclude: $options['defaultExclude'] ?? false,
    );
    $t->same($expected, $actualPattern($pattern), $input);
};

$assertInvalid = static function (TestRunner $t, string $input): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => PathspecPattern::parse($input),
    );
};

$assertNormalized = static function (
    TestRunner $t,
    string $input,
    string $prefix,
    string $root,
    string $expectedPath,
    string $expectedPrefix,
    bool $expectedNil = false,
): void {
    $pattern = PathspecPattern::parse($input)->normalize($prefix, $root);
    $t->same([
        'path' => $expectedPath,
        'prefixDirectory' => $expectedPrefix,
        'nil' => $expectedNil,
    ], [
        'path' => $pattern->path,
        'prefixDirectory' => $pattern->prefixDirectory(),
        'nil' => $pattern->nil,
    ], $input);
};

$assertNormalizeError = static function (
    TestRunner $t,
    string $input,
    string $prefix,
    string $root,
    string $expectedMessage,
): void {
    try {
        PathspecPattern::parse($input)->normalize($prefix, $root);
    } catch (InvalidArgumentException $exception) {
        $t->same($expectedMessage, $exception->getMessage(), $input);

        return;
    }

    throw new RuntimeException("Expected InvalidArgumentException was not thrown for {$input}");
};

return [
    'upstream parse valid repeated_matcher_keywords' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            [':(glob,glob)', $expectedPattern(searchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB)],
            [':(literal,literal)', $expectedPattern(searchMode: PathspecPattern::SEARCH_LITERAL)],
            [':(top,top)', $expectedPattern(top: true)],
            [':(icase,icase)', $expectedPattern(ignoreCase: true)],
            [':(attr,attr)', $expectedPattern()],
            [':!^(exclude,exclude)', $expectedPattern(exclude: true)],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid glob_negations_are_always_literal' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            ['!a', $expectedPattern('!a')],
            ['\\!a', $expectedPattern('\\!a')],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid literal_default_prevents_parsing' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        $input = ':(literal)f[o][o]';
        $assertParsed($t, ':', $expectedPattern(':', exclude: true, searchMode: PathspecPattern::SEARCH_LITERAL), [
            'literalDefault' => true,
            'defaultSearchMode' => PathspecPattern::SEARCH_PATH_AWARE_GLOB,
            'defaultExclude' => true,
        ]);
        $assertParsed($t, $input, $expectedPattern($input, top: true, searchMode: PathspecPattern::SEARCH_LITERAL), [
            'literalDefault' => true,
            'defaultSearchMode' => PathspecPattern::SEARCH_LITERAL,
            'defaultTop' => true,
        ]);
        $assertParsed($t, $input, $expectedPattern('f[o][o]', top: true, searchMode: PathspecPattern::SEARCH_LITERAL), [
            'defaultSearchMode' => PathspecPattern::SEARCH_LITERAL,
            'defaultTop' => true,
        ]);
    },
    'upstream parse valid there_is_no_pathspec_pathspec' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        $assertParsed($t, ':', $expectedPattern(nil: true));
        $assertParsed($t, ':', $expectedPattern(nil: true), [
            'defaultSearchMode' => PathspecPattern::SEARCH_PATH_AWARE_GLOB,
            'defaultExclude' => true,
        ]);
    },
    'upstream parse valid defaults_are_used' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        $assertParsed($t, '.', $expectedPattern('.', exclude: true, searchMode: PathspecPattern::SEARCH_LITERAL), [
            'defaultSearchMode' => PathspecPattern::SEARCH_LITERAL,
            'defaultExclude' => true,
        ]);
    },
    'upstream parse valid literal_from_defaults_is_overridden_by_element_glob' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        $assertParsed($t, ':(glob)*override', $expectedPattern('*override', searchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB), [
            'defaultSearchMode' => PathspecPattern::SEARCH_LITERAL,
        ]);
    },
    'upstream parse valid glob_from_defaults_is_overridden_by_element_glob' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        $assertParsed($t, ':(literal)*override', $expectedPattern('*override', searchMode: PathspecPattern::SEARCH_LITERAL), [
            'defaultSearchMode' => PathspecPattern::SEARCH_PATH_AWARE_GLOB,
        ]);
    },
    'upstream parse valid empty_signatures' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            ['.', $expectedPattern('.')],
            ['some/path', $expectedPattern('some/path')],
            [':some/path', $expectedPattern('some/path')],
            [':()some/path', $expectedPattern('some/path')],
            ['::some/path', $expectedPattern('some/path')],
            [':::some/path', $expectedPattern(':some/path')],
            [':():some/path', $expectedPattern(':some/path')],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid whitespace_in_pathspec' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            [' some/path', $expectedPattern(' some/path')],
            ['some/ path', $expectedPattern('some/ path')],
            ['some/path ', $expectedPattern('some/path ')],
            [': some/path', $expectedPattern(' some/path')],
            [': !some/path', $expectedPattern(' !some/path')],
            [': :some/path', $expectedPattern(' :some/path')],
            [': ()some/path', $expectedPattern(' ()some/path')],
            [':! some/path', $expectedPattern(' some/path', exclude: true)],
            [':!!some/path', $expectedPattern('some/path', exclude: true)],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid short_signatures' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            [':/some/path', $expectedPattern('some/path', top: true)],
            [':^some/path', $expectedPattern('some/path', exclude: true)],
            [':!some/path', $expectedPattern('some/path', exclude: true)],
            [':/!some/path', $expectedPattern('some/path', top: true, exclude: true)],
            [':!/^/:some/path', $expectedPattern('some/path', top: true, exclude: true)],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid trailing_slash_is_turned_into_magic_signature_and_removed' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            ['a/b/', $expectedPattern('a/b', mustBeDirectory: true)],
            ['a/', $expectedPattern('a', mustBeDirectory: true)],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid signatures_and_searchmodes' => static function (TestRunner $t) use ($assertParsed, $expectedPattern): void {
        foreach ([
            [':(top)', $expectedPattern(top: true)],
            [':(icase)', $expectedPattern(ignoreCase: true)],
            [':(attr)', $expectedPattern()],
            [':(exclude)', $expectedPattern(exclude: true)],
            [':(literal)', $expectedPattern(searchMode: PathspecPattern::SEARCH_LITERAL)],
            [':(glob)', $expectedPattern(searchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB)],
            [':(top,exclude)', $expectedPattern(top: true, exclude: true)],
            [':(icase,literal)', $expectedPattern(ignoreCase: true, searchMode: PathspecPattern::SEARCH_LITERAL)],
            [':!(literal)some/*path', $expectedPattern('some/*path', exclude: true, searchMode: PathspecPattern::SEARCH_LITERAL)],
            [':(top,literal,icase,attr,exclude)some/path', $expectedPattern('some/path', top: true, exclude: true, ignoreCase: true, searchMode: PathspecPattern::SEARCH_LITERAL)],
            [':(top,glob,icase,attr,exclude)some/path', $expectedPattern('some/path', top: true, exclude: true, ignoreCase: true, searchMode: PathspecPattern::SEARCH_PATH_AWARE_GLOB)],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid attributes_in_signature' => static function (TestRunner $t) use ($assertParsed, $expectedPattern, $attr): void {
        foreach ([
            [':(attr:someAttr)', $expectedPattern(attributes: [$attr('someAttr')])],
            [':(attr:!someAttr)', $expectedPattern(attributes: [$attr('someAttr', GitAttributes::STATE_UNSPECIFIED)])],
            [':(attr:-someAttr)', $expectedPattern(attributes: [$attr('someAttr', GitAttributes::STATE_UNSET)])],
            [':(attr:someAttr=value)', $expectedPattern(attributes: [$attr('someAttr', GitAttributes::STATE_VALUE, 'value')])],
            [':(attr:a=one b=)', $expectedPattern(attributes: [$attr('a', GitAttributes::STATE_VALUE, 'one'), $attr('b', GitAttributes::STATE_VALUE, '')])],
            [':(attr:a= b=two)', $expectedPattern(attributes: [$attr('a', GitAttributes::STATE_VALUE, ''), $attr('b', GitAttributes::STATE_VALUE, 'two')])],
            [':(attr:a=one b=two)', $expectedPattern(attributes: [$attr('a', GitAttributes::STATE_VALUE, 'one'), $attr('b', GitAttributes::STATE_VALUE, 'two')])],
            [':(attr:a=one   b=two)', $expectedPattern(attributes: [$attr('a', GitAttributes::STATE_VALUE, 'one'), $attr('b', GitAttributes::STATE_VALUE, 'two')])],
            [':(attr:someAttr anotherAttr)', $expectedPattern(attributes: [$attr('someAttr'), $attr('anotherAttr')])],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse valid attributes_with_escape_chars_in_state_values' => static function (TestRunner $t) use ($assertParsed, $expectedPattern, $attr): void {
        foreach ([
            [':(attr:v=one\\-)', $expectedPattern(attributes: [$attr('v', GitAttributes::STATE_VALUE, 'one-')])],
            [':(attr:v=one\\_)', $expectedPattern(attributes: [$attr('v', GitAttributes::STATE_VALUE, 'one_')])],
            [':(attr:v=one\\,)', $expectedPattern(attributes: [$attr('v', GitAttributes::STATE_VALUE, 'one,')])],
            [':(attr:v=one\\,two\\,three)', $expectedPattern(attributes: [$attr('v', GitAttributes::STATE_VALUE, 'one,two,three')])],
            [':(attr:a=\\d b= c=\\d)', $expectedPattern(attributes: [$attr('a', GitAttributes::STATE_VALUE, 'd'), $attr('b', GitAttributes::STATE_VALUE, ''), $attr('c', GitAttributes::STATE_VALUE, 'd')])],
        ] as [$input, $expected]) {
            $assertParsed($t, $input, $expected);
        }
    },
    'upstream parse invalid empty_input' => static function (TestRunner $t) use ($assertInvalid): void {
        $assertInvalid($t, '');
    },
    'upstream parse invalid invalid_short_signatures' => static function (TestRunner $t) use ($assertInvalid): void {
        foreach ([
            ':"()',
            ':#()',
            ':%()',
            ':&()',
            ":'()",
            ':,()',
            ':-()',
            ':;()',
            ':<()',
            ':=()',
            ':>()',
            ':@()',
            ':_()',
            ':`()',
            ':~()',
        ] as $input) {
            $assertInvalid($t, $input);
        }
    },
    'upstream parse invalid invalid_keywords' => static function (TestRunner $t) use ($assertInvalid): void {
        foreach ([
            ':( )some/path',
            ':(tp)some/path',
            ':(top, exclude)some/path',
            ':(top,exclude,icse)some/path',
        ] as $input) {
            $assertInvalid($t, $input);
        }
    },
    'upstream parse invalid invalid_attributes' => static function (TestRunner $t) use ($assertInvalid): void {
        foreach ([
            ':(attr:+invalidAttr)some/path',
            ':(attr:validAttr +invalidAttr)some/path',
            ':(attr:+invalidAttr,attr:valid)some/path',
            ':(attr:inva\\lid)some/path',
        ] as $input) {
            $assertInvalid($t, $input);
        }
    },
    'upstream parse invalid invalid_attribute_values' => static function (TestRunner $t) use ($assertInvalid): void {
        foreach ([
            ':(attr:v=inva#lid)some/path',
            ':(attr:v=inva\\\\lid)some/path',
            ':(attr:v=invalid\\\\)some/path',
            ':(attr:v=invalid\\#)some/path',
            ':(attr:v=inva\\=lid)some/path',
            ':(attr:a=valid b=inva\\#lid)some/path',
            ":(attr:v=val\xEF\xBF\xBD\xEF\xBF\xBD)",
            ":(attr:pr=pre\xEF\xBF\xBD\xEF\xBF\xBDx:,)\xEF\xBF\xBD",
        ] as $input) {
            $assertInvalid($t, $input);
        }
    },
    'upstream parse invalid escape_character_at_end_of_attribute_value' => static function (TestRunner $t) use ($assertInvalid): void {
        foreach ([
            ':(attr:v=invalid\\)some/path',
            ':(attr:v=invalid\\ )some/path',
            ':(attr:v=invalid\\ valid)some/path',
        ] as $input) {
            $assertInvalid($t, $input);
        }
    },
    'upstream parse invalid empty_attribute_specification' => static function (TestRunner $t) use ($assertInvalid): void {
        $assertInvalid($t, ':(attr:)');
    },
    'upstream parse invalid multiple_attribute_specifications' => static function (TestRunner $t) use ($assertInvalid): void {
        $assertInvalid($t, ':(attr:one,attr:two)some/path');
    },
    'upstream parse invalid missing_parentheses' => static function (TestRunner $t) use ($assertInvalid): void {
        $assertInvalid($t, ':(top');
    },
    'upstream parse invalid glob_and_literal_keywords_present' => static function (TestRunner $t) use ($assertInvalid): void {
        $assertInvalid($t, ':(glob,literal)some/path');
    },
    'upstream normalize consuming_the_entire_prefix_does_not_lead_to_a_single_dot' => static function (TestRunner $t) use ($assertNormalized): void {
        $assertNormalized($t, '..', 'a', '', '.', '', true);
    },
    'upstream normalize removes_relative_path_components' => static function (TestRunner $t) use ($assertNormalized): void {
        foreach ([
            ['..', 'a', ''],
            ['c', 'a/b/c', 'a/b'],
            ['../c', 'a/c', 'a'],
            ['../b/c', 'a/b/c', 'a'],
            ['../*c/d', 'a/*c/d', 'a'],
            ['../../c/d', 'c/d', ''],
            ['../../c/d/', 'c/d', ''],
            ['./c', 'a/b/c', 'a/b'],
            ['../../c', 'c', ''],
            ['../..', '.', ''],
            ['../././c', 'a/c', 'a'],
            ['././/./c', 'a/b/c', 'a/b'],
            ['././/./c/', 'a/b/c', 'a/b'],
            ['././/./../c/d/', 'a/c/d', 'a'],
        ] as [$input, $expectedPath, $expectedPrefix]) {
            $assertNormalized($t, $input, 'a/b', '', $expectedPath, $expectedPrefix, $expectedPath === '.');
        }
    },
    'upstream normalize single_dot_is_special_and_directory_is_implied_without_trailing_slash' => static function (TestRunner $t) use ($assertNormalized): void {
        foreach ([
            ['.', '.'],
            ['./', '.'],
        ] as [$input, $expectedPath]) {
            $assertNormalized($t, $input, '', '/repo', $expectedPath, '', true);
        }
    },
    'upstream normalize absolute_path_made_relative' => static function (TestRunner $t) use ($assertNormalized): void {
        foreach ([
            ['/repo/a', 'a', ''],
            ['/repo/a/..//.///b', 'b', ''],
            ['/repo/a/', 'a', 'a'],
            ['/repo/*/', '*', '*'],
            ['/repo/a/b', 'a/b', 'a'],
            ['/repo/*/b', '*/b', '*'],
            ['/repo/a/*/', 'a/*', 'a/*'],
            ['/repo/a/b/', 'a/b', 'a/b'],
            ['/repo/a/b/*', 'a/b/*', 'a/b'],
            ['/repo/a/b/c/..', 'a/b', 'a'],
        ] as [$input, $expectedPath, $expectedPrefix]) {
            $assertNormalized($t, $input, '', '/repo', $expectedPath, $expectedPrefix);
        }
    },
    'upstream normalize relative_top_patterns_ignore_the_prefix' => static function (TestRunner $t) use ($assertNormalized): void {
        $assertNormalized($t, ':(top)c', 'a/b', '', 'c', '');
    },
    'upstream normalize absolute_top_patterns_ignore_the_prefix_but_are_made_relative' => static function (TestRunner $t) use ($assertNormalized): void {
        $assertNormalized($t, ':(top)/a/b', 'prefix-ignored', '/a', 'b', '');
    },
    'upstream normalize relative_path_breaks_out_of_working_tree' => static function (TestRunner $t) use ($assertNormalizeError): void {
        $assertNormalizeError($t, '../a', '', '', "The path '../a' leaves the repository");
        $assertNormalizeError($t, '../../b', 'a', '', "The path 'a/../../b' leaves the repository");
    },
    'upstream normalize absolute_path_breaks_out_of_working_tree' => static function (TestRunner $t) use ($assertNormalizeError): void {
        $assertNormalizeError($t, '/path/to/repo/..///./a', '', '/path/to/repo', "The path '..///./a' leaves the repository");
        $assertNormalizeError($t, '/path/to/repo/../../../dev', '', '/path/to/repo', "The path '../../../dev' leaves the repository");
    },
    'upstream normalize absolute_path_escapes_worktree' => static function (TestRunner $t) use ($assertNormalizeError): void {
        $assertNormalizeError($t, '/dev', '', '/path/to/repo', "The path '/dev' is not inside of the worktree '/path/to/repo'");
    },
];
