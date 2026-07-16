<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  color: currentColor;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Rule' => static function (array $rule) use (&$seen): array {
        $seen[] = [
            'type' => $rule['type'] ?? null,
            'selector' => $rule['value']['selectors'][0][0]['name'] ?? null,
            'property' => $rule['value']['declarations']['declarations'][0]['property'] ?? null,
            'value' => $rule['value']['declarations']['declarations'][0]['value'] ?? null,
        ];

        return $rule;
    },
]);

$expected = '.wp-block-card{color:currentColor}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected generic rule visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== [[
        'type' => 'style',
        'selector' => 'wp-block-card',
        'property' => 'color',
        'value' => 'currentColor',
    ]]) {
        fwrite(STDERR, "Unexpected generic rule visitor input: " . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
