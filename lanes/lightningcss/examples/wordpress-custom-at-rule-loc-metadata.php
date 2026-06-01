<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tokens accent;

@wp-source "theme.json";
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
    ],
], [
    'StyleSheet' => static function (array $stylesheet) use (&$seen): void {
        $seen['stylesheet'] = [
            'custom' => $stylesheet['rules'][0]['value']['loc'] ?? null,
            'unknown' => $stylesheet['rules'][1]['value']['loc'] ?? null,
        ];
    },
    'Rule' => [
        'custom' => [
            'tokens' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                $seen['custom'] = $rule['loc'] ?? null;

                return $transformer->styleRule('.wp-token-source', [
                    ['property' => 'color', 'value' => 'yellow'],
                ]);
            },
        ],
        'unknown' => [
            'wp-source' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                $seen['unknown'] = $rule['loc'] ?? null;

                return $transformer->styleRule('.wp-source-location', [
                    ['property' => 'outline-color', 'value' => '#056ef0'],
                ]);
            },
        ],
    ],
]);

$expected = '.wp-token-source{color:#ff0}.wp-source-location{outline-color:#056ef0}';
$expectedLocations = [
    'stylesheet' => [
        'custom' => ['source_index' => 0, 'line' => 0, 'column' => 1],
        'unknown' => ['source_index' => 0, 'line' => 2, 'column' => 1],
    ],
    'custom' => ['source_index' => 0, 'line' => 0, 'column' => 1],
    'unknown' => ['source_index' => 0, 'line' => 2, 'column' => 1],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule loc metadata output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== $expectedLocations) {
        fwrite(STDERR, "Unexpected custom at-rule loc metadata:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
