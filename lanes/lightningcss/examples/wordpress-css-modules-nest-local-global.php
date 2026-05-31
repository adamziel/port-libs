<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  color: red;

  @nest :global(.wp-block-group) & {
    color: blue;
  }

  @nest :local(.themeVariant) & {
    color: yellow;
  }

  @nest &:where(:global(.is-wide), .featured) {
    color: green;
  }

  composes: reset;
}

.reset {
  margin: 0;
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
    'code' => '.BlockA_card{color:red}.wp-block-group .BlockA_card{color:#00f}.BlockA_themeVariant .BlockA_card{color:#ff0}.BlockA_card:where(.is-wide,.BlockA_featured){color:green}.BlockA_reset{margin:0}',
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
        'themeVariant' => [
            'name' => 'BlockA_themeVariant',
            'composes' => [],
            'isReferenced' => false,
        ],
        'featured' => [
            'name' => 'BlockA_featured',
            'composes' => [],
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
        fwrite(STDERR, "Unexpected CSS Modules @nest local/global output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'card-class-list: ' . $actual['cardClassList'] . PHP_EOL;
