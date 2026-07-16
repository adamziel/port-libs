<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-design-token card {
  gap: 8px;
}
CSS;

$seenExitBody = null;
$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-design-token' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], [
    'Rule' => [
        'custom' => [
            'wp-design-token' => static function (array $rule): array {
                $rule['body'] = <<<'CSS'
gap: 16px;
color: theme-token('accent');
CSS;

                return [
                    'type' => 'custom',
                    'value' => $rule,
                ];
            },
        ],
    ],
    'Length' => static fn (array $length): ?array => ($length['unit'] ?? null) === 'px'
        ? ['unit' => 'rem', 'value' => ((float) $length['value']) / 16]
        : null,
    'Function' => [
        'theme-token' => static fn (array $arguments): ?array => ($arguments[0] ?? null) === 'accent'
            ? ['raw' => 'var(--wp-card-accent)']
            : null,
    ],
    'RuleExit' => [
        'custom' => [
            'wp-design-token' => static function (array $rule) use (&$seenExitBody): ?array {
                $seenExitBody = $rule['body'];

                return null;
            },
        ],
    ],
]);

$expected = '@wp-design-token card{gap:1rem;color:var(--wp-card-accent)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected returned declaration-list custom at-rule output:\n{$result}\n");
        exit(1);
    }

    if ($seenExitBody !== 'gap:1rem;color:var(--wp-card-accent)') {
        fwrite(STDERR, "Unexpected returned declaration-list exit body:\n{$seenExitBody}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
