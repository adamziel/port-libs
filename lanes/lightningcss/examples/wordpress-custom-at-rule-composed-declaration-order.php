<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  size: 16px;
}
CSS;

$expandSize = [
    'Declaration' => [
        'custom' => [
            'size' => static fn (): array => [
                [
                    'property' => 'width',
                    'value' => [
                        'type' => 'length-percentage',
                        'value' => [
                            'type' => 'dimension',
                            'value' => ['unit' => 'px', 'value' => 16],
                        ],
                    ],
                ],
                [
                    'property' => 'height',
                    'value' => [
                        'type' => 'length-percentage',
                        'value' => [
                            'type' => 'dimension',
                            'value' => ['unit' => 'px', 'value' => 16],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
$removeWidth = [
    'Declaration' => [
        'width' => static fn (array $declaration): array|null => ($declaration['property'] ?? null) === 'width'
            ? []
            : null,
    ],
];

$forward = (new CustomAtRuleTransformer())->transform(
    $css,
    [],
    CustomAtRuleTransformer::composeVisitors([$expandSize, $removeWidth])
);
$reverse = (new CustomAtRuleTransformer())->transform(
    $css,
    [],
    CustomAtRuleTransformer::composeVisitors([$removeWidth, $expandSize])
);

$expected = '.wp-block-card{height:16px}';

if (($argv[1] ?? null) === '--self-test') {
    if ($forward !== $expected || $reverse !== $expected) {
        fwrite(STDERR, "Unexpected composed declaration visitor output:\nforward={$forward}\nreverse={$reverse}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $forward . PHP_EOL;
