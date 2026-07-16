<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-spacing wp {
  gap: var(--wp-card-stack);
  padding: env(--wp-safe-gap);
}

.wp-block-card {
  display: grid;
}
CSS;

$events = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'VariableExit' => [
            '--wp-card-stack' => static function (array $variable) use (&$events): array {
                $events[] = ['VariableExit', $variable['name']['ident']];

                return [
                    [
                        'type' => 'function',
                        'value' => [
                            'name' => 'wp-var-step',
                            'arguments' => [],
                        ],
                    ],
                    [
                        'type' => 'length',
                        'unit' => 'px',
                        'value' => 16,
                    ],
                ];
            },
        ],
        'EnvironmentVariableExit' => [
            '--wp-safe-gap' => static function (array $environmentVariable) use (&$events): array {
                $events[] = ['EnvironmentVariableExit', $environmentVariable['name']['ident']];

                return [
                    [
                        'type' => 'function',
                        'value' => [
                            'name' => 'wp-env-step',
                            'arguments' => [],
                        ],
                    ],
                    [
                        'type' => 'length',
                        'unit' => 'px',
                        'value' => 24,
                    ],
                ];
            },
        ],
    ],
    [
        'FunctionExit' => [
            'wp-var-step' => static function (array $function) use (&$events): array {
                $events[] = ['FunctionExit', $function['name']];

                return [
                    'type' => 'length',
                    'unit' => 'px',
                    'value' => 8,
                ];
            },
            'wp-env-step' => static function (array $function) use (&$events): array {
                $events[] = ['FunctionExit', $function['name']];

                return [
                    'type' => 'length',
                    'unit' => 'px',
                    'value' => 32,
                ];
            },
        ],
        'Length' => static function (array $length) use (&$events): ?array {
            $events[] = ['Length', $length['unit'] . ':' . $length['value']];

            return $length['unit'] === 'px'
                ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                : null;
        },
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-spacing' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], $visitor);

$expected = '@wp-spacing wp{gap:0.5rem 1rem;padding:2rem 1.5rem}.wp-block-card{display:grid}';
$expectedEvents = [
    ['VariableExit', '--wp-card-stack'],
    ['FunctionExit', 'wp-var-step'],
    ['Length', 'px:8'],
    ['Length', 'px:16'],
    ['EnvironmentVariableExit', '--wp-safe-gap'],
    ['FunctionExit', 'wp-env-step'],
    ['Length', 'px:32'],
    ['Length', 'px:24'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected exit-array function visitor output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected exit-array function visitor events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
