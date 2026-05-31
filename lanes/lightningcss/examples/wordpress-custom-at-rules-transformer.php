<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@asset "wp-block-card/view.js";
@asset-style "wp-block-card/style.css";
@block-color #056ef0;
@token --wp-ring #056ef0;
@env-token --wp-card-breakpoint 782px;
@var-token --wp-card-padding 24px;

@tokens wp {
  --gap: 24px;
  accent: yellow;
}

@mixin card {
  border-color: token('wp.accent');
  padding: token('wp.--gap');

  & .wp-block-button__link {
    color: token('wp.accent');
  }
}

@media (max-width: env(--wp-card-breakpoint)) {
  .wp-block-card {
    padding: var(--wp-card-padding);
  }
}

@responsive {
  .wp-block-card__stack {
    margin: 10px;
  }
}

.wp-block-card__media {
  size: 48px;
}

.wp-block-card {
  @apply card;
  gap: wp-rem(wp-size(card));
  outline-color: @wp-accent;
  box-shadow: 0 0 0 1px @--wp-ring;
  margin-left: 20px;
  margin-right: @margin-left;

  &:focus-visible {
    outline-color: @wp-accent;
  }

  @breakpoint 782px {
    display: grid;

    &.is-style-featured {
      color: token('wp.accent');
    }
  }
}
CSS;

$tokens = [];
$mixins = [];
$dependencies = [];
$colorAliases = [];
$environmentTokens = [];
$variableTokens = [];
$stylesheetExitRuleCount = 0;
$transformer = new CustomAtRuleTransformer();

$transform = $transformer->transformWithDependencies($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
        'body' => 'declaration-list',
    ],
    'mixin' => [
        'prelude' => '<custom-ident>',
        'body' => 'style-block',
    ],
    'apply' => [
        'prelude' => '<custom-ident>',
    ],
    'responsive' => [
        'prelude' => null,
        'body' => 'rule-list',
    ],
    'breakpoint' => [
        'prelude' => '<length>',
        'body' => 'style-block',
    ],
], CustomAtRuleTransformer::composeVisitors([
    static function (array $context): array {
        $addDependency = $context['addDependency'];

        return [
            'Rule' => [
                'unknown' => [
                    'asset' => static function (array $rule) use ($addDependency): array {
                        $addDependency(['type' => 'script', 'path' => $rule['preludeTokens'][0]['value']['value']]);

                        return [];
                    },
                    'block-color' => static function (array $rule): array {
                        $rule['name'] = 'wp-accent';

                        return [
                            'type' => 'unknown',
                            'value' => $rule,
                        ];
                    },
                ],
            ],
        ];
    },
    [
        'Rule' => [
            'custom' => [
                'tokens' => static function (array $rule) use (&$tokens): array {
                    foreach ($rule['declarations'] as $declaration) {
                        $tokens[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                    }

                    return [];
                },
                'mixin' => static function (array $rule) use (&$mixins): array {
                    $mixins[$rule['prelude']] = $rule['body'];

                    return [];
                },
                'apply' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$mixins): array {
                    return $transformer->styleBlock($mixins[$rule['prelude']] ?? '');
                },
            ],
        ],
    ],
    static function (array $context) use (&$tokens, &$colorAliases, &$environmentTokens, &$variableTokens): array {
        $addDependency = $context['addDependency'];

        return [
            'Rule' => [
                'unknown' => static function (array $rule) use ($addDependency, &$colorAliases, &$environmentTokens, &$variableTokens): ?array {
                    if ($rule['name'] === 'asset-style') {
                        $addDependency(['type' => 'style', 'path' => $rule['preludeTokens'][0]['value']['value']]);

                        return [];
                    }
                    if ($rule['name'] === 'token') {
                        $colorAliases[$rule['preludeTokens'][0]['value']] = $rule['preludeTokens'][1]['value'];

                        return [];
                    }
                    if ($rule['name'] === 'env-token') {
                        $environmentTokens[$rule['preludeTokens'][0]['value']] = [
                            'raw' => $rule['preludeTokens'][1]['value'],
                        ];

                        return [];
                    }
                    if ($rule['name'] === 'var-token') {
                        $variableTokens[$rule['preludeTokens'][0]['value']] = [
                            'raw' => $rule['preludeTokens'][1]['value'],
                        ];

                        return [];
                    }

                    if (!empty($rule['hasBlock'])) {
                        return null;
                    }

                    $colorAliases[$rule['name']] = $rule['prelude'];

                    return [];
                },
                'custom' => [
                    'responsive' => static function (array $rule, CustomAtRuleTransformer $transformer): array {
                        return [
                            $transformer->ruleList($rule['body']),
                            $transformer->media('(min-width: 782px)', '.md\\:wp-block-card__stack{margin:10px}'),
                        ];
                    },
                    'breakpoint' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                        '(width <= ' . $rule['prelude'] . ')',
                        $transformer->styleBlock($rule['body'])
                    ),
                ],
            ],
            'Token' => [
                'at-keyword' => static function (array $token) use (&$colorAliases): ?string {
                    return $colorAliases[$token['value']] ?? null;
                },
            ],
            'Function' => [
                'token' => static function (array $arguments) use (&$tokens): ?string {
                    return $tokens[$arguments[0] ?? ''] ?? null;
                },
            ],
            'EnvironmentVariable' => static function (array $environmentVariable) use (&$environmentTokens): ?array {
                $name = $environmentVariable['name']['ident'] ?? null;

                return is_string($name) ? ($environmentTokens[$name] ?? null) : null;
            },
            'Variable' => static function (array $variable) use (&$variableTokens): ?array {
                $name = $variable['name']['ident'] ?? null;

                return is_string($name) ? ($variableTokens[$name] ?? null) : null;
            },
            'FunctionExit' => [
                'wp-size' => static function (array $function): ?array {
                    $argument = $function['arguments'][0] ?? null;
                    if (!is_array($argument) || ($argument['value']['value'] ?? null) !== 'card') {
                        return null;
                    }

                    return [
                        'type' => 'length',
                        'unit' => 'px',
                        'value' => 32,
                    ];
                },
            ],
            'Declaration' => [
                'custom' => [
                    'size' => static function (array $declaration): ?array {
                        $token = $declaration['value'][0] ?? null;
                        if (!is_array($token) || ($token['type'] ?? null) !== 'length') {
                            return null;
                        }

                        return [
                            [
                                'property' => 'width',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => $token['value'],
                                    ],
                                ],
                            ],
                            [
                                'property' => 'height',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => $token['value'],
                                    ],
                                ],
                            ],
                        ];
                    },
                ],
            ],
        ];
    },
    [
        'FunctionExit' => static function (array $function): ?array {
            if ($function['name'] !== 'wp-rem') {
                return null;
            }

            $argument = $function['arguments'][0] ?? null;

            return is_array($argument) ? $argument : null;
        },
        'Length' => static function (array $length): ?array {
            if ($length['unit'] !== 'px') {
                return null;
            }

            return [
                'unit' => 'rem',
                'value' => $length['value'] / 16,
            ];
        },
    ],
    [
        'Rule' => [
            'style' => static function (array $rule): array {
                $valuesByProperty = [];
                foreach ($rule['declarations'] as $declaration) {
                    $valuesByProperty[$declaration['property']] = $declaration['value'];
                }

                foreach ($rule['declarations'] as $index => $declaration) {
                    if (str_starts_with($declaration['value'], '@')) {
                        $referenced = substr($declaration['value'], 1);
                        if (isset($valuesByProperty[$referenced])) {
                            $rule['declarations'][$index]['value'] = $valuesByProperty[$referenced];
                        }
                    }
                }

                $fallbackSelectors = [];
                foreach ($rule['selectors'] as $selector) {
                    if (str_contains($selector, ':focus-visible')) {
                        $fallbackSelectors[] = str_replace(':focus-visible', '.focus-visible', $selector);
                    }
                }
                if ($fallbackSelectors === []) {
                    return $rule;
                }

                return [
                    array_replace($rule, ['selectors' => $fallbackSelectors]),
                    $rule,
                ];
            },
        ],
    ],
    [
        'StyleSheetExit' => static function (array $stylesheet) use (&$stylesheetExitRuleCount): array {
            $stylesheetExitRuleCount = count($stylesheet['rules']);
            $stylesheet['rules'][] = [
                'type' => 'style',
                'value' => [
                    'selectors' => [
                        [
                            ['type' => 'class', 'name' => 'wp-block-card'],
                            ['type' => 'class', 'name' => 'is-visitor-ready'],
                        ],
                    ],
                    'declarations' => [
                        'declarations' => [
                            [
                                'property' => 'outline-color',
                                'value' => ['type' => 'raw', 'value' => '#056ef0'],
                            ],
                        ],
                    ],
                ],
            ];

            return $stylesheet;
        },
    ],
]));
$result = $transform['code'];
$dependencies = $transform['dependencies'];

