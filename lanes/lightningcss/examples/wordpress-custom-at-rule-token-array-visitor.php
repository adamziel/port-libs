<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  margin: theme("space");
  gap: wp-stack;
}
CSS;

$events = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Function' => [
            'theme' => static function (array $arguments, string $raw, string $name) use (&$events): ?array {
                $events[] = ['Function', $name, $arguments[0] ?? null, $raw];
                if (($arguments[0] ?? null) !== 'space') {
                    return null;
                }

                return [
                    ['type' => 'length', 'unit' => 'px', 'value' => 12],
                    ['type' => 'var', 'value' => ['name' => ['ident' => '--wp-card-gap'], 'fallback' => null]],
                ];
            },
        ],
        'Token' => [
            'ident' => static function (array $token) use (&$events): ?array {
                $events[] = ['Token.ident', $token['value'] ?? ''];
                if (($token['value'] ?? '') !== 'wp-stack') {
                    return null;
                }

                return [
                    ['type' => 'length', 'unit' => 'px', 'value' => 8],
                    ['type' => 'env', 'value' => ['name' => ['type' => 'custom', 'ident' => '--wp-stack-gap'], 'fallback' => null]],
                ];
            },
        ],
    ],
    [
        'Variable' => static function (array $variable) use (&$events): ?array {
            $name = $variable['name']['ident'] ?? '';
            $events[] = ['Variable', $name];

            return $name === '--wp-card-gap'
                ? ['type' => 'length', 'unit' => 'px', 'value' => 24]
                : null;
        },
        'EnvironmentVariable' => static function (array $environmentVariable) use (&$events): ?array {
            $name = $environmentVariable['name']['ident'] ?? '';
            $events[] = ['EnvironmentVariable', $name];

            return $name === '--wp-stack-gap'
                ? ['type' => 'length', 'unit' => 'px', 'value' => 16]
                : null;
        },
        'Length' => static function (array $length) use (&$events): ?array {
            $events[] = ['Length', $length['unit'] . ':' . $length['value']];

            return $length['unit'] === 'px'
                ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                : null;
        },
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);
$expected = '.wp-block-card{margin:.75rem 1.5rem;gap:0.5rem 1rem}';
$expectedEvents = [
    ['Function', 'theme', 'space', 'theme("space")'],
    ['Length', 'px:12'],
    ['Variable', '--wp-card-gap'],
    ['Length', 'px:24'],
    ['Token.ident', 'wp-stack'],
    ['Length', 'px:8'],
    ['EnvironmentVariable', '--wp-stack-gap'],
    ['Length', 'px:16'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected token-array visitor output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected token-array visitor events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
