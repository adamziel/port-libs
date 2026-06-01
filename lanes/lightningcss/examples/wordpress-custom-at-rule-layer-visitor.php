<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer reset, theme.blocks;
@layer theme.blocks {
  .wp-block-card {
    margin-top: 16px;
  }
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Length' => static function (array $length): ?array {
        return ($length['unit'] ?? null) === 'px'
            ? ['unit' => 'rem', 'value' => ((float) $length['value']) / 16]
            : null;
    },
    'Rule' => [
        'layer-statement' => static function (array $rule) use (&$seen): array {
            $seen['statement'] = $rule['value']['names'] ?? [];
            $rule['value']['names'][] = ['wp-overrides'];

            return $rule;
        },
    ],
    'RuleExit' => [
        'layer-block' => static function (array $rule) use (&$seen): array {
            $seen['block'] = [
                'name' => $rule['value']['name'] ?? null,
                'margin' => $rule['value']['rules'][0]['value']['declarations']['declarations'][0]['raw'] ?? null,
            ];
            $rule['value']['name'] = ['theme', 'components'];

            return $rule;
        },
    ],
]);

$expected = '@layer reset,theme.blocks,wp-overrides;@layer theme.components{.wp-block-card{margin-top:1rem}}';
$expectedSeen = [
    'statement' => [['reset'], ['theme', 'blocks']],
    'block' => [
        'name' => ['theme', 'blocks'],
        'margin' => '1rem',
    ],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected layer visitor output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== $expectedSeen) {
        fwrite(STDERR, "Unexpected layer visitor AST: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
