<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  comp/* migration comment */oses: phantom;
  c\6f/* escaped migration comment */mposes: ghost from global;
  color: red;
}

.button {
  composes/* property comment before colon */: card /*! migration token */;
  c\6f mposes/* escaped property comment before colon */: wp-block-button /*! public token */ from /*! source token */ global;
  C\6f MPOSES/* dependency property comment before colon */: token /*! dependency token */ from /*! source token */ "./tokens.css";
  color: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'buttonClassList' => CssModulesTransformer::exportClassList(
        $result['exports'],
        'button',
        static fn (string $name, string $specifier): ?string => $name === 'token' && $specifier === './tokens.css'
            ? 'Theme_token'
            : null
    ),
];

$expected = [
    'code' => '.BlockA_card{color:red}.BlockA_button{color:#00f}',
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
                [
                    'type' => 'global',
                    'name' => 'wp-block-button',
                ],
                [
                    'type' => 'dependency',
                    'name' => 'token',
                    'specifier' => './tokens.css',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'buttonClassList' => 'BlockA_button BlockA_card wp-block-button Theme_token',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected commented CSS Modules composes property output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
