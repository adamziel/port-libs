<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

$evidence = static fn (): SQLiteUpstreamSuiteEvidence => SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

$tests = [
    'upstream expression evidence next23 reports missing cache status' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same('blocked-missing-upstream-cache', $matrix['status']);
    },
    'upstream expression evidence next23 reports four groups' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same(4, $matrix['group_count']);
    },
    'upstream expression evidence next23 reports fifteen scripts' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same(15, $matrix['script_count']);
    },
    'upstream expression evidence next23 reports zero runnable groups without hydrated cache' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same(0, $matrix['runnable_groups']);
    },
    'upstream expression evidence next23 preserves dependency closure note' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('no new support component needed', $matrix['dependency_closure']);
    },
    'upstream expression evidence next23 names hydration next gate' => static function (TestRunner $t) use ($evidence): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('hydrate .upstream-cache/libsqlite', $matrix['next_acceptance_gate']);
    },
];

$expectedGroups = [
    'core-expression' => ['expr.test', 'e_expr.test', 'func.test', 'func2.test'],
    'affinity-cast-collation' => ['cast.test', 'types2.test', 'collate1.test', 'collate2.test'],
    'predicate-pattern' => ['where.test', 'where2.test', 'like.test', 'in.test'],
    'case-null-rowvalue' => ['case.test', 'null.test', 'rowvalue.test'],
];

foreach ($expectedGroups as $group => $scripts) {
    $tests["upstream expression evidence next23 exposes {$group} group"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->true(isset($matrix['groups'][$group]), "Expected {$group} group");
    };

    $tests["upstream expression evidence next23 {$group} script count"] = static function (TestRunner $t) use ($evidence, $group, $scripts): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same(count($scripts), $matrix['groups'][$group]['script_count']);
    };

    $tests["upstream expression evidence next23 {$group} command uses jobs"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('--jobs 2', $matrix['groups'][$group]['command']);
    };

    $tests["upstream expression evidence next23 {$group} command uses stop on error"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('--stop-on-error veryquick', $matrix['groups'][$group]['command']);
    };

    $tests["upstream expression evidence next23 {$group} is not runnable without cache"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->same(false, $matrix['groups'][$group]['runnable']);
    };

    $tests["upstream expression evidence next23 {$group} names missing testfixture"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', (string) $matrix['groups'][$group]['skip_reason']);
    };

    $tests["upstream expression evidence next23 {$group} names missing testrunner"] = static function (TestRunner $t) use ($evidence, $group): void {
        $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

        $t->contains('.upstream-cache/libsqlite/test/testrunner.tcl', (string) $matrix['groups'][$group]['skip_reason']);
    };

    foreach ($scripts as $script) {
        $tests["upstream expression evidence next23 {$group} includes {$script}"] = static function (TestRunner $t) use ($evidence, $group, $script): void {
            $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

            $t->true(in_array($script, $matrix['groups'][$group]['scripts'], true), "Expected {$script}");
        };

        $tests["upstream expression evidence next23 {$group} command includes {$script}"] = static function (TestRunner $t) use ($evidence, $group, $script): void {
            $matrix = $evidence()->upstreamExpressionEvidenceMatrix(2, dirname(__DIR__, 3));

            $t->contains($script, $matrix['groups'][$group]['command']);
        };
    }
}

return $tests;
