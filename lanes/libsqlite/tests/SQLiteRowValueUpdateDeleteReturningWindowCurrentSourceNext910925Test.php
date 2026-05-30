<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next910-925 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next910-925.php';

        $expectedStatuses = [];
        for ($next = 910; $next <= 925; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next910-925', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next910Handoff']));
        $t->same('next906-909', $result['next910AfterReadyRange']);
        $t->same(true, $result['next910ConsumesNext909Ready']);
        $t->same(64, strlen($result['next911SourceAudit']));
        $t->same(true, $result['next911PreservesCurrentSource']);
        $t->same(64, strlen($result['next912Preflight']));
        $t->same(true, $result['next912KeepsThroughputHigh']);
        $t->same(64, strlen($result['next913Final']));
        $t->same(true, $result['next913Ready']);
        $t->same(64, strlen($result['next914Handoff']));
        $t->same('next910-913', $result['next914AfterReadyRange']);
        $t->same(true, $result['next917Ready']);
        $t->same(true, $result['next921Ready']);
        $t->same(64, strlen($result['next925Final']));
        $t->same(true, $result['next925Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next910-925 ' . $name] = $callback;
}

return $tests;
