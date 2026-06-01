<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  c\6f mposes: reset/* block layout separator */layout;
  c\6f mposes: wp-block-card/* public utility separator */is-wide from g\6c obal;
  C\6f MPOSES: token/* dependency separator */shadow from "./tokens.css";
  color: red;
}

.reset {
  color: white;
}

.layout {
  display: grid;
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
        static fn (string $name, string $specifier): ?string => $specifier === './tokens.css'
            ? [
                'token' => 'Theme_token',
                'shadow' => 'Theme_shadow',
            ][$name] ?? null
            : null
    ),
];

$expected = [
    'code' => '.BlockA_card{color:red}.BlockA_reset{color:#fff}.BlockA_layout{display:grid}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_reset',
                ],
                [
                    'type' => 'local',
                    'name' => 'BlockA_layout',
                ],
                [
                    'type' => 'global',
                    'name' => 'wp-block-card',
                ],
                [
                    'type' => 'global',
                    'name' => 'is-wide',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'token',
                    'specifier' => './tokens.css',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'shadow',
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
        'layout' => [
            'name' => 'BlockA_layout',
            'composes' => [],
            'isReferenced' => false,
        ],
    ],
    'classList' => 'BlockA_card BlockA_reset BlockA_layout wp-block-card is-wide Theme_token Theme_shadow',
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
