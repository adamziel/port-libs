<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@variant block {
  .wp-block-card[data-state="draft" i] > a[href^="/shop"] {
    color: red;
  }
}

.wp-block-card[data-state="published" s] {
  color: yellow;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'variant' => [
        'prelude' => '<custom-ident>',
        'body' => 'rule-list',
    ],
], [
    'Selector' => static function (array $selector) use (&$seen): array {
        foreach ($selector as &$component) {
            if (($component['type'] ?? null) !== 'attribute' || ($component['name'] ?? null) !== 'data-state') {
                continue;
            }

            $seen[] = ($component['operation']['value'] ?? '') . ':' . ($component['operation']['caseSensitivity'] ?? '');
            $component['operation']['value'] = 'public';
            $component['operation']['caseSensitivity'] = 'ascii-case-insensitive';
        }
        unset($component);

        return $selector;
    },
    'Rule' => [
        'custom' => [
            'variant' => static function (array $rule) use (&$seen): array {
                $attribute = $rule['bodyRules'][0]['value']['selectors'][0][1] ?? [];
                if (($attribute['type'] ?? null) === 'attribute') {
                    $seen[] = ($attribute['operation']['value'] ?? '') . ':' . ($attribute['operation']['caseSensitivity'] ?? '');
                    $rule['bodyRules'][0]['value']['selectors'][0][1]['operation']['value'] = 'live';
                }

                return $rule['bodyRules'];
            },
        ],
    ],
]);

if (($argv[1] ?? null) === '--self-test') {
    $expected = '.wp-block-card[data-state=live i]>a[href^=\/shop]{color:red}.wp-block-card[data-state=public i]{color:#ff0}';
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule attribute selector parser output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== ['draft:ascii-case-insensitive', 'published:explicit-case-sensitive']) {
        fwrite(STDERR, "Unexpected custom at-rule attribute selector visitor events:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
