<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-block-token theme(24px --wp-card-gap draft);

.wp-block-card {
  color: red;
}
CSS;

$events = [];
$exitArguments = null;
$exitSeparator = null;
$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-block-token' => [
        'prelude' => '*',
    ],
], [
    'Length' => static function (array $length) use (&$events): ?array {
        if ($length['unit'] !== 'px') {
            return null;
        }

        $events[] = 'length:' . $length['value'] . $length['unit'];

        return ['unit' => 'rem', 'value' => $length['value'] / 16];
    },
    'DashedIdent' => static function (string $ident) use (&$events): string {
        if ($ident !== '--wp-card-gap') {
            return $ident;
        }

        $events[] = 'dashed:' . $ident;

        return '--theme-' . substr($ident, 2);
    },
    'Token' => [
        'ident' => static function (array $token) use (&$events): ?string {
            if (($token['value'] ?? null) !== 'draft') {
                return null;
            }

            $events[] = 'token:ident:' . $token['value'];

            return 'live';
        },
    ],
    'FunctionExit' => [
        'theme' => static function (array $function) use (&$events, &$exitArguments, &$exitSeparator): ?array {
            if (($function['argumentSeparator'] ?? null) !== 'space') {
                return null;
            }

            $events[] = 'exit:' . implode(',', array_map(
                static fn (array $argument): string => $argument['type'] ?? 'raw',
                $function['arguments'] ?? []
            ));
            $exitArguments = $function['arguments'] ?? null;
            $exitSeparator = $function['argumentSeparator'] ?? null;

            return null;
        },
    ],
    'Rule' => [
        'custom' => [
            'wp-block-token' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$events): array {
                $events[] = 'rule:' . $rule['prelude'];

                return $transformer->styleRule(':root', [
                    [
                        'property' => '--wp-block-token',
                        'value' => $rule['prelude'],
                        'important' => false,
                    ],
                ]);
            },
        ],
    ],
]);

$expected = ':root{--wp-block-token:theme(1.5rem --theme-wp-card-gap live)}.wp-block-card{color:red}';
$expectedEvents = [
    'length:24px',
    'dashed:--wp-card-gap',
    'token:ident:draft',
    'exit:length,dashed-ident,token',
    'rule:theme(1.5rem --theme-wp-card-gap live)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule space FunctionExit output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected custom at-rule space FunctionExit events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    if ($exitSeparator !== 'space') {
        fwrite(STDERR, "Unexpected custom at-rule FunctionExit separator:\n" . json_encode($exitSeparator) . "\n");
        exit(1);
    }

    if (!is_array($exitArguments) || array_column($exitArguments, 'type') !== ['length', 'dashed-ident', 'token']) {
        fwrite(STDERR, "Unexpected custom at-rule FunctionExit arguments:\n" . json_encode($exitArguments) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
