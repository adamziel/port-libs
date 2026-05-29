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
