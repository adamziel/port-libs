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
@unit --wp-fluid-step;

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

@media (hover) {
  .wp-block-card__cta {
    color: yellow;
  }
}

.wp-block-card__viewport {
  color: red;
  height: 100vh;
}

.wp-block-card__media {
  size: 48px;
}

.wp-block-card {
  @apply card;
  --wp-fluid-step: .25rem;
  font-size: 3--wp-fluid-step;
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
$customUnits = [];
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
    static function (array $context) use (&$tokens, &$colorAliases, &$environmentTokens, &$variableTokens, &$customUnits): array {
        $addDependency = $context['addDependency'];

        return [
            'Rule' => [
                'unknown' => static function (array $rule) use ($addDependency, &$colorAliases, &$environmentTokens, &$variableTokens, &$customUnits): ?array {
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
                    if ($rule['name'] === 'unit') {
                        $unit = $rule['preludeTokens'][0]['value'] ?? null;
                        if (is_string($unit)) {
                            $customUnits[$unit] = true;
                        }

                        return [];
                    }

                    if (!empty($rule['hasBlock'])) {
                        return null;
                    }

                    $colorAliases[$rule['name']] = $rule['prelude'];

                    return [];
                },
                'custom' => [
                    'responsive' => static function (array $rule): array {
                        $mediaRules = [];
                        foreach ($rule['bodyRules'] as $bodyRule) {
                            if (($bodyRule['type'] ?? null) !== 'style') {
                                continue;
                            }
                            $clone = $bodyRule;
                            foreach ($clone['value']['selectors'] as &$selector) {
                                foreach ($selector as &$component) {
                                    if (($component['type'] ?? null) === 'class') {
                                        $component['name'] = 'md:' . $component['name'];
                                    }
                                }
                                unset($component);
                            }
                            unset($selector);
                            $mediaRules[] = $clone;
                        }

                        return [
                            ...$rule['bodyRules'],
                            [
                                'type' => 'media',
                                'value' => [
                                    'query' => [
                                        'mediaQueries' => [
                                            ['raw' => '(min-width: 782px)'],
                                        ],
                                    ],
                                    'rules' => $mediaRules,
                                ],
                            ],
                        ];
                    },
                    'breakpoint' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                        '(width <= ' . $rule['prelude'] . ')',
                        $transformer->styleBlock($rule['body'])
                    ),
                ],
                'media' => static function (array $media): ?array {
                    $mediaQueries = $media['value']['query']['mediaQueries'] ?? [];
                    $condition = $mediaQueries[0]['condition'] ?? null;
                    if (
                        count($mediaQueries) !== 1
                        || !is_array($condition)
                        || ($condition['type'] ?? null) !== 'feature'
                        || ($condition['value']['type'] ?? null) !== 'boolean'
                        || ($condition['value']['name'] ?? null) !== 'hover'
                    ) {
                        return null;
                    }

                    foreach ($media['value']['rules'] as &$rule) {
                        if (($rule['type'] ?? null) !== 'style') {
                            continue;
                        }
                        foreach ($rule['value']['selectors'] as &$selector) {
                            array_unshift(
                                $selector,
                                ['type' => 'class', 'name' => 'wp-hoverable'],
                                ['type' => 'combinator', 'value' => 'descendant']
                            );
                        }
                        unset($selector);
                    }
                    unset($rule);

                    return $media['value']['rules'];
                },
            ],
            'Token' => [
                'at-keyword' => static function (array $token) use (&$colorAliases): ?string {
                    return $colorAliases[$token['value']] ?? null;
                },
                'dimension' => static function (array $token) use (&$customUnits): ?array {
                    if (!isset($customUnits[$token['unit'] ?? ''])) {
                        return null;
                    }

                    return [
                        'type' => 'function',
                        'value' => [
                            'name' => 'calc',
                            'arguments' => [
                                [
                                    'type' => 'token',
                                    'value' => [
                                        'type' => 'number',
                                        'value' => $token['value'],
                                    ],
                                ],
                                [
                                    'type' => 'token',
                                    'value' => [
                                        'type' => 'delim',
                                        'value' => '*',
                                    ],
                                ],
                                [
                                    'type' => 'var',
                                    'value' => [
                                        'name' => [
                                            'ident' => $token['unit'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
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

                $supportsRule = null;
                foreach ($rule['declarations'] as $declaration) {
                    if (($declaration['property'] ?? null) !== 'height' || ($declaration['value'] ?? null) !== '100vh') {
                        continue;
                    }

                    $supportsRule = [
                        'type' => 'supports',
                        'value' => [
                            'condition' => [
                                'type' => 'declaration',
                                'propertyId' => ['property' => '-webkit-touch-callout'],
                                'value' => 'none',
                            ],
                            'rules' => [[
                                'type' => 'style',
                                'value' => [
                                    'selectors' => $rule['value']['selectors'],
                                    'declarations' => [
                                        'declarations' => [[
                                            'property' => 'height',
                                            'value' => [
                                                'type' => 'stretch',
                                                'vendorPrefix' => ['webkit'],
                                            ],
                                        ]],
                                        'importantDeclarations' => [],
                                    ],
                                ],
                            ]],
                        ],
                    ];
                    break;
                }

                $fallbackSelectors = [];
                foreach ($rule['selectors'] as $selector) {
                    if (str_contains($selector, ':focus-visible')) {
                        $fallbackSelectors[] = str_replace(':focus-visible', '.focus-visible', $selector);
                    }
                }
                $rules = [];
                if ($fallbackSelectors !== []) {
                    $rules[] = array_replace($rule, ['selectors' => $fallbackSelectors]);
                }
                $rules[] = $rule;
                if ($supportsRule !== null) {
                    $rules[] = $supportsRule;
                }

                return count($rules) === 1 ? $rule : $rules;
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

$expected = '@media (width<=782px){.wp-block-card{padding:24px}}.wp-block-card__stack{margin:10px}@media (width>=782px){.md\\:wp-block-card__stack{margin:10px}}.wp-hoverable .wp-block-card__cta{color:#ff0}.wp-block-card__viewport{color:red;height:100vh}@supports (-webkit-touch-callout:none){.wp-block-card__viewport{height:-webkit-fill-available}}.wp-block-card__media{width:3rem;height:3rem}.wp-block-card{border-color:#ff0;padding:24px}.wp-block-card .wp-block-button__link{color:#ff0}.wp-block-card{--wp-fluid-step:.25rem;font-size:calc(3*var(--wp-fluid-step));gap:2rem;outline-color:#056ef0;box-shadow:0 0 0 .0625rem #056ef0;margin-left:1.25rem;margin-right:1.25rem}.wp-block-card.focus-visible{outline-color:#056ef0}.wp-block-card:focus-visible{outline-color:#056ef0}@media (width<=782px){.wp-block-card{display:grid}.wp-block-card.is-style-featured{color:#ff0}}.wp-block-card.is-visitor-ready{outline-color:#056ef0}';

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
    if ($customUnits !== ['--wp-fluid-step' => true]) {
        fwrite(STDERR, "Unexpected custom at-rule custom units:\n" . json_encode($customUnits) . "\n");
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
