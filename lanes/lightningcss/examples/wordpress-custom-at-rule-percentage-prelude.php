<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-fluid-step 25% wp-scale(50%);

.wp-block-card {
  gap: var(--wp-fluid-step);
}
CSS;

$events = [];
$seenRule = null;
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Token' => [
            'percentage' => static function (array $token) use (&$events): array {
                $events[] = 'token:' . $token['raw'] . ':' . $token['value'];

                return [
                    'type' => 'length',
                    'unit' => 'px',
                    'value' => $token['value'] * 100,
                ];
            },
        ],
        'FunctionExit' => [
            'wp-scale' => static function (array $function) use (&$events): array {
                $argument = $function['arguments'][0] ?? [];
                $token = is_array($argument['value'] ?? null) ? $argument['value'] : [];
                $events[] = 'function:' . ($token['type'] ?? '') . ':' . ($token['value'] ?? '');

                return $argument;
            },
        ],
    ],
    [
        'Rule' => [
            'custom' => [
                'wp-fluid-step' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$events, &$seenRule): array {
                    $events[] = 'rule:' . $rule['prelude'];
                    $seenRule = $rule;

                    return $transformer->styleRule(':root', [
                        [
                            'property' => '--wp-fluid-step',
                            'value' => $rule['prelude'],
                            'important' => false,
                        ],
                    ]);
                },
            ],
        ],
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-fluid-step' => [
        'prelude' => '*',
    ],
], $visitor);

$expected = ':root{--wp-fluid-step:25px 50px}.wp-block-card{gap:var(--wp-fluid-step)}';
$expectedEvents = [
    'token:25%:0.25',
    'function:percentage:0.5',
    'token:50%:0.5',
    'rule:25px 50px',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom percentage prelude output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected custom percentage prelude events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    if (($seenRule['preludeAst']['value'][0]['type'] ?? null) !== 'length') {
        fwrite(STDERR, "Unexpected custom percentage prelude AST:\n" . json_encode($seenRule['preludeAst'] ?? null) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
