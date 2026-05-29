<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'stable final continuation handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-final-continuation-handoff.php';

        $expectedStatuses = [];
        for ($next = 958; $next <= 973; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-final-continuation-handoff', $result['status']);
        $t->same($expectedStatuses, $result['candidate_statuses']);
        $t->same(64, strlen($result['handoff_token']));
        $t->same('next954-957', $result['handoff_after_ready_range']);
        $t->same(true, $result['handoff_consumes_previous_ready']);
        $t->same(64, strlen($result['source_audit_token']));
        $t->same(true, $result['preserves_current_source']);
        $t->same(64, strlen($result['preflight_token']));
        $t->same(true, $result['keeps_throughput_high']);
        $t->same(64, strlen($result['first_seal_token']));
        $t->same(true, $result['first_seal_ready']);
        $t->same(64, strlen($result['second_handoff_token']));
        $t->same('next958-961', $result['second_handoff_after_ready_range']);
        $t->same(true, $result['penultimate_seal_ready']);
        $t->same(64, strlen($result['final_seal_token']));
        $t->same(true, $result['final_seal_ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window final continuation handoff ' . $name] = $callback;
}

return $tests;
