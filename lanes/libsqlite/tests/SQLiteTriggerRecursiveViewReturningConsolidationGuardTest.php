<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$tests = [];

$tests['trigger recursive view returning production class has no numbered method declarations'] = static function (TestRunner $t): void {
    $class = new ReflectionClass(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::class);
    $numbered = [];

    foreach ($class->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $class->getName()) {
            continue;
        }

        if (preg_match('/(?:Next|next)[0-9]+$/', $method->getName()) === 1) {
            $numbered[] = $method->getName();
        }
    }

    sort($numbered);
    $t->same([], $numbered);
};

$tests['trigger recursive view returning production files have no numbered variant classes'] = static function (TestRunner $t): void {
    $root = dirname(__DIR__) . '/src';
    $files = glob($root . '/SQLiteTrigger*CurrentSourceNext*Plan.php') ?: [];
    $numberedFiles = [];
    $numberedClasses = [];

    foreach ($files as $file) {
        $basename = basename($file);
        if (preg_match('/CurrentSourceNext[0-9]+|CurrentNext[0-9]+/', $basename) === 1) {
            $numberedFiles[] = $basename;
        }

        $source = file_get_contents($file);
        if (!is_string($source)) {
            continue;
        }

        if (preg_match_all('/\bclass\s+([A-Za-z0-9_]*(?:CurrentSourceNext|CurrentNext)[0-9]+[A-Za-z0-9_]*)\b/', $source, $matches) > 0) {
            array_push($numberedClasses, ...$matches[1]);
        }
    }

    sort($numberedFiles);
    sort($numberedClasses);

    $t->same([], $numberedFiles);
    $t->same([], $numberedClasses);
};

$tests['trigger returning production files have no numbered method declarations'] = static function (TestRunner $t): void {
    $root = dirname(__DIR__) . '/src';
    $bannedCurrentSourceSuffix = 'CurrentSourceNext' . '150';
    $bannedCurrentSuffix = 'CurrentNext' . '150';
    $files = array_merge(
        glob($root . '/*Trigger*Returning*.php') ?: [],
        glob($root . '/*Returning*Trigger*.php') ?: [],
        glob($root . '/*Trigger*CurrentNext*Plan.php') ?: [],
        glob($root . '/*Trigger*CurrentSourceNext*Plan.php') ?: [],
    );
    $files = array_values(array_unique($files));
    sort($files);

    $numberedMethods = [];
    $bannedSuffixFiles = [];
    foreach ($files as $file) {
        $source = file_get_contents($file);
        if (!is_string($source)) {
            continue;
        }

        if (str_contains($source, $bannedCurrentSourceSuffix) || str_contains($source, $bannedCurrentSuffix)) {
            $bannedSuffixFiles[] = basename($file);
        }

        if (preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*(?:Next|next)[0-9]+[A-Za-z0-9_]*)\s*\(/', $source, $matches) > 0) {
            foreach ($matches[1] as $method) {
                $numberedMethods[] = basename($file) . '::' . $method;
            }
        }
    }

    sort($numberedMethods);
    sort($bannedSuffixFiles);

    $t->same([], $numberedMethods);
    $t->same([], $bannedSuffixFiles);
};

$tests['trigger recursive view returning canonical final methods are stable entry points'] = static function (TestRunner $t): void {
    $class = new ReflectionClass(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::class);
    $methods = [
        'executeCurrentReturningSourceSeal',
        'executeFollowingCurrentSeal',
        'currentReturningSnapshotAcknowledgement',
        'executeCurrentReturningGenerationSeal',
        'executeCurrentSourceEpochReceipt',
        'executeCurrentSourceCursorClose',
    ];

    foreach ($methods as $method) {
        $t->same(true, $class->hasMethod($method));
        $t->same(true, $class->getMethod($method)->isPublic());
    }
};

return $tests;
