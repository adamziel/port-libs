<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-theme-bundle {
  @tokens spacing {
    --gap: 16px;
    --accent: yellow;
  }

  .wp-block-card {
    color: token('spacing.--accent');
    gap: token('spacing.--gap');
  }
}
CSS;

$tokens = [];
$seen = [];
$transformer = new CustomAtRuleTransformer();
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'custom' => [
                'wp-theme-bundle' => static function (array $rule) use (&$seen): array {
                    $seen['bundleBodyType'] = $rule['bodyAst']['type'] ?? null;
                    $seen['bundleRuleTypes'] = array_map(
                        static fn (array $bodyRule): string => $bodyRule['type'] ?? '',
                        $rule['bodyAst']['value'] ?? []
                    );
                    $seen['nestedTokenPrelude'] = $rule['bodyAst']['value'][0]['value']['preludeAst'] ?? null;

                    return $rule['bodyAst']['value'];
                },
            ],
        ],
    ],
    [
        'Rule' => [
            'custom' => [
                'tokens' => static function (array $rule) use (&$tokens, &$seen): array {
                    $seen['tokensBodyType'] = $rule['bodyAst']['type'] ?? null;
                    foreach ($rule['declarations'] as $declaration) {
                        $tokens[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                    }

                    return [];
                },
            ],
        ],
        'Function' => [
            'token' => static function (array $arguments) use (&$tokens): ?string {
                return $tokens[$arguments[0] ?? ''] ?? null;
            },
        ],
    ],
]);

$result = $transformer->transform($css, [
    'wp-theme-bundle' => [
        'body' => 'rule-list',
    ],
    'tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], $visitor);

$expected = '.wp-block-card{color:#ff0;gap:16px}';
$expectedSeen = [
    'bundleBodyType' => 'rule-list',
    'bundleRuleTypes' => ['custom', 'style'],
    'nestedTokenPrelude' => ['type' => 'custom-ident', 'value' => 'spacing'],
    'tokensBodyType' => 'declaration-list',
];
$expectedTokens = [
    'spacing.--gap' => '16px',
    'spacing.--accent' => 'yellow',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected nested custom at-rule output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== $expectedSeen) {
        fwrite(STDERR, "Unexpected nested custom at-rule parser summary:\n" . json_encode($seen) . "\n");
        exit(1);
    }
    if ($tokens !== $expectedTokens) {
        fwrite(STDERR, "Unexpected nested custom at-rule token map:\n" . json_encode($tokens) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
