<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@block-tokens card {
  accent: yellow;
}

@asset "blocks/card/tokens.json";

.wp-block-card {
  width: 16px;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [
    'block-tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'RuleExit' => [
            'custom' => [
                'block-tokens' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                    $seen['tokenPrelude'] = $rule['preludeAst'];

                    return $transformer->styleRule('.wp-block-' . $rule['prelude'] . '.has-exit-tokens', [
                        'outline-color' => $rule['declarations'][0]['value'] ?? 'transparent',
                    ]);
                },
            ],
            'unknown' => [
                'asset' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                    $seen['asset'] = $rule['preludeTokens'][0]['value']['value'] ?? '';

                    return $transformer->styleRule('.wp-block-card.has-exit-asset', [
                        'outline-color' => '#056ef0',
                    ]);
                },
            ],
        ],
    ],
    [
        'RuleExit' => [
            'style' => static function (array $rule) use (&$seen): array {
                $seen['styleSelectors'][] = $rule['selector'] ?? '';
                $rule['declarations'][] = [
                    'property' => 'height',
                    'value' => '16px',
                    'important' => false,
                ];

                return $rule;
            },
        ],
    ],
]));

$expected = '.wp-block-card.has-exit-tokens{outline-color:#ff0}.wp-block-card.has-exit-asset{outline-color:#056ef0}.wp-block-card{width:16px;height:16px}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule RuleExit output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== [
        'tokenPrelude' => ['type' => 'custom-ident', 'value' => 'card'],
        'asset' => 'blocks/card/tokens.json',
        'styleSelectors' => [
            '.wp-block-card',
        ],
    ]) {
        fwrite(STDERR, "Unexpected custom at-rule RuleExit visitor state:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
