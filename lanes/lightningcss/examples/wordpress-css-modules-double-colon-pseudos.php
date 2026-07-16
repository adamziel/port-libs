<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
::global(.wp-block-button) {
  color: red;
}

::local(.card) {
  color: yellow;
}

.card::global(.legacy) {
  color: blue;
}

::global .card {
  color: green;
}

.button {
  composes: card;
  color: white;
}

.card {
  color: black;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$invalid = [];
foreach ([
    '.card::global(.legacy) { composes: base; color: red }',
    '::global .card { composes: base; color: red }',
    '::local(.card) { composes: base; color: red }',
] as $source) {
    try {
        (new CssModulesTransformer())->transform($source, [
            'hash' => 'BlockA',
        ]);
        $invalid[$source] = 'accepted';
    } catch (InvalidArgumentException $exception) {
        $invalid[$source] = $exception->getMessage();
    }
}

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
    'invalid' => $invalid,
];

$expected = [
    'code' => '::global(.wp-block-button){color:red}::local(.card){color:#ff0}.BlockA_card::global(.legacy){color:#00f}::global .BlockA_card{color:green}.BlockA_button{color:#fff}.BlockA_card{color:#000}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
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
    'invalid' => [
        '.card::global(.legacy) { composes: base; color: red }' => 'The `composes` property cannot be used with a simple class selector',
        '::global .card { composes: base; color: red }' => 'The `composes` property cannot be used with a simple class selector',
        '::local(.card) { composes: base; color: red }' => 'The `composes` property cannot be used with a simple class selector',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules double-colon pseudo output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
echo json_encode($actual['invalid'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
