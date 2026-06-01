<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card:w\68 ere(:global(.wp-block-card), .is-featured) {
  color: red;
}

.card:h\61 s(> .media, + :global(.wp-block-image)) {
  color: blue;
}

.card:n\6f t(.is-disabled, :global(.is-preview)) {
  opacity: .8;
}

.card:-WEBKIT-ANY(:local(.is-wide)) {
  margin: 0;
}

.button {
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
    'buttonClassList' => CssModulesTransformer::exportClassList($result['exports'], 'button'),
];

$expected = [
    'code' => '.BlockA_card:where(.wp-block-card,.BlockA_is-featured){color:red}.BlockA_card:has(>.BlockA_media,+.wp-block-image){color:#00f}.BlockA_card:not(.BlockA_is-disabled,.is-preview){opacity:.8}.BlockA_card:-webkit-any(.BlockA_is-wide){margin:0}.BlockA_button{color:#fff}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-featured' => [
            'name' => 'BlockA_is-featured',
            'composes' => [],
            'isReferenced' => false,
        ],
        'media' => [
            'name' => 'BlockA_media',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-disabled' => [
            'name' => 'BlockA_is-disabled',
            'composes' => [],
            'isReferenced' => false,
        ],
        'is-wide' => [
            'name' => 'BlockA_is-wide',
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
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules selector-function canonical output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
