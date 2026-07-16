<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:global(.wp-block-card) .card:has(:scope::before:hover, :scope::highlight(editor-ring), :scope::cue(.caption), .media, :scope::part(icon) .dropped) {
  color: red;
}

.button {
  composes: card;
  color: white;
}

.card {
  color: blue;
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
    'code' => '.wp-block-card .BlockA_card:has(:before:hover,::highlight(BlockA_editor-ring),::cue(.BlockA_caption),.BlockA_media){color:red}.BlockA_button{color:#fff}.BlockA_card{color:#00f}',
    'exports' => [
        'card' => [
            'name' => 'BlockA_card',
            'composes' => [],
            'isReferenced' => false,
        ],
        'editor-ring' => [
            'name' => 'BlockA_editor-ring',
            'composes' => [],
            'isReferenced' => false,
        ],
        'caption' => [
            'name' => 'BlockA_caption',
            'composes' => [],
            'isReferenced' => false,
        ],
        'media' => [
            'name' => 'BlockA_media',
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
        fwrite(STDERR, "Unexpected CSS Modules :has() pseudo-element output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'button-class-list: ' . $actual['buttonClassList'] . PHP_EOL;
