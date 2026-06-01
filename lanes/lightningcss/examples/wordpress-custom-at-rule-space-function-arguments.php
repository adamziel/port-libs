<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-design-token theme(16px --wp-gap draft);

.wp-block-card {
  color: red;
}
CSS;

$events = [];
$seenPreludeAst = null;
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'wp-design-token' => [
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
        if ($ident !== '--wp-gap') {
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
    'Rule' => [
        'custom' => [
            'wp-design-token' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$events, &$seenPreludeAst): array {
                $events[] = 'rule:' . $rule['prelude'];
                $seenPreludeAst = $rule['preludeAst'];

                return $transformer->styleRule(':root', [
                    [
                        'property' => '--wp-design-token-prelude',
                        'value' => $rule['prelude'],
                        'important' => false,
                    ],
                ]);
            },
        ],
    ],
]);

$expected = ':root{--wp-design-token-prelude:theme(1rem --theme-wp-gap live)}.wp-block-card{color:red}';
$expectedEvents = [
    'length:16px',
    'dashed:--wp-gap',
    'token:ident:draft',
    'rule:theme(1rem --theme-wp-gap live)',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule space function output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected custom at-rule space function events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    $function = $seenPreludeAst['value'][0]['value'] ?? null;
    if (!is_array($function) || ($function['name'] ?? null) !== 'theme' || ($function['argumentSeparator'] ?? null) !== 'space') {
        fwrite(STDERR, "Unexpected custom at-rule space function AST:\n" . json_encode($seenPreludeAst) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
