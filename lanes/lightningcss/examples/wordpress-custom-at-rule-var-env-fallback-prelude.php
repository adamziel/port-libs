<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token var(--wp-card-gap, 24px draft);
@wp-safe env(--wp-safe-gap 1, 16px draft);

.wp-block-card {
  color: red;
}
CSS;

$events = [];
$rules = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-token' => ['prelude' => '*'],
    'wp-safe' => ['prelude' => '*'],
], [
    'Length' => static function (array $length) use (&$events): ?array {
        if ($length['unit'] !== 'px') {
            return null;
        }

        $events[] = 'length:' . $length['value'] . $length['unit'];

        return ['unit' => 'rem', 'value' => $length['value'] / 16];
    },
    'Token' => [
        'ident' => static function (array $token) use (&$events): ?string {
            if (($token['value'] ?? null) !== 'draft') {
                return null;
            }

            $events[] = 'token:ident:' . $token['value'];

            return 'published';
        },
    ],
    'VariableExit' => [
        '--wp-card-gap' => static function (array $variable) use (&$events): ?array {
            $events[] = 'var-exit:' . implode(',', array_map(
                static fn (array $component): string => $component['type'] ?? 'raw',
                $variable['fallback'] ?? []
            ));

            return null;
        },
    ],
    'EnvironmentVariableExit' => [
        '--wp-safe-gap' => static function (array $environmentVariable) use (&$events): ?array {
            $events[] = 'env-exit:' . implode(',', array_map(
                static fn (array $component): string => $component['type'] ?? 'raw',
                $environmentVariable['fallback'] ?? []
            ));

            return null;
        },
    ],
    'Rule' => [
        'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$events, &$rules): array {
            $events[] = 'rule:' . $rule['prelude'];
            $rules[] = $rule['preludeAst'];

            return $transformer->styleRule(':root', [[
                'property' => '--wp-token-' . count($rules),
                'value' => $rule['prelude'],
                'important' => false,
            ]]);
        },
    ],
]);

$expected = ':root{--wp-token-1:var(--wp-card-gap,1.5rem published);--wp-token-2:env(--wp-safe-gap 1,1rem published)}.wp-block-card{color:red}';
$expectedEvents = [
    'length:24px',
    'token:ident:draft',
    'var-exit:length,token',
    'rule:var(--wp-card-gap,1.5rem published)',
    'var-exit:raw',
    'length:16px',
    'token:ident:draft',
    'env-exit:length,token',
    'rule:env(--wp-safe-gap 1,1rem published)',
    'env-exit:raw',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule fallback-prelude output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected custom at-rule fallback-prelude events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    if (
        count($rules) !== 2
        || (($rules[0]['value'][0]['value']['fallback'][0]['type'] ?? null) !== 'length')
        || (($rules[0]['value'][0]['value']['fallback'][1]['value']['value'] ?? null) !== 'published')
        || (($rules[1]['value'][0]['value']['fallback'][0]['type'] ?? null) !== 'length')
        || (($rules[1]['value'][0]['value']['fallback'][1]['value']['value'] ?? null) !== 'published')
    ) {
        fwrite(STDERR, "Unexpected custom at-rule fallback-prelude AST:\n" . json_encode($rules) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
