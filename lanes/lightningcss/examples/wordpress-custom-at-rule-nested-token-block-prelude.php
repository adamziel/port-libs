<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-block-state [data-state=draft] (16px draft);

.wp-block-card {
  color: red;
}
CSS;

$events = [];
$prelude = null;
$preludeAstTypes = [];

$result = (new CustomAtRuleTransformer())->transform($css, [
    'wp-block-state' => ['prelude' => '*'],
], [
    'Token' => [
        'ident' => static function (array $token) use (&$events): ?string {
            if (!in_array($token['value'], ['data-state', 'draft'], true)) {
                return null;
            }

            $events[] = ['Token.ident', $token['value']];

            return match ($token['value']) {
                'data-state' => 'data-wp-state',
                'draft' => 'published',
                default => null,
            };
        },
    ],
    'Length' => static function (array $length) use (&$events): ?array {
        if ($length['unit'] !== 'px') {
            return null;
        }

        $events[] = ['Length', $length['unit'] . ':' . $length['value']];

        return ['unit' => 'rem', 'value' => $length['value'] / 16];
    },
    'Rule' => [
        'custom' => [
            'wp-block-state' => static function (array $rule) use (&$prelude, &$preludeAstTypes): array {
                $prelude = $rule['prelude'];
                $preludeAstTypes = array_map(static function (array $component): string {
                    if (($component['type'] ?? null) !== 'token') {
                        return (string) ($component['type'] ?? '');
                    }

                    $token = $component['value'] ?? [];
                    $type = (string) ($token['type'] ?? '');

                    return $type === 'delim' ? 'delim:' . ($token['value'] ?? '') : $type;
                }, $rule['preludeAst']['value'] ?? []);

                return [];
            },
        ],
    ],
]);

$expected = '.wp-block-card{color:red}';
$expectedEvents = [
    ['Token.ident', 'data-state'],
    ['Token.ident', 'draft'],
    ['Length', 'px:16'],
    ['Token.ident', 'draft'],
];
$expectedPrelude = '[data-wp-state=published] (1rem published)';
$expectedPreludeAstTypes = [
    'square-bracket-block',
    'ident',
    'delim:=',
    'ident',
    'close-square-bracket',
    'parenthesis-block',
    'length',
    'ident',
    'close-parenthesis',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected nested token block output:\n{$result}\n");
        exit(1);
    }

    if ($events !== $expectedEvents) {
        fwrite(STDERR, "Unexpected nested token block visitor events:\n" . json_encode($events) . "\n");
        exit(1);
    }

    if ($prelude !== $expectedPrelude || $preludeAstTypes !== $expectedPreludeAstTypes) {
        fwrite(STDERR, "Unexpected nested token block prelude:\n" . json_encode([$prelude, $preludeAstTypes]) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
