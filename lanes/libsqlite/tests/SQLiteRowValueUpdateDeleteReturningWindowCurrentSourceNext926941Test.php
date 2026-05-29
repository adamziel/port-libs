<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next926-941 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next926-941.php';

        $expectedStatuses = [];
        for ($next = 926; $next <= 941; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next926-941', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next926Handoff']));
        $t->same('next922-925', $result['next926AfterReadyRange']);
        $t->same(true, $result['next926ConsumesNext925Ready']);
        $t->same(64, strlen($result['next927SourceAudit']));
        $t->same(true, $result['next927PreservesCurrentSource']);
        $t->same(64, strlen($result['next928Preflight']));
        $t->same(true, $result['next928KeepsThroughputHigh']);
        $t->same(64, strlen($result['next929Final']));
        $t->same(true, $result['next929Ready']);
        $t->same(64, strlen($result['next930Handoff']));
        $t->same('next926-929', $result['next930AfterReadyRange']);
        $t->same(true, $result['next933Ready']);
        $t->same(true, $result['next937Ready']);
        $t->same(64, strlen($result['next941Final']));
        $t->same(true, $result['next941Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next926-941 ' . $name] = $callback;
}

return $tests;
