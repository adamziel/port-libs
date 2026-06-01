<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  c\6f mposes: reset;
  c\6f mposes: wp-block-card from g\6c obal;
  C\6f MPOSES: token from "./tokens.css";
  color: red;
}

.reset {
  color: white;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'classList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'card',
        static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
            ? 'Theme_token'
            : null
    ),
];

$expected = [
    'code' => '.BlockA_card{color:red}.BlockA_reset{color:#fff}',
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
                    'name' => 'wp-block-card',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'token',
                    'specifier' => './tokens.css',
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
    'classList' => 'BlockA_card BlockA_reset wp-block-card Theme_token',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected escaped CSS Modules composes property output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
