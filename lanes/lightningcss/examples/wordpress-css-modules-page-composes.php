<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@page {
  composes: printShell from global;
  margin: 0.5in;
}

@page cardPrint {
  composes: pageCover pageFooter from "./print-tokens.module.css";
  margin: 1in;
}

.card {
  composes: reset;
  color: red;
}

.reset {
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'cardClassList' => CssModulesTransformer::exportClassList($result['exports'], 'card'),
];

$expected = [
    'code' => '@page{composes:BlockA_printShell from global;margin:.5in}@page cardPrint{composes:BlockA_pageCover BlockA_pageFooter from "./print-tokens.module.css";margin:1in}.BlockA_card{color:red}.BlockA_reset{color:#00f}',
    'exports' => [
        'printShell' => [
            'name' => 'BlockA_printShell',
            'composes' => [],
            'isReferenced' => false,
        ],
        'pageCover' => [
            'name' => 'BlockA_pageCover',
            'composes' => [],
            'isReferenced' => false,
        ],
        'pageFooter' => [
            'name' => 'BlockA_pageFooter',
            'composes' => [],
            'isReferenced' => false,
        ],
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_reset',
                ],
            ],
            'isReferenced' => false,
        ],
        'reset' => [
            'name' => 'BlockA_reset',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'cardClassList' => 'BlockA_card BlockA_reset',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules @page composes output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
