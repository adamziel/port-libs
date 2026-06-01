<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@supports (display: grid) {
  .wp-block-post-template {
    width: 32px;
  }
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], CustomAtRuleTransformer::composeVisitors([
    [
        'Length' => static function (array $length): ?array {
            return $length['unit'] === 'px'
                ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                : null;
        },
    ],
    [
        'RuleExit' => [
            'supports' => static function (array $rule) use (&$seen): array {
                $seen = [
                    'condition' => $rule['value']['condition']['value'] ?? null,
                    'child' => $rule['value']['rules'][0]['value']['selectors'][0][0]['name'] ?? null,
                    'width' => $rule['value']['rules'][0]['value']['declarations']['declarations'][0]['raw'] ?? null,
                ];
                $rule['value']['condition'] = [
                    'type' => 'declaration',
                    'propertyId' => ['property' => 'display'],
                    'value' => 'flex',
                ];

                return $rule;
            },
        ],
    ],
]));

$expected = '@supports (display:flex){.wp-block-post-template{width:2rem}}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected supports visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== [
        'condition' => 'grid',
        'child' => 'wp-block-post-template',
        'width' => '2rem',
    ]) {
        fwrite(STDERR, "Unexpected supports visitor AST: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
