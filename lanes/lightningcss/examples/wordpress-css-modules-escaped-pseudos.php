<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:lo\63 al(.card) {
  color: yellow;
}

:glo\62 al(.wp-block-button :lo\63 al(.legacy-button)) .card {
  color: red;
}

.button {
  composes: card;
  composes: wp-block-button from global;
  background: blue;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$expected = [
    'code' => '.BlockA_card{color:#ff0}.wp-block-button .legacy-button .BlockA_card{color:red}.BlockA_button{background:#00f}',
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
            ],
            'isReferenced' => false,
        ],
    ],
    'classList' => 'BlockA_button BlockA_card wp-block-button',
];

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'classList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected escaped CSS Modules pseudo output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
