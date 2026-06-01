<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:is(:local(.featured)) {
  color: red;
}

.card:is(:global(.wp-block-button)) {
  color: blue;
}

.card:is([data-align="wide"]) {
  margin: 0;
}

.card:is(.wrapper .child) {
  color: purple;
}

.button {
  composes: card;
  color: white;
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
    'code' => '.BlockA_card.BlockA_featured{color:red}.BlockA_card.wp-block-button{color:#00f}.BlockA_card[data-align=wide]{margin:0}.BlockA_card:is(.BlockA_wrapper .BlockA_child){color:purple}.BlockA_button{color:#fff}',
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
        'wrapper' => [
            'name' => 'BlockA_wrapper',
            'composes' => [],
            'isReferenced' => false,
        ],
        'child' => [
            'name' => 'BlockA_child',
            'composes' => [],
            'isReferenced' => false,
        ],
        'button' => [
            'name' => 'BlockA_button',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_card',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules single :is() output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
