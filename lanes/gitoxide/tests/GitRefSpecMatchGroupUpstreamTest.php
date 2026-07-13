<?php

declare(strict_types=1);

use PortLibs\Gitoxide\RefSpec;

/*
 * Exact upstream #[test] functions represented here:
 * - tests/refspec/match_group.rs: 13
 * - tests/refspec/impls.rs: 3
 * - tests/refspec/parse/mod.rs: 1 (local_and_remote)
 *
 * Skipped:
 * - tests/refspec/parse/mod.rs::baseline depends on the generated
 *   parse_baseline.sh fixture and the local git executable's behavior.
 */

$sha1AnnotatedTag = '78b1c1be9421b33a49a7a8176d93eeeafa112da1';
$sha1InitialCommit = '9d2fab1a0ba3585d0bc50922bfdd04ebb59361df';
$sha256AnnotatedTag = 'b071221ea854da2958fba3a37527ca5cf32c4ebcd71ab0b68b6b8f10f04e93ad';
$sha256InitialCommit = 'ac050883b75422e0d03bfee760c591b292cbc10cee8ad934480ea5fb2ebc44fe';

$ref = static function (string $name, ?string $target = null, ?string $object = null): array {
    $out = ['name' => $name];
    if ($target !== null) {
        $out['target'] = $target;
    }
    if ($object !== null) {
        $out['object'] = $object;
    }

    return $out;
};

$fixtureRefs = [
    $ref('HEAD', str_repeat('4', 40)),
    $ref('refs/heads/f1', str_repeat('1', 40)),
    $ref('refs/heads/f2', str_repeat('2', 40)),
    $ref('refs/heads/f3', str_repeat('3', 40)),
    $ref('refs/heads/main', str_repeat('4', 40)),
    $ref('refs/heads/sub/f4', str_repeat('5', 40)),
    $ref('refs/heads/sub/subdir/f5', str_repeat('6', 40)),
    $ref('refs/heads/suub/f6', str_repeat('7', 40)),
    $ref('refs/tags/annotated-v0.0', $sha1AnnotatedTag, $sha1InitialCommit),
    $ref('refs/tags/v0.0-f1', str_repeat('1', 40)),
    $ref('refs/tags/v0.0-f2', str_repeat('2', 40)),
    $ref('refs/tags/v0.0-f3', str_repeat('3', 40)),
];

$m = static fn (string $remote, ?string $local = null, int $specIndex = 0, ?int $itemIndex = null): array => [
    'remote' => $remote,
    'local' => $local,
    'specIndex' => $specIndex,
    'itemIndex' => $itemIndex,
];

$assertFetch = static function (TestRunner $t, array $expected, array $specs, array $refs) use ($m): void {
    $t->same($expected, RefSpec::matchFetchRemoteRefs($specs, $refs), implode(' ', $specs));
};

$headsToOrigin = static function (int $specIndex = 0) use ($m): array {
    return [
        $m('refs/heads/f1', 'refs/remotes/origin/f1', $specIndex, 1),
        $m('refs/heads/f2', 'refs/remotes/origin/f2', $specIndex, 2),
        $m('refs/heads/f3', 'refs/remotes/origin/f3', $specIndex, 3),
        $m('refs/heads/main', 'refs/remotes/origin/main', $specIndex, 4),
        $m('refs/heads/sub/f4', 'refs/remotes/origin/sub/f4', $specIndex, 5),
        $m('refs/heads/sub/subdir/f5', 'refs/remotes/origin/sub/subdir/f5', $specIndex, 6),
        $m('refs/heads/suub/f6', 'refs/remotes/origin/suub/f6', $specIndex, 7),
    ];
};

$tagsToOrigin = static function (int $specIndex = 0) use ($m): array {
    return [
        $m('refs/tags/annotated-v0.0', 'refs/remotes/origin/annotated-v0.0', $specIndex, 8),
        $m('refs/tags/v0.0-f1', 'refs/remotes/origin/v0.0-f1', $specIndex, 9),
        $m('refs/tags/v0.0-f2', 'refs/remotes/origin/v0.0-f2', $specIndex, 10),
        $m('refs/tags/v0.0-f3', 'refs/remotes/origin/v0.0-f3', $specIndex, 11),
    ];
};

