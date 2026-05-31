<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeySchemaRequirementPlan;

$valueAt = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$expectedPaths = static function (int $variant): array {
    $paths = [
        'source' => 'e_fkey.test',
        'variant' => $variant,
        'scenarios.0' => 'e_fkey-18.1..18.9',
        'scenarios.1' => 'e_fkey-19.1..19.5',
        'scenarios.2' => 'e_fkey-20.1..20.8.6',
        'scenarios.3' => 'e_fkey-21.1..21.8',
        'scenarios.4' => 'e_fkey-22.OFF.1..22.ON.4',
        'scenarios.5' => 'e_fkey-23.1..23.7',
        'scenarios.6' => 'e_fkey-24.1..24.4.3',
        'dependencies.0' => 'sqlite-efkey-parent-key-requires-primary-key-or-unique-index',
        'dependencies.1' => 'sqlite-efkey-parent-unique-index-must-use-parent-collation',
        'dependencies.2' => 'sqlite-efkey-cross-table-schema-errors-surface-at-dml-prepare',
        'dependencies.3' => 'sqlite-efkey-child-definition-errors-surface-at-create-table',
        'dependencies.4' => 'sqlite-efkey-implicit-references-map-to-parent-primary-key',
        'dependencies.5' => 'sqlite-efkey-child-key-index-is-optional-and-need-not-be-unique',
    ];

    $parentCases = [
        ['e_fkey-18.1', 'definition-deferred', null, false, null, 'parent-table-lookup-deferred-until-dml', 'create-child-table'],
        ['e_fkey-18.2', 'dml-ok', null, true, 'primary-key', 'parent-primary-key', 'dml-prepare'],
        ['e_fkey-18.3', 'dml-ok', null, true, 'unique-constraint', 'parent-unique-constraint', 'dml-prepare'],
        ['e_fkey-18.4', 'dml-ok', null, true, 'unique-index', 'parent-unique-index', 'dml-prepare'],
        ['e_fkey-18.5', 'foreign-key-mismatch', 'foreign key mismatch - "t2" referencing "t1"', false, null, 'unique-index-collation-mismatch', 'dml-prepare'],
        ['e_fkey-18.6', 'foreign-key-mismatch', 'foreign key mismatch - "t2" referencing "t1"', false, null, 'missing-unique-parent-key', 'dml-prepare'],
        ['e_fkey-18.7', 'foreign-key-mismatch', 'foreign key mismatch - "t2" referencing "t1"', false, null, 'parent-key-not-exact-unique-index', 'dml-prepare'],
        ['e_fkey-18.8', 'foreign-key-mismatch', 'foreign key mismatch - "t2" referencing "t1"', false, null, 'parent-key-not-exact-unique-index', 'dml-prepare'],
        ['e_fkey-18.9', 'foreign-key-mismatch', 'foreign key mismatch - "t2" referencing "t1"', false, null, 'parent-key-not-exact-unique-index', 'dml-prepare'],
    ];

    foreach ($parentCases as $index => [$case, $status, $error, $valid, $matched, $reason, $detectedAt]) {
        $prefix = 'parent_key_cases.' . $index;
        $paths[$prefix . '.case'] = $case;
        $paths[$prefix . '.status'] = $status;
        $paths[$prefix . '.error'] = $error;
        $paths[$prefix . '.parent_key_valid'] = $valid;
        $paths[$prefix . '.matched_by'] = $matched;
        $paths[$prefix . '.reason'] = $reason;
        $paths[$prefix . '.detected_at'] = $detectedAt;
    }

    $exampleCases = [
        ['child1', 'dml-ok', null, true, 'primary-key', 'parent-primary-key'],
        ['child2', 'dml-ok', null, true, 'unique-constraint', 'parent-unique-constraint'],
        ['child3', 'dml-ok', null, true, 'unique-index', 'parent-unique-index'],
        ['child4', 'foreign-key-mismatch', 'foreign key mismatch - "child4" referencing "parent"', false, null, 'missing-unique-parent-key'],
        ['child5', 'foreign-key-mismatch', 'foreign key mismatch - "child5" referencing "parent"', false, null, 'unique-index-collation-mismatch'],
        ['child6', 'foreign-key-mismatch', 'foreign key mismatch - "child6" referencing "parent"', false, null, 'parent-key-not-exact-unique-index'],
        ['child7', 'foreign-key-mismatch', 'foreign key mismatch - "child7" referencing "parent"', false, null, 'parent-key-not-exact-unique-index'],
    ];

    foreach ($exampleCases as $index => [$child, $status, $error, $valid, $matched, $reason]) {
        $prefix = 'example_children.' . $index;
        $paths[$prefix . '.child_table'] = $child;
        $paths[$prefix . '.status'] = $status;
        $paths[$prefix . '.error'] = $error;
        $paths[$prefix . '.parent_key_valid'] = $valid;
        $paths[$prefix . '.matched_by'] = $matched;
        $paths[$prefix . '.reason'] = $reason;
    }

    $dmlCases = [
        ['c1', 'nosuchtable', 'no-such-parent-table', 'no such table: main.nosuchtable', 'missing-parent-table', 3],
        ['c2', 'p2', 'foreign-key-mismatch', 'foreign key mismatch - "c2" referencing "p2"', 'missing-parent-key-column', 6],
        ['c3', 'p3', 'foreign-key-mismatch', 'foreign key mismatch - "c3" referencing "p3"', 'referenced-parent-column-not-unique', 6],
        ['c4', 'p4', 'foreign-key-mismatch', 'foreign key mismatch - "c4" referencing "p4"', 'unique-index-collation-mismatch', 6],
        ['c5', 'p5', 'foreign-key-mismatch', 'foreign key mismatch - "c5" referencing "p5"', 'parent-column-collation-mismatch', 6],
        ['c6', 'p6', 'foreign-key-mismatch', 'foreign key mismatch - "c6" referencing "p6"', 'implicit-parent-primary-key-width-mismatch', 6],
        ['c7', 'p7', 'foreign-key-mismatch', 'foreign key mismatch - "c7" referencing "p7"', 'implicit-parent-primary-key-width-mismatch', 6],
    ];

    foreach ($dmlCases as $index => [$child, $parent, $status, $error, $reason, $operationCount]) {
        $prefix = 'dml_error_cases.' . $index;
        $paths[$prefix . '.child'] = $child;
        $paths[$prefix . '.parent'] = $parent;
        $paths[$prefix . '.status'] = $status;
        $paths[$prefix . '.error'] = $error;
        $paths[$prefix . '.reason'] = $reason;
        $paths[$prefix . '.operation_count'] = $operationCount;
    }

    $left = 'I' . $variant;
    $right = 'II' . $variant;
    $implicitWidth = [
        ['child8', 'commit-ok', null, ['x', 'y'], [$left, $right], false],
        ['child9', 'foreign-key-mismatch', 'foreign key mismatch - "child9" referencing "parent2"', ['x'], [$left], true],
        ['child10', 'foreign-key-mismatch', 'foreign key mismatch - "child10" referencing "parent2"', ['x', 'y', 'z'], [$left, $right, 'III' . $variant], true],
    ];
    foreach ($implicitWidth as $index => [$child, $status, $error, $childColumns, $insertValues, $nullStillMismatch]) {
        $prefix = 'implicit_width_cases.' . $index;
        $paths[$prefix . '.child'] = $child;
        $paths[$prefix . '.status'] = $status;
        $paths[$prefix . '.error'] = $error;
        $paths[$prefix . '.implicit_parent_columns'] = ['a', 'b'];
        $paths[$prefix . '.child_columns'] = $childColumns;
        $paths[$prefix . '.insert_values'] = $insertValues;
        $paths[$prefix . '.null_child_still_mismatch'] = $nullStillMismatch;
    }

    $ddlErrors = [
        'number of columns in foreign key does not match the number of columns in the referenced table',
        'number of columns in foreign key does not match the number of columns in the referenced table',
        'unknown column "c" in foreign key definition',
        'unknown column "c" in foreign key definition',
    ];
    foreach ([false, true] as $fkIndex => $foreignKeysEnabled) {
        foreach ($ddlErrors as $errorIndex => $error) {
            $index = ($fkIndex * 4) + $errorIndex;
            $prefix = 'child_definition_errors.' . $index;
            $paths[$prefix . '.case'] = 'e_fkey-22.' . ($foreignKeysEnabled ? 'ON' : 'OFF') . '.' . ($errorIndex + 1);
            $paths[$prefix . '.status'] = 'definition-error';
            $paths[$prefix . '.error'] = $error;
            $paths[$prefix . '.foreign_keys_enabled'] = $foreignKeysEnabled;
            $paths[$prefix . '.detected_at'] = 'create-table';
        }
    }

    $a = 239 + $variant;
    $b = 231 + $variant;
    $implicitReferences = [
        ['c1', 'p1', ['a', 'b'], [$a, $b]],
        ['c2', 'p2', ['b'], [$a, $b]],
    ];
    foreach ($implicitReferences as $index => [$child, $parent, $parentColumns, $values]) {
        $prefix = 'implicit_references.' . $index;
        $paths[$prefix . '.child'] = $child;
        $paths[$prefix . '.parent'] = $parent;
        $paths[$prefix . '.implicit_parent_columns'] = $parentColumns;
        $paths[$prefix . '.child_values'] = $values;
        $paths[$prefix . '.status_before_parent'] = 'constraint-failed';
        $paths[$prefix . '.status_after_parent'] = 'commit-ok';
        $paths[$prefix . '.error_before_parent'] = 'FOREIGN KEY constraint failed';
    }

    $value = [1 + $variant, 2 + $variant];
    foreach ([['c1', 'none'], ['c2', 'index'], ['c3', 'unique-index']] as $index => [$child, $indexType]) {
        $prefix = 'child_index_cases.' . $index;
        $paths[$prefix . '.child'] = $child;
        $paths[$prefix . '.child_index_type'] = $indexType;
        $paths[$prefix . '.parent_unique_columns'] = ['y', 'x'];
        $paths[$prefix . '.foreign_key_parent_columns'] = ['x', 'y'];
        $paths[$prefix . '.child_values'] = $value;
        $paths[$prefix . '.child_index_required'] = false;
        $paths[$prefix . '.status_before_parent'] = 'constraint-failed';
        $paths[$prefix . '.status_after_parent'] = 'commit-ok';
        $paths[$prefix . '.error_before_parent'] = 'FOREIGN KEY constraint failed';
    }

    return $paths;
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests = [
    'real upstream e_fkey schema requirement cites required parent key section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('EVIDENCE-OF: R-13435-26311', $source);
        $t->contains('test_efkey_57 5 1', $source);
    },
    'real upstream e_fkey schema requirement cites example child mismatch section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('CREATE TABLE child4(l, m REFERENCES parent(e));', $source);
        $t->contains('foreign key mismatch - "child4" referencing "parent"', $source);
    },
    'real upstream e_fkey schema requirement cites dml prepare error section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('do_test e_fkey-20.$tn.1', $source);
        $t->contains('no such table: main.nosuchtable', $source);
    },
    'real upstream e_fkey schema requirement cites ddl child definition section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('do_test e_fkey-22.$fk.[incr i]', $source);
        $t->contains('unknown column "c" in foreign key definition', $source);
    },
    'real upstream e_fkey schema requirement cites implicit parent key section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('test_efkey_60 2 1 "INSERT INTO c1 VALUES(239, 231)"', $source);
        $t->contains('test_efkey_60 7 0 "INSERT INTO c2 VALUES(239, 231)"', $source);
    },
    'real upstream e_fkey schema requirement cites optional child index section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);
        $t->true(is_string($source));
        $t->contains('EVIDENCE-OF: R-15417-28014', $source);
        $t->contains('EVIDENCE-OF: R-15741-50893', $source);
    },
];

foreach (range(1, 5) as $variant) {
    foreach ($expectedPaths($variant) as $path => $expected) {
        $tests[sprintf('real upstream e_fkey schema requirement dynamic v%02d %s', $variant, $path)] = static function (TestRunner $t) use ($variant, $path, $expected, $valueAt): void {
            $plan = SQLiteForeignKeySchemaRequirementPlan::eFkeyRequiredIndexCorpus($variant);
            $t->same($expected, $valueAt($plan, (string) $path));
        };
    }
}

$tests['real upstream e_fkey schema requirement rejects zero variant'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeySchemaRequirementPlan::eFkeyRequiredIndexCorpus(0));
};

return $tests;
