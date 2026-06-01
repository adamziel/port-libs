<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@keyframes wp-card {
  from {
    color: red;
    left: 16px;
  }

  to {
    color: green;
    left: 32px;
  }
}

.wp-block-card {
  animation-name: wp-card;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'CustomIdent' => static fn (string $ident): string => 'theme-' . $ident,
    'Rule' => [
        'keyframes' => static function (array $rule) use (&$seen): ?array {
            $seen['enter'] = $rule['value']['name']['value'] ?? null;

            return null;
        },
    ],
    'RuleExit' => [
        'keyframes' => static function (array $rule) use (&$seen): ?array {
            $seen['exit'] = [
                'name' => $rule['value']['name']['value'] ?? null,
                'left' => $rule['value']['keyframes'][0]['declarations']['declarations'][1]['raw'] ?? null,
            ];

            return null;
        },
    ],
    'Color' => static fn (array $color): ?array => ($color['type'] ?? null) === 'rgb'
        ? ['type' => 'rgb', 'r' => 0, 'g' => 0, 'b' => 255, 'alpha' => 1]
        : null,
    'Length' => static fn (array $length): ?array => ($length['unit'] ?? null) === 'px'
        ? ['unit' => 'rem', 'value' => ((float) $length['value']) / 16]
        : null,
]);

$expected = '@keyframes theme-wp-card{0%{color:#00f;left:1rem}to{color:#00f;left:2rem}}.wp-block-card{animation-name:theme-wp-card}';
$expectedSeen = [
    'enter' => 'wp-card',
    'exit' => [
        'name' => 'theme-wp-card',
        'left' => '1rem',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected keyframes visitor output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== $expectedSeen) {
        fwrite(STDERR, "Unexpected keyframes visitor AST: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
