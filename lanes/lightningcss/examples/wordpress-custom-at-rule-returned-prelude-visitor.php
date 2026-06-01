<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-alias placeholder;

.wp-block-card {
  color: red;
}
CSS;

$seenPrelude = null;
$events = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'custom' => [
                'wp-token-alias' => static function (array $rule): array {
                    $rule['prelude'] = [
                        ['type' => 'dashed-ident', 'value' => '--wp-card-gap'],
                        ['type' => 'length', 'value' => ['unit' => 'px', 'value' => 16.0]],
                        ['type' => 'function', 'value' => ['name' => 'theme', 'arguments' => [
                            ['type' => 'token', 'value' => ['type' => 'ident', 'value' => 'draft']],
                        ]]],
                    ];

                    return ['type' => 'custom', 'value' => $rule];
                },
            ],
        ],
    ],
    [
        'DashedIdent' => static function (string $ident) use (&$events): string {
            if ($ident !== '--wp-card-gap') {
                return $ident;
            }

            $events[] = 'dashed:' . $ident;

            return '--theme-' . substr($ident, 2);
        },
        'Length' => static function (array $length) use (&$events): ?array {
            if (($length['unit'] ?? null) !== 'px') {
                return null;
            }

            $events[] = 'length:' . $length['value'] . $length['unit'];

            return [
                'unit' => 'rem',
                'value' => ((float) $length['value']) / 16,
            ];
        },
        'RuleExit' => [
            'custom' => [
                'wp-token-alias' => static function (array $rule) use (&$seenPrelude): ?array {
                    $seenPrelude = $rule['prelude'];

                    return null;
                },
            ],
        ],
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-token-alias' => [
        'prelude' => '*',
    ],
], $visitor);

$expected = '@wp-token-alias --theme-wp-card-gap 1rem theme(draft);.wp-block-card{color:red}';
$expectedEvents = [
    'dashed:--wp-card-gap',
    'length:16px',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected returned custom at-rule prelude visitor output:\n{$result}\n");
        exit(1);
    }

    if ($seenPrelude !== '--theme-wp-card-gap 1rem theme(draft)') {
        fwrite(STDERR, "Unexpected returned custom at-rule RuleExit prelude:\n" . json_encode($seenPrelude) . "\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected returned custom at-rule prelude visitor events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
