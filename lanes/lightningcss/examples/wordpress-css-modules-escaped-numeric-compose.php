<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.\31 23, .wpBlockCard {
  composes: \31 23-base;
  color: red;
}

:global(.\31 23) .wpBlockCard {
  border-color: yellow;
}

.\31 23-base {
  margin: 0;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'numericClassList' => CssModulesTransformer::exportClassList($result['exports'], '123'),
    'cardClassList' => CssModulesTransformer::exportClassList($result['exports'], 'wpBlockCard'),
];

$expected = [
    'code' => '.BlockA_123,.BlockA_wpBlockCard{color:red}.\31 23 .BlockA_wpBlockCard{border-color:#ff0}.BlockA_123-base{margin:0}',
    'exports' => [
        123 => [
            'name' => 'BlockA_123',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_123-base',
                ],
            ],
            'isReferenced' => false,
        ],
        'wpBlockCard' => [
            'name' => 'BlockA_wpBlockCard',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_123-base',
                ],
            ],
            'isReferenced' => false,
        ],
        '123-base' => [
            'name' => 'BlockA_123-base',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'numericClassList' => 'BlockA_123 BlockA_123-base',
    'cardClassList' => 'BlockA_wpBlockCard BlockA_123-base',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected escaped numeric CSS Modules compose output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'numeric-class-list: ' . $actual['numericClassList'] . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
