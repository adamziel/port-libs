<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:state(open) {
  color: yellow;
}

:global(.wp-block-card:state(selected)) .card {
  outline-color: blue;
}

.card::highlight(focus-ring):hover {
  background: white;
}

:global(.wp-block-card::highlight(editor-ring)) .card {
  border-color: green;
}

.card {
  composes: reset;
  color: white;
}

.reset {
  margin: 0;
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
    'code' => '.BlockA_card:state(BlockA_open){color:#ff0}.wp-block-card:state(selected) .BlockA_card{outline-color:#00f}.BlockA_card::highlight(BlockA_focus-ring):hover{background:#fff}.wp-block-card::highlight(editor-ring) .BlockA_card{border-color:green}.BlockA_card{color:#fff}.BlockA_reset{margin:0}',
    'exports' => [
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
        'open' => [
            'name' => 'BlockA_open',
            'composes' => [],
            'isReferenced' => false,
        ],
        'focus-ring' => [
            'name' => 'BlockA_focus-ring',
            'composes' => [],
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
        fwrite(STDERR, "Unexpected CSS Modules state/highlight output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
