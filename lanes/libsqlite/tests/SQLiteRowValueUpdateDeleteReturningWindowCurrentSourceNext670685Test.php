<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next670-685 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next670-685.php';

        $expectedStatuses = [];
        for ($next = 670; $next <= 685; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next670-685', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next670Handoff']));
        $t->same('next666-669', $result['next670AfterReadyRange']);
        $t->same(64, strlen($result['next671SourceAudit']));
        $t->same(true, $result['next671PreservesCurrentSource']);
        $t->same(64, strlen($result['next672Preflight']));
        $t->same(true, $result['next672KeepsThroughputHigh']);
        $t->same(64, strlen($result['next673Final']));
        $t->same(true, $result['next673Ready']);
        $t->same(64, strlen($result['next674Handoff']));
        $t->same('next670-673', $result['next674AfterReadyRange']);
        $t->same(true, $result['next677Ready']);
        $t->same(true, $result['next681Ready']);
        $t->same(64, strlen($result['next685Final']));
        $t->same(true, $result['next685Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next670-685 ' . $name] = $callback;
}

return $tests;