$tagsToTags = static function (int $specIndex = 0) use ($m): array {
    return [
        $m('refs/tags/annotated-v0.0', 'refs/tags/annotated-v0.0', $specIndex, 8),
        $m('refs/tags/v0.0-f1', 'refs/tags/v0.0-f1', $specIndex, 9),
        $m('refs/tags/v0.0-f2', 'refs/tags/v0.0-f2', $specIndex, 10),
        $m('refs/tags/v0.0-f3', 'refs/tags/v0.0-f3', $specIndex, 11),
    ];
};

return [
    'upstream match_group.rs single::fetch_only' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $m,
        $sha1AnnotatedTag,
        $sha1InitialCommit,
        $sha256AnnotatedTag,
        $sha256InitialCommit
    ): void {
        foreach ([
            'refs/heads/main' => $m('refs/heads/main', null, 0, 4),
            'heads/main' => $m('refs/heads/main', null, 0, 4),
            'main' => $m('refs/heads/main', null, 0, 4),
            'v0.0-f1' => $m('refs/tags/v0.0-f1', null, 0, 9),
            'tags/v0.0-f2' => $m('refs/tags/v0.0-f2', null, 0, 10),
        ] as $spec => $expected) {
            $assertFetch($t, [$expected], [$spec], $fixtureRefs);
        }

        foreach ([$sha1AnnotatedTag, $sha1InitialCommit, $sha256AnnotatedTag, $sha256InitialCommit] as $object) {
            $assertFetch($t, [$m(strtolower($object))], [$object], $fixtureRefs);
        }
    },

    'upstream match_group.rs single::fetch_and_update' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $headsToOrigin,
        $m,
        $sha1AnnotatedTag,
        $sha1InitialCommit,
        $sha256AnnotatedTag,
        $sha256InitialCommit
    ): void {
        foreach ([$sha1AnnotatedTag, $sha256AnnotatedTag] as $annotatedTag) {
            $assertFetch($t, [
                $m(strtolower($annotatedTag), 'refs/heads/special'),
            ], [$annotatedTag . ':special'], $fixtureRefs);

            $assertFetch($t, [
                $m(strtolower($annotatedTag), 'refs/heads/1111111111111111111111111111111111111111'),
            ], [$annotatedTag . ':1111111111111111111111111111111111111111'], $fixtureRefs);
        }

        foreach ([$sha1InitialCommit, $sha256InitialCommit] as $initialCommit) {
            $assertFetch($t, [
                $m(strtolower($initialCommit), 'refs/tags/special'),
            ], [$initialCommit . ':tags/special'], $fixtureRefs);

            $assertFetch($t, [
                $m(strtolower($initialCommit), 'refs/tags/special'),
            ], [$initialCommit . ':refs/tags/special'], $fixtureRefs);
        }

        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/heads/origin/f1', 0, 1),
        ], ['f1:origin/f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/remotes/origin/f1', 0, 1),
        ], ['f1:remotes/origin/f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/heads/notes/f1', 0, 1),
        ], ['f1:notes/f1'], $fixtureRefs);

        $assertFetch($t, $headsToOrigin(), ['+refs/heads/*:refs/remotes/origin/*'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/remotes/origin/a1', 0, 1),
            $m('refs/heads/f2', 'refs/remotes/origin/a2', 0, 2),
            $m('refs/heads/f3', 'refs/remotes/origin/a3', 0, 3),
        ], ['refs/heads/f*:refs/remotes/origin/a*'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/remotes/origin/f1', 0, 1),
        ], ['refs/heads/*1:refs/remotes/origin/*1'], $fixtureRefs);
    },

    'upstream match_group.rs multiple::fetch_only' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $headsToOrigin,
        $m,
        $tagsToOrigin,
        $tagsToTags
    ): void {
        $assertFetch($t, [
            $m('refs/heads/main', null, 0, 4),
            $m('refs/heads/f1', null, 1, 1),
        ], ['main', 'f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/main', null, 0, 4),
            $m('refs/heads/f1', null, 1, 1),
        ], ['heads/main', 'heads/f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/main', null, 0, 4),
            $m('refs/heads/f1', null, 1, 1),
        ], ['refs/heads/main', 'refs/heads/f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', null, 0, 1),
            $m('refs/heads/f2', null, 1, 2),
            $m('refs/heads/f3', null, 2, 3),
            $m('refs/heads/main', null, 3, 4),
        ], ['heads/f1', 'f2', 'refs/heads/f3', 'heads/main'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/main', null, 1, 4),
        ], ['f*:a*', 'refs/heads/main'], $fixtureRefs);
        $assertFetch($t, [
            ...$tagsToOrigin(0),
            ...$headsToOrigin(1),
        ], [
            'refs/tags/*:refs/remotes/origin/*',
            'refs/heads/*:refs/remotes/origin/*',
        ], $fixtureRefs);
        $assertFetch($t, $tagsToTags(), ['refs/tags/*:refs/tags/*'], $fixtureRefs);
    },

    'upstream match_group.rs multiple::fetch_and_update_and_negations' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $headsToOrigin,
        $m
    ): void {
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^f1'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^refs/heads/f*:refs/remotes/origin/a*'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^heads/f2'));
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('^main'));

        $assertFetch($t, [
            $m('refs/heads/f2', 'refs/remotes/origin/a2', 0, 2),
            $m('refs/heads/f3', 'refs/remotes/origin/a3', 0, 3),
        ], ['refs/heads/f*:refs/remotes/origin/a*', '^refs/heads/f1'], $fixtureRefs);
        $assertFetch($t, [
            $m('refs/heads/f1', 'refs/remotes/origin/a1', 1, 1),
            $m('refs/heads/f3', 'refs/remotes/origin/a3', 1, 3),
        ], ['^refs/heads/f2', 'refs/heads/f*:refs/remotes/origin/a*'], $fixtureRefs);

        $withoutMain = array_values(array_filter(
            $headsToOrigin(1),
            static fn (array $mapping): bool => $mapping['remote'] !== 'refs/heads/main'
        ));
        $assertFetch($t, $withoutMain, ['^refs/heads/main', 'refs/heads/*:refs/remotes/origin/*'], $fixtureRefs);

        $withoutMainFirstSpec = array_values(array_map(
            static fn (array $mapping): array => [
                'remote' => $mapping['remote'],
                'local' => $mapping['local'],
                'specIndex' => 0,
                'itemIndex' => $mapping['itemIndex'],
            ],
            $withoutMain
        ));
        $assertFetch($t, $withoutMainFirstSpec, ['refs/heads/*:refs/remotes/origin/*', '^refs/heads/main'], $fixtureRefs);
    },

    'upstream match_group.rs multiple::fetch_and_update_with_empty_lhs' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $m
    ): void {
        foreach ([':refs/heads/f1', ':f1', '@:f1'] as $spec) {
            $assertFetch($t, [
                $m('HEAD', 'refs/heads/f1', 0, 0),
            ], [$spec], $fixtureRefs);
        }
    },

    'upstream match_group.rs multiple::fetch_and_update_head_to_head_never_updates_actual_head_ref' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $m
    ): void {
        $assertFetch($t, [
            $m('HEAD', 'refs/heads/HEAD', 0, 0),
        ], ['@:HEAD'], $fixtureRefs);
    },

    'upstream match_group.rs multiple::fetch_and_update_head_with_empty_rhs' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $m
    ): void {
        foreach ([':', 'HEAD:', '@:'] as $spec) {
            $assertFetch($t, [
                $m('HEAD', null, 0, 0),
            ], [$spec], $fixtureRefs);
        }
    },

    'upstream match_group.rs multiple::fetch_and_update_multiple_destinations' => static function (TestRunner $t) use (
        $assertFetch,
        $fixtureRefs,
        $headsToOrigin,
        $m
    ): void {
        $assertFetch($t, [
            ...$headsToOrigin(0),
            $m('refs/heads/main', 'refs/remotes/new-origin/main', 1, 4),
        ], [
            'refs/heads/*:refs/remotes/origin/*',
            'refs/heads/main:refs/remotes/new-origin/main',
        ], $fixtureRefs);

        $assertFetch($t, $headsToOrigin(0), [
            'refs/heads/*:refs/remotes/origin/*',
            'refs/heads/main:refs/remotes/origin/main',
        ], $fixtureRefs);
    },

    'upstream match_group.rs multiple::fetch_and_update_with_conflicts' => static function (TestRunner $t) use ($fixtureRefs): void {
        $conflict = RefSpec::validatedFetchRemoteRefs([
            'refs/heads/f1:refs/remotes/origin/conflict',
            'refs/heads/f2:refs/remotes/origin/conflict',
        ], $fixtureRefs);
        $t->same(false, $conflict['ok']);
        $t->same([
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/conflict',
                'sources' => ['refs/heads/f1', 'refs/heads/f2'],
                'specs' => [
                    'refs/heads/f1:refs/remotes/origin/conflict',
                    'refs/heads/f2:refs/remotes/origin/conflict',
                ],
                'specIndexes' => [0, 1],
            ],
        ], $conflict['issues']);

        $twoConflicts = RefSpec::validatedFetchRemoteRefs([
            'refs/heads/f1:refs/remotes/origin/conflict2',
            'refs/heads/f2:refs/remotes/origin/conflict2',
            'refs/heads/f1:refs/remotes/origin/conflict',
            'refs/heads/f2:refs/remotes/origin/conflict',
            'refs/heads/f3:refs/remotes/origin/conflict',
        ], $fixtureRefs);
        $t->same(false, $twoConflicts['ok']);
        $t->same([
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/conflict',
                'sources' => ['refs/heads/f1', 'refs/heads/f2', 'refs/heads/f3'],
                'specs' => [
                    'refs/heads/f1:refs/remotes/origin/conflict',
                    'refs/heads/f2:refs/remotes/origin/conflict',
                    'refs/heads/f3:refs/remotes/origin/conflict',
                ],
                'specIndexes' => [2, 3, 4],
            ],
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/conflict2',
                'sources' => ['refs/heads/f1', 'refs/heads/f2'],
                'specs' => [
                    'refs/heads/f1:refs/remotes/origin/conflict2',
                    'refs/heads/f2:refs/remotes/origin/conflict2',
                ],
                'specIndexes' => [0, 1],
            ],
        ], $twoConflicts['issues']);

        $tagConflict = RefSpec::validatedFetchRemoteRefs([
            'refs/heads/f1:refs/remotes/origin/same',
            'refs/tags/v0.0-f1:refs/remotes/origin/same',
        ], $fixtureRefs);
        $t->same(false, $tagConflict['ok']);
        $t->same([
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/same',
                'sources' => ['refs/heads/f1', 'refs/tags/v0.0-f1'],
                'specs' => [
                    'refs/heads/f1:refs/remotes/origin/same',
                    'refs/tags/v0.0-f1:refs/remotes/origin/same',
                ],
                'specIndexes' => [0, 1],
            ],
        ], $tagConflict['issues']);

        $crossed = RefSpec::validatedFetchRemoteRefs([
            '+refs/heads/*:refs/remotes/origin/*',
            'refs/heads/f1:refs/remotes/origin/f2',
            'refs/heads/f2:refs/remotes/origin/f1',
        ], $fixtureRefs);
        $t->same(false, $crossed['ok']);
        $t->same([
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/f1',
                'sources' => ['refs/heads/f1', 'refs/heads/f2'],
                'specs' => [
                    '+refs/heads/*:refs/remotes/origin/*',
                    'refs/heads/f2:refs/remotes/origin/f1',
                ],
                'specIndexes' => [0, 2],
            ],
            [
                'type' => 'conflicting-destination',
                'destination' => 'refs/remotes/origin/f2',
                'sources' => ['refs/heads/f2', 'refs/heads/f1'],
                'specs' => [
                    '+refs/heads/*:refs/remotes/origin/*',
                    'refs/heads/f1:refs/remotes/origin/f2',
                ],
                'specIndexes' => [0, 1],
            ],
        ], $crossed['issues']);
    },

    'upstream match_group.rs multiple::fetch_and_update_with_fixes' => static function (TestRunner $t) use ($fixtureRefs): void {
        $fixed = RefSpec::validatedFetchRemoteRefs([
            'refs/heads/f*:foo/f*',
            'f1:f1',
        ], $fixtureRefs);

        $t->same(true, $fixed['ok']);
        $t->same([
            [
                'remote' => 'refs/heads/f1',
                'local' => 'refs/heads/f1',
                'specIndex' => 1,
                'itemIndex' => 1,
            ],
        ], $fixed['mappings']);
        $t->same([
            [
                'type' => 'partial-destination-removed',
                'name' => 'foo/f1',
                'spec' => 'refs/heads/f*:foo/f*',
                'specIndex' => 0,
            ],
            [
                'type' => 'partial-destination-removed',
                'name' => 'foo/f2',
                'spec' => 'refs/heads/f*:foo/f*',
                'specIndex' => 0,
            ],
            [
                'type' => 'partial-destination-removed',
                'name' => 'foo/f3',
                'spec' => 'refs/heads/f*:foo/f*',
                'specIndex' => 0,
            ],
        ], $fixed['fixes']);
        $t->same([], $fixed['issues']);
    },

    'upstream match_group.rs complex_globs::one_sided_complex_glob_patterns_can_be_parsed' => static function (TestRunner $t): void {
        $t->same('refs/*/foo/*', RefSpec::parseFetch('refs/*/foo/*')->source());
        $t->same('refs/*/*/bar', RefSpec::parseFetch('refs/*/*/bar')->source());
        $t->same('refs/heads/[a-z.]/release/*', RefSpec::parseFetch('refs/heads/[a-z.]/release/*')->source());
        $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parseFetch('refs/*/foo/*:refs/remotes/*'));
    },

    'upstream match_group.rs complex_globs::one_sided_simple_glob_patterns_match' => static function (TestRunner $t) use ($assertFetch, $m, $ref): void {
        $refs = [
            $ref('refs/heads/feature/foo', str_repeat('1', 40)),
            $ref('refs/heads/bugfix/bar', str_repeat('2', 40)),
            $ref('refs/tags/v1.0', str_repeat('3', 40)),
            $ref('refs/pull/123', str_repeat('4', 40)),
        ];

        $assertFetch($t, [
            $m('refs/heads/feature/foo', null, 0, 0),
            $m('refs/heads/bugfix/bar', null, 0, 1),
        ], ['refs/heads/*'], $refs);

        $assertFetch($t, [
            $m('refs/tags/v1.0', null, 0, 2),
        ], ['refs/tags/v[0-9]*'], $refs);
    },

    'upstream match_group.rs complex_globs::one_sided_glob_with_suffix_matches' => static function (TestRunner $t) use ($assertFetch, $m, $ref): void {
        $refs = [
            $ref('refs/heads/feature', str_repeat('1', 40)),
            $ref('refs/heads/feat', str_repeat('2', 40)),
            $ref('refs/heads/main', str_repeat('3', 40)),
        ];

        $assertFetch($t, [
            $m('refs/heads/feature', null, 0, 0),
            $m('refs/heads/feat', null, 0, 1),
        ], ['refs/heads/feat*'], $refs);
    },

    'upstream impls.rs cmp' => static function (TestRunner $t): void {
        $lhs = RefSpec::parsePush('refs/heads/foo');
        $rhs = RefSpec::parsePush('refs/heads/foo:refs/heads/foo');

        $orderedSet = [];
        foreach ([$lhs, $rhs] as $spec) {
            $orderedSet[$spec->instructionKey()] = true;
        }
        ksort($orderedSet);

        $t->same(1, count($orderedSet));
        $t->same(0, $lhs->instructionKey() <=> $rhs->instructionKey());
    },

    'upstream impls.rs hash' => static function (TestRunner $t): void {
        $set = [];
        foreach ([RefSpec::parsePush('refs/heads/foo'), RefSpec::parsePush('refs/heads/foo:refs/heads/foo')] as $spec) {
            $set[$spec->instructionKey()] = true;
        }

        $t->same(1, count($set));
    },

    'upstream impls.rs eq' => static function (TestRunner $t): void {
        $specs = [
            RefSpec::parsePush('refs/heads/foo'),
            RefSpec::parsePush('refs/heads/foo:refs/heads/foo'),
        ];

        $t->true($specs[0]->equivalentTo($specs[1]));
        $t->same($specs[0]->instructionIdentity(), $specs[1]->instructionIdentity());
    },

    'upstream parse/mod.rs local_and_remote' => static function (TestRunner $t): void {
        $fetch = RefSpec::parseFetch('remote:local');
        $t->same($fetch->source(), $fetch->remote());
        $t->same($fetch->destination(), $fetch->local());

        $push = RefSpec::parsePush('local:remote');
        $t->same($push->source(), $push->local());
        $t->same($push->destination(), $push->remote());
    },
];
