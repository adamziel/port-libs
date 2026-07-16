<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@container wp-query-card (inline-size > 45em) {
  .wp-block-post-title {
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
    'RuleExit' => [
        'container' => static function (array $rule) use (&$seen): array {
            $feature = $rule['value']['condition']['value'] ?? [];
            $seen = [
                'name' => $rule['value']['name'] ?? null,
                'feature' => is_array($feature) ? ($feature['name'] ?? null) : null,
                'margin' => $rule['value']['rules'][0]['value']['declarations']['declarations'][0]['raw'] ?? null,
            ];
            $rule['value']['name'] = 'wp-query-card--wide';
            $rule['value']['condition'] = ['raw' => '(inline-size > 48rem)'];

            return $rule;
        },
    ],
]);

$expected = '@container wp-query-card--wide (inline-size>48rem){.wp-block-post-title{margin-top:1rem}}';
$expectedSeen = [
    'name' => 'wp-query-card',
    'feature' => 'inline-size',
    'margin' => '1rem',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected container visitor output:\n{$result}\n");
        exit(1);
    }

    if ($seen !== $expectedSeen) {
        fwrite(STDERR, "Unexpected container visitor AST: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
