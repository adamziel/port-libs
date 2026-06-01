<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:is(:global(.wp-block-button, .wp-block-file), .isHighlighted, .isSelected) {
  color: red;
}

.card:has(:global(.wp-block-image, .wp-block-gallery), .media) {
  color: blue;
}

.card:nth-child(2n of :global(.wp-block-post, .wp-block-page), .card, :global(.is-layout-flow)) {
  margin: 0;
}

.panel {
  composes: card;
  color: white;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'panelClassList' => CssModulesTransformer::exportClassList($result['exports'], 'panel'),
];

$expected = [
    'code' => '.BlockA_card:is(.BlockA_isHighlighted,.BlockA_isSelected){color:red}.BlockA_card:has(.BlockA_media){color:#00f}.BlockA_card:nth-child(2n of .BlockA_card,.is-layout-flow){margin:0}.BlockA_panel{color:#fff}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'isHighlighted' => [
            'name' => 'BlockA_isHighlighted',
            'composes' => [],
            'isReferenced' => false,
        ],
        'isSelected' => [
            'name' => 'BlockA_isSelected',
            'composes' => [],
            'isReferenced' => false,
        ],
        'media' => [
            'name' => 'BlockA_media',
            'composes' => [],
            'isReferenced' => false,
        ],
        'panel' => [
            'name' => 'BlockA_panel',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_card',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'panelClassList' => 'BlockA_panel BlockA_card',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules forgiving selector output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'panel-class-list: ' . $actual['panelClassList'] . PHP_EOL;
