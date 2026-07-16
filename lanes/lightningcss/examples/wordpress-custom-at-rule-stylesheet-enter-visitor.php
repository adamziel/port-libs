<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@block-tokens card {
  accent: yellow;
}

.wp-block-card {
  width: 32px;
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
        'StyleSheet' => static function (array $stylesheet) use (&$seen): array {
            $seen['ruleTypes'] = array_map(
                static fn (array $rule): string => (string) ($rule['type'] ?? ''),
                $stylesheet['rules']
            );
            $customRule = $stylesheet['rules'][0]['value'] ?? [];
            $bodyAst = is_array($customRule) ? ($customRule['bodyAst'] ?? []) : [];
            $declaration = is_array($bodyAst)
                ? ($bodyAst['value']['declarations'][0] ?? [])
                : [];
            $accentTokens = is_array($declaration['value']['value'] ?? null)
                ? $declaration['value']['value']
                : [];
            $seen['tokenPrelude'] = is_array($customRule) ? ($customRule['preludeAst'] ?? null) : null;
            $seen['tokenBodyType'] = is_array($bodyAst) ? ($bodyAst['type'] ?? null) : null;
            $seen['tokenDeclaration'] = $declaration['value']['name'] ?? null;

            return [
                'rules' => [
                    [
                        'type' => 'style',
                        'value' => [
                            'selectors' => [
                                [
                                    ['type' => 'class', 'name' => 'wp-block-card'],
                                    ['type' => 'class', 'name' => 'has-sheet-tokens'],
                                ],
                            ],
                            'declarations' => [
                                'declarations' => [
                                    [
                                        'property' => 'unparsed',
                                        'value' => [
                                            'propertyId' => ['property' => 'outline-color'],
                                            'value' => $accentTokens,
                                        ],
                                    ],
                                    [
                                        'property' => 'width',
                                        'value' => [
                                            'type' => 'length-percentage',
                                            'value' => [
                                                'type' => 'dimension',
                                                'value' => ['unit' => 'px', 'value' => 16],
                                            ],
                                        ],
                                    ],
                                ],
                                'importantDeclarations' => [],
                            ],
                        ],
                    ],
                    $stylesheet['rules'][1],
                ],
            ];
        },
    ],
    [
        'Length' => static function (array $length) use (&$seen): ?array {
            $seen['lengths'][] = $length['value'];

            return $length['unit'] === 'px'
                ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                : null;
        },
    ],
]));

$expected = '.wp-block-card.has-sheet-tokens{outline-color:#ff0;width:1rem}.wp-block-card{width:2rem}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule StyleSheet output:\n{$result}\n");
        exit(1);
    }
    if ($seen !== [
        'ruleTypes' => ['custom', 'style'],
        'tokenPrelude' => ['type' => 'custom-ident', 'value' => 'card'],
        'tokenBodyType' => 'declaration-list',
        'tokenDeclaration' => 'accent',
        'lengths' => [16.0, 32.0],
    ]) {
        fwrite(STDERR, "Unexpected custom at-rule StyleSheet visitor state:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
