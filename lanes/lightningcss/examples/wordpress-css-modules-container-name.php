<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  color: red;
  container-name: query-card block-card;
  container-type: inline-size;
  composes: blockShell from "./block.module.css";
}

:global(.wp-block-group) .card {
  color: yellow;
  container: public-card / size;
}

.cardNarrow {
  composes: card;
  color: blue;
}

@container query-card (width > 320px) {
  .cardNarrow {
    color: white;
  }
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$dependencyClassName = static fn (string $name, string $specifier): ?string => $name === 'blockShell' && $specifier === './block.module.css'
    ? 'Theme_blockShell'
    : null;

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'classList' => CssModulesTransformer::exportClassList($result['exports'], 'card', $dependencyClassName),
    'narrowClassList' => CssModulesTransformer::exportClassList($result['exports'], 'cardNarrow', $dependencyClassName),
];

$expected = [
    'code' => '.BlockA_card{color:red;container:BlockA_query-card BlockA_block-card/inline-size}.wp-block-group .BlockA_card{color:#ff0;container:BlockA_public-card/size}.BlockA_cardNarrow{color:#00f}@container BlockA_query-card (width>320px){.BlockA_cardNarrow{color:#fff}}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [
                [
                    'type' => 'dependency',
                    'name' => 'blockShell',
                    'specifier' => './block.module.css',
                ],
            ],
            'isReferenced' => false,
        ],
        'query-card' => [
            'name' => 'BlockA_query-card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'block-card' => [
            'name' => 'BlockA_block-card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'public-card' => [
            'name' => 'BlockA_public-card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'cardNarrow' => [
            'name' => 'BlockA_cardNarrow',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'classList' => 'BlockA_card Theme_blockShell',
    'narrowClassList' => 'BlockA_cardNarrow BlockA_card Theme_blockShell',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules container-name output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'class-list: ' . $actual['classList'] . PHP_EOL;
echo 'narrow-class-list: ' . $actual['narrowClassList'] . PHP_EOL;
