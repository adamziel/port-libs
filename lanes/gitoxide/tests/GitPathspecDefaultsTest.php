<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitPathspecDefaults;

$defaultsView = static fn (GitPathspecDefaults $defaults): array => [
    'signature' => $defaults->signature,
    'searchMode' => $defaults->searchMode,
    'literal' => $defaults->literal,
];

return [
    'literal only combines with icase' => static function (TestRunner $t) use ($defaultsView): void {
        $t->same([
            'signature' => GitPathspecDefaults::MAGIC_SIGNATURE_ICASE,
            'searchMode' => GitPathspecDefaults::SEARCH_LITERAL,
            'literal' => true,
        ], $defaultsView(GitPathspecDefaults::fromEnvironment([
            'GIT_LITERAL_PATHSPECS' => 'true',
            'GIT_ICASE_PATHSPECS' => '1',
            'GIT_NOGLOB_PATHSPECS' => 'yes',
        ])));

        $t->same([
            'signature' => GitPathspecDefaults::MAGIC_SIGNATURE_NONE,
            'searchMode' => GitPathspecDefaults::SEARCH_LITERAL,
            'literal' => true,
        ], $defaultsView(GitPathspecDefaults::fromEnvironment([
            'GIT_LITERAL_PATHSPECS' => 'true',
            'GIT_ICASE_PATHSPECS' => 'false',
            'GIT_GLOB_PATHSPECS' => 'yes',
        ])));
    },
    'nothing is set then it is like the default impl' => static function (TestRunner $t) use ($defaultsView): void {
        $t->same([
            'signature' => GitPathspecDefaults::MAGIC_SIGNATURE_NONE,
            'searchMode' => GitPathspecDefaults::SEARCH_SHELL_GLOB,
            'literal' => false,
        ], $defaultsView(GitPathspecDefaults::fromEnvironment([])));
    },
    'glob and noglob cause error' => static function (TestRunner $t): void {
        try {
            GitPathspecDefaults::fromEnvironment([
                'GIT_GLOB_PATHSPECS' => '1',
                'GIT_NOGLOB_PATHSPECS' => 'yes',
            ]);
        } catch (InvalidArgumentException $exception) {
            $t->same('Glob and no-glob settings are mutually exclusive', $exception->getMessage());

            return;
        }

        throw new RuntimeException('Expected InvalidArgumentException was not thrown');
    },
    'noglob works' => static function (TestRunner $t) use ($defaultsView): void {
        $t->same([
            'signature' => GitPathspecDefaults::MAGIC_SIGNATURE_NONE,
            'searchMode' => GitPathspecDefaults::SEARCH_LITERAL,
            'literal' => false,
        ], $defaultsView(GitPathspecDefaults::fromEnvironment([
            'GIT_GLOB_PATHSPECS' => '0',
            'GIT_NOGLOB_PATHSPECS' => 'true',
        ])));
    },
    'glob works' => static function (TestRunner $t) use ($defaultsView): void {
        $t->same([
            'signature' => GitPathspecDefaults::MAGIC_SIGNATURE_NONE,
            'searchMode' => GitPathspecDefaults::SEARCH_PATH_AWARE_GLOB,
            'literal' => false,
        ], $defaultsView(GitPathspecDefaults::fromEnvironment([
            'GIT_GLOB_PATHSPECS' => 'yes',
        ])));
    },
];
