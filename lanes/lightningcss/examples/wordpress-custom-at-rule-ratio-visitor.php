<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@viewport-ratio 16 / 9 {
  .wp-block-cover {
    color: yellow;
  }
}

@image-ratios 1/1, 3/2;

.wp-block-cover {
  aspect-ratio: var(--wp-image-wide-ratio);
}
CSS;

$viewportPrelude = null;
$themeRatios = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'viewport-ratio' => ['prelude' => '<ratio>', 'body' => 'rule-list'],
    'image-ratios' => ['prelude' => '<ratio>#'],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Ratio' => static function (array $ratio): array {
            return match ($ratio) {
                [16.0, 9.0] => [4.0, 3.0],
                [1.0, 1.0] => [2.0, 1.0],
                default => $ratio,
            };
        },
    ],
    [
        'Rule' => [
            'custom' => [
                'viewport-ratio' => static function (array $rule) use (&$viewportPrelude): array {
                    $viewportPrelude = $rule['preludeAst'];

                    return [
                        'type' => 'media',
                        'value' => [
                            'query' => [
                                'mediaQueries' => [[
                                    'mediaType' => 'all',
                                    'condition' => [
                                        'type' => 'feature',
                                        'value' => [
                                            'type' => 'plain',
                                            'name' => 'aspect-ratio',
                                            'value' => $rule['preludeAst'],
                                        ],
                                    ],
                                ]],
                            ],
                            'rules' => $rule['bodyRules'],
                        ],
                    ];
                },
                'image-ratios' => static function (array $rule) use (&$themeRatios): array {
                    $themeRatios = $rule['preludeAst']['value']['components'] ?? [];

                    return [
                        'type' => 'style',
                        'value' => [
                            'selectors' => [
                                [
                                    ['type' => 'pseudo-class', 'kind' => 'root'],
                                ],
                            ],
                            'declarations' => [
                                'declarations' => [
                                    ['property' => '--wp-image-square-ratio', 'value' => $themeRatios[0] ?? ['type' => 'ratio', 'value' => [1.0, 1.0]]],
                                    ['property' => '--wp-image-wide-ratio', 'value' => $themeRatios[1] ?? ['type' => 'ratio', 'value' => [16.0, 9.0]]],
                                ],
                                'importantDeclarations' => [],
                            ],
                        ],
                    ];
                },
            ],
        ],
    ],
]));

$expected = '@media (aspect-ratio:4/3){.wp-block-cover{color:#ff0}}:root{--wp-image-square-ratio:2;--wp-image-wide-ratio:3/2}.wp-block-cover{aspect-ratio:var(--wp-image-wide-ratio)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule ratio output:\n{$result}\n");
        exit(1);
    }
    if (($viewportPrelude['value'] ?? null) !== [4.0, 3.0]) {
        fwrite(STDERR, "Unexpected viewport ratio AST:\n" . json_encode($viewportPrelude) . "\n");
        exit(1);
    }
    if (($themeRatios[0]['value'] ?? null) !== [2.0, 1.0] || ($themeRatios[1]['value'] ?? null) !== [3.0, 2.0]) {
        fwrite(STDERR, "Unexpected image ratio AST:\n" . json_encode($themeRatios) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
