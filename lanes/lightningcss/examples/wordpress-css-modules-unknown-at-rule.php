<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wordpress-tokens {
  .raw { composes: card; color: red }
  :global(.wp-block-button) .raw { color: yellow }
}

.card {
  composes: reset;
  color: blue;
}

.reset {
  color: green;
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
    'code' => '@wordpress-tokens{.raw { composes: card; color: red } :global(.wp-block-button) .raw { color: yellow }}.BlockA_card{color:#00f}.BlockA_reset{color:green}',
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
        fwrite(STDERR, "Unexpected CSS Modules unknown at-rule output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