$expected = '@media (width<=782px){.wp-block-card{padding:24px}}.wp-block-card__stack{margin:.625rem}@media (width>=782px){.md\\:wp-block-card__stack{margin:10px}}.wp-block-card__media{width:3rem;height:3rem}.wp-block-card{border-color:#ff0;padding:24px}.wp-block-card .wp-block-button__link{color:#ff0}.wp-block-card{gap:2rem;outline-color:#056ef0;box-shadow:0 0 0 .0625rem #056ef0;margin-left:1.25rem;margin-right:1.25rem}.wp-block-card.focus-visible{outline-color:#056ef0}.wp-block-card:focus-visible{outline-color:#056ef0}@media (width<=782px){.wp-block-card{display:grid}.wp-block-card.is-style-featured{color:#ff0}}.wp-block-card.is-visitor-ready{outline-color:#056ef0}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule transform output:\n{$result}\n");
        exit(1);
    }
    if ($dependencies !== [
        ['type' => 'script', 'path' => 'wp-block-card/view.js'],
        ['type' => 'style', 'path' => 'wp-block-card/style.css'],
    ]) {
        fwrite(STDERR, "Unexpected custom at-rule dependencies:\n" . json_encode($dependencies) . "\n");
        exit(1);
    }
    if ($colorAliases !== ['wp-accent' => '#056ef0', '--wp-ring' => '#056ef0']) {
        fwrite(STDERR, "Unexpected custom at-rule color aliases:\n" . json_encode($colorAliases) . "\n");
        exit(1);
    }
    if ($environmentTokens !== ['--wp-card-breakpoint' => ['raw' => '782px']]) {
        fwrite(STDERR, "Unexpected custom at-rule environment tokens:\n" . json_encode($environmentTokens) . "\n");
        exit(1);
    }
    if ($variableTokens !== ['--wp-card-padding' => ['raw' => '24px']]) {
        fwrite(STDERR, "Unexpected custom at-rule variable tokens:\n" . json_encode($variableTokens) . "\n");
        exit(1);
    }
    if ($stylesheetExitRuleCount < 1) {
        fwrite(STDERR, "Unexpected custom at-rule stylesheet exit count: {$stylesheetExitRuleCount}\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
