<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-block-variant [data-state~=draft] [lang|=en] [href^=shop] [file$=pdf] [class*=wp-block];

.wp-block-card {
  color: red;
}
CSS;

$events = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-block-variant' => [
        'prelude' => '*',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Token' => [
            'include-match' => static function (array $token) use (&$events): ?array {
                $events[] = $token['type'] . ':' . ($token['raw'] ?? '');

                return null;
            },
            'dash-match' => static function (array $token) use (&$events): ?array {
                $events[] = $token['type'] . ':' . ($token['raw'] ?? '');

                return null;
            },
            'prefix-match' => static function (array $token) use (&$events): ?array {
                $events[] = $token['type'] . ':' . ($token['raw'] ?? '');

                return null;
            },
            'suffix-match' => static function (array $token) use (&$events): ?array {
                $events[] = $token['type'] . ':' . ($token['raw'] ?? '');

                return null;
            },
            'substring-match' => static function (array $token) use (&$events): ?array {
                $events[] = $token['type'] . ':' . ($token['raw'] ?? '');

                return null;
            },
        ],
    ],
    [
        'Rule' => [
            'custom' => [
                'wp-block-variant' => static function (array $rule, CustomAtRuleTransformer $transformer): array {
                    return $transformer->styleRule(':root', [[
                        'property' => '--wp-block-variant-selector',
                        'value' => $rule['prelude'],
                        'important' => false,
                    ]]);
                },
            ],
        ],
    ],
]));

$expected = ':root{--wp-block-variant-selector:[data-state~=draft][lang|=en][href^=shop][file$=pdf][class*=wp-block]}.wp-block-card{color:red}';
$expectedEvents = [
    'include-match:~=',
    'dash-match:|=',
    'prefix-match:^=',
    'suffix-match:$=',
    'substring-match:*=',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected selector match token output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected selector match token events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
