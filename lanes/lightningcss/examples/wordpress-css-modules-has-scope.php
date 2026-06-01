<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.blockCard:has(:scope > :global(.wp-block-button)) {
  margin: 0;
}

.blockCard:has(:scope + :local(.blockCaption)) {
  color: blue;
}

.blockCard:has(:scope .media) {
  background: white;
}

.blockCard:has(:scope) {
  outline: 0;
}

.blockCardVariant {
  composes: blockCard;
  color: red;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

$actual = [
    'code' => $result['code'],
    'exports' => $result['exports'],
    'variantClassList' => CssModulesTransformer::exportClassList($result['exports'], 'blockCardVariant'),
];

$expected = [
    'code' => '.BlockA_blockCard:has(>.wp-block-button){margin:0}.BlockA_blockCard:has(+.BlockA_blockCaption){color:#00f}.BlockA_blockCard:has( .BlockA_media){background:#fff}.BlockA_blockCard:has(){outline:0}.BlockA_blockCardVariant{color:red}',
    'exports' => [
        'blockCard' => [
            'name' => 'BlockA_blockCard',
            'composes' => [],
            'isReferenced' => false,
        ],
        'blockCaption' => [
            'name' => 'BlockA_blockCaption',
            'composes' => [],
            'isReferenced' => false,
        ],
        'media' => [
            'name' => 'BlockA_media',
            'composes' => [],
            'isReferenced' => false,
        ],
        'blockCardVariant' => [
            'name' => 'BlockA_blockCardVariant',
            'composes' => [
                [
                    'type' => 'local',
                    'name' => 'BlockA_blockCard',
                ],
            ],
            'isReferenced' => false,
        ],
    ],
    'variantClassList' => 'BlockA_blockCardVariant BlockA_blockCard',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected CSS Modules :has(:scope) output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $actual['code'] . PHP_EOL;
echo json_encode($actual['exports'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'variant-class-list: ' . $actual['variantClassList'] . PHP_EOL;
