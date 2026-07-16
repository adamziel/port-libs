<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card[data-layout="grid/feature"] {
  color: red;
}

:global(.wp-block-query[data-kind="core/query"]) .card:is([data-tone="accent.primary"], .featured) {
  color: yellow;
}

.button {
  composes: base;
  background: blue;
}

.base {
  color: green;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

$expected = [
    'code' => '.BlockA_card[data-layout=grid\/feature]{color:red}.wp-block-query[data-kind=core\/query] .BlockA_card:is([data-tone=accent\.primary],.BlockA_featured){color:#ff0}.BlockA_button{background:#00f}.BlockA_base{color:green}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'featured' => [
            'name' => 'BlockA_featured',
            'composes' => [],
            'isReferenced' => false,
        ],
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_base',
                ],
            ],
            'isReferenced' => false,
        ],
        'base' => [
            'name' => 'BlockA_base',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_base',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules attribute selector output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
