<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  ;
  composes: reset;
  ;
  composes: wp-block-button from global;
  composes: token from "./theme.module.css";
  ;;
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
    'cardClassList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'card',
        static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './theme.module.css'
            ? 'Theme_token'
            : null
    ),
];

$expected = [
    'code' => '.BlockA_card{color:red}.BlockA_reset{color:#00f}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_reset',
                ],
                [
                    'type' => 'global',
                    'name' => 'wp-block-button',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'token',
                    'specifier' => './theme.module.css',
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
    'cardClassList' => 'BlockA_card BlockA_reset wp-block-button Theme_token',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules empty compose statement output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
