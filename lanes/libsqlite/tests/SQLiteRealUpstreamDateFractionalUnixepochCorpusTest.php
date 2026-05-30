<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

for ($millisecond = 0; $millisecond < 1000; $millisecond++) {
    $suffix = str_pad((string) $millisecond, 3, '0', STR_PAD_LEFT);
    $tests['real upstream corpus date fractional unixepoch date.test date-2.2c-' . $millisecond] = static function (TestRunner $t) use ($millisecond, $suffix): void {
        $value = sprintf('1237962480.%03d', $millisecond);

        $t->same(
            '06:28:00.' . $suffix,
            SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $value, 'unixepoch'])
        );
    };
}

$tests['real upstream corpus date fractional unixepoch application queue buckets keep millisecond affinity'] = static function (TestRunner $t): void {
    $scheduledJobs = [
        ['job_id' => 10, 'run_at' => '1237962480.000'],
        ['job_id' => 20, 'run_at' => '1237962480.125'],
        ['job_id' => 30, 'run_at' => '1237962480.500'],
        ['job_id' => 40, 'run_at' => '1237962480.999'],
    ];
    $buckets = [];

    foreach ($scheduledJobs as $job) {
        $buckets[$job['job_id']] = SQLiteCoreScalarFunction::sqlFunctionArguments(
            'strftime',
            ['%H:%M:%f', $job['run_at'], 'unixepoch']
        );
    }

    $t->same([
        10 => '06:28:00.000',
        20 => '06:28:00.125',
        30 => '06:28:00.500',
        40 => '06:28:00.999',
    ], $buckets);
};

return $tests;
