<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

$customDefinitions = [
    'theme' => [
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
    'breakpoint' => [
        'prelude' => '<length>',
        'body' => 'rule-list',
    ],
    'test' => [
        'body' => 'style-block',
    ],
];

$mixinVisitor = static function (array &$mixins): array {
    return [
        'Rule' => [
            'custom' => [
                'mixin' => static function (array $rule) use (&$mixins): array {
                    $mixins[$rule['prelude']] = $rule['body'];

                    return [];
                },
                'apply' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$mixins): array {
                    return $transformer->styleBlock($mixins[$rule['prelude']] ?? '');
                },
            ],
        ],
    ];
};

return [
    'custom at-rules map upstream declaration-list parser and function visitor' => static function (TestRunner $t) use ($customDefinitions): void {
        $definitions = [];
        $css = <<<'CSS'
@theme spacing {
  foo: 16px;
  bar: 32px;
}

.foo {
  width: theme('spacing.foo');
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, $customDefinitions, [
            'Rule' => [
                'custom' => [
                    'theme' => static function (array $rule) use (&$definitions): array {
                        foreach ($rule['declarations'] as $declaration) {
                            $definitions[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                        }

                        return [];
                    },
                ],
            ],
            'Function' => [
                'theme' => static function (array $arguments) use (&$definitions): ?string {
                    return $definitions[$arguments[0] ?? ''] ?? null;
                },
            ],
        ]);

        $t->same('.foo{width:16px}', $result);
        $t->same('32px', $definitions['spacing.bar']);
    },
    'custom at-rules map upstream mixin style-block and apply statement visitor' => static function (TestRunner $t) use ($customDefinitions, $mixinVisitor): void {
        $mixins = [];
        $css = <<<'CSS'
@mixin color {
  color: red;

  &.bar {
    color: yellow;
  }
}

.foo {
  @apply color;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, $customDefinitions, $mixinVisitor($mixins));

        $t->same('.foo{color:red}.foo.bar{color:#ff0}', $result);
        $t->same(['color'], array_keys($mixins));
    },
    'custom at-rules map upstream rule-list visitor replacement' => static function (TestRunner $t) use ($customDefinitions): void {
        $css = <<<'CSS'
@breakpoint 1024px {
  .foo { color: yellow; }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, $customDefinitions, [
            'Rule' => [
                'custom' => [
                    'breakpoint' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                        '(width <= ' . $rule['prelude'] . ')',
                        $transformer->ruleList($rule['body'])
                    ),
                ],
            ],
        ]);

        $t->same('@media (width<=1024px){.foo{color:#ff0}}', $result);
    },
    'custom at-rules map upstream nested style-block visitor replacement' => static function (TestRunner $t) use ($customDefinitions): void {
        $css = <<<'CSS'
.foo {
  @breakpoint 1024px {
    color: yellow;

    &.bar {
      color: red;
    }
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'breakpoint' => [
                'prelude' => '<length>',
                'body' => 'style-block',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'breakpoint' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                        '(width <= ' . $rule['prelude'] . ')',
                        $transformer->styleBlock($rule['body'])
                    ),
                ],
            ],
        ]);

        $t->same('@media (width<=1024px){.foo{color:#ff0}.foo.bar{color:red}}', $result);
    },
    'custom at-rules preserve upstream top-level style-block without visitor' => static function (TestRunner $t) use ($customDefinitions): void {
        $css = <<<'CSS'
@test {
  .foo {
    background: black;
  }
}
CSS;

        $t->same(
            '@test{.foo{background:#000}}',
            (new CustomAtRuleTransformer())->transform($css, $customDefinitions)
        );
    },
    'custom at-rules map upstream generic custom visitor across nested rules' => static function (TestRunner $t) use ($customDefinitions): void {
        $css = <<<'CSS'
@breakpoint 1024px {
  @theme spacing {
    foo: 16px;
    bar: 32px;
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, $customDefinitions, [
            'Rule' => [
                'custom' => static function (array $rule, CustomAtRuleTransformer $transformer): array {
                    if ($rule['name'] === 'breakpoint') {
                        return $transformer->media(
                            '(width <= ' . $rule['prelude'] . ')',
                            $transformer->ruleList($rule['body'])
                        );
                    }

                    return $transformer->styleRule(':root', $rule['declarations']);
                },
            ],
        ]);

        $t->same('@media (width<=1024px){:root{foo:16px;bar:32px}}', $result);
    },
    'custom at-rules map upstream bundler mixin visitor after import resolution' => static function (TestRunner $t) use ($customDefinitions, $mixinVisitor): void {
        $mixins = [];
        $result = (new CustomAtRuleTransformer())->bundle('/apply.css', [
            '/apply.css' => <<<'CSS'
@import "./mixin.css";

.foo {
  @apply color;
}
CSS,
            '/mixin.css' => <<<'CSS'
@mixin color {
  color: red;

  &.bar {
    color: yellow;
  }
}
CSS,
        ], $customDefinitions, $mixinVisitor($mixins));

        $t->same('.foo{color:red}.foo.bar{color:#ff0}', $result);
        $t->contains('&.bar', $mixins['color']);
    },
    'custom at-rules preserve upstream custom parser inline and block output' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@block test {
  color: yellow;
}

@inline test;

.foo {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'block' => [
                'prelude' => '<custom-ident>',
                'body' => 'declaration-list',
            ],
            'inline' => [
                'prelude' => '<custom-ident>',
            ],
        ]);

        $t->same('@block test{color:#ff0}@inline test;.foo{color:red}', $result);
    },
    'custom at-rules reject upstream no-prelude and no-body parser shape violations' => static function (TestRunner $t): void {
        $transformer = new CustomAtRuleTransformer();

        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@tokens stale;', [
            'tokens' => [],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@tokens { .foo { color: red; } }', [
            'tokens' => [],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@breakpoints;', [
            'breakpoints' => [
                'body' => 'rule-list',
            ],
        ]));
    },
    'custom at-rules map upstream visitor rule-array replacement' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@breakpoints {
  .m-1 {
    margin: 10px;
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'breakpoints' => [
                'prelude' => null,
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'breakpoints' => static function (array $rule, CustomAtRuleTransformer $transformer): array {
                        return [
                            $transformer->ruleList($rule['body']),
                            $transformer->media('(min-width: 500px)', '.sm\\:m-1{margin:10px}'),
                        ];
                    },
                ],
            ],
        ]);

        $t->same('.m-1{margin:10px}@media (width>=500px){.sm\\:m-1{margin:10px}}', $result);
    },
    'custom at-rules emit upstream returned media raw rule objects' => static function (TestRunner $t): void {
        $css = <<<'CSS'
@breakpoints {
  .m-1 {
    margin: 10px;
  }
}
CSS;

        $seenBodyRules = [];
        $result = (new CustomAtRuleTransformer())->transform($css, [
            'breakpoints' => [
                'prelude' => null,
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'breakpoints' => static function (array $rule) use (&$seenBodyRules): array {
                        $seenBodyRules = $rule['bodyRules'];
                        $mediaRules = [];
                        foreach ($rule['bodyRules'] as $bodyRule) {
                            if (($bodyRule['type'] ?? null) !== 'style') {
                                continue;
                            }
                            $clone = $bodyRule;
                            foreach ($clone['value']['selectors'] as &$selector) {
                                foreach ($selector as &$component) {
                                    if (($component['type'] ?? null) === 'class') {
                                        $component['name'] = 'sm:' . $component['name'];
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
                                            ['raw' => '(min-width: 500px)'],
                                        ],
                                    ],
                                    'rules' => $mediaRules,
                                ],
                            ],
                        ];
                    },
                ],
            ],
        ]);

        $t->same('.m-1{margin:10px}@media (width>=500px){.sm\\:m-1{margin:10px}}', $result);
        $t->same('style', $seenBodyRules[0]['type']);
        $t->same('m-1', $seenBodyRules[0]['value']['selectors'][0][0]['name']);
    },
    'custom at-rules emit upstream returned style and ignored rule objects' => static function (TestRunner $t): void {
        $result = (new CustomAtRuleTransformer())->transform('@skip unused; @tailwind base; .keep { color: red; }', [
            'skip' => [
                'prelude' => '<custom-ident>',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'skip' => static fn (): array => ['type' => 'ignored'],
                ],
                'unknown' => [
                    'tailwind' => static fn (): array => [
                        'type' => 'style',
                        'value' => [
                            'selectors' => [
                                [
                                    ['type' => 'universal'],
                                ],
                            ],
                            'declarations' => [
                                'declarations' => [
                                    ['property' => 'visibility', 'raw' => 'hi\\64 den'],
                                    ['property' => 'transition', 'vendorPrefix' => ['moz'], 'raw' => '200ms test'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $t->same('*{visibility:hidden;-moz-transition:test .2s}.keep{color:red}', $result);
        $t->same(0, substr_count($result, '@skip'));
    },
    'custom at-rules map upstream composed custom rule visitors' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'custom' => [
                        'testA' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->styleRule('.testA', [
                            'color' => 'red',
                        ]),
                    ],
                ],
            ],
            [
                'Rule' => [
                    'custom' => [
                        'testB' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->styleRule('.testB', [
                            'color' => 'lime',
                        ]),
                    ],
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('@testA; @testB;', [
            'testA' => [],
            'testB' => [],
        ], $visitor);

        $t->same('.testA{color:red}.testB{color:#0f0}', $result);
    },
    'custom at-rules map upstream composed unknown at-rule visitors' => static function (TestRunner $t): void {
        $dependencies = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'unknown' => [
                        'dep' => static function (array $rule) use (&$dependencies): array {
                            $dependencies[] = $rule['preludeTokens'][0]['value']['value'];

                            return [];
                        },
                    ],
                ],
            ],
            [
                'Rule' => [
                    'unknown' => [
                        'dep2' => static function (array $rule) use (&$dependencies): array {
                            $dependencies[] = [
                                'name' => $rule['name'],
                                'prelude' => $rule['prelude'],
                                'string' => $rule['preludeTokens'][0]['value']['value'],
                            ];

                            return [];
                        },
                    ],
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('@dep "foo.js"; @dep2 "bar.js"; .foo { width: 32px; }', [], $visitor);

        $t->same('.foo{width:32px}', $result);
        $t->same('foo.js', $dependencies[0]);
        $t->same(['name' => 'dep2', 'prelude' => '"bar.js"', 'string' => 'bar.js'], $dependencies[1]);
    },
    'custom at-rules compose upstream visitor factories with dependencies' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            static function (array $context): array {
                $addDependency = $context['addDependency'];

                return [
                    'Rule' => [
                        'unknown' => [
                            'dep' => static function (array $rule) use ($addDependency): array {
                                $addDependency([
                                    'type' => 'file',
                                    'filePath' => $rule['preludeTokens'][0]['value']['value'],
                                ]);

                                return [];
                            },
                        ],
                    ],
                ];
            },
            static function (array $context): array {
                $addDependency = $context['addDependency'];

                return [
                    'Rule' => [
                        'unknown' => [
                            'dep2' => static function (array $rule) use ($addDependency): array {
                                $addDependency([
                                    'type' => 'file',
                                    'filePath' => $rule['preludeTokens'][0]['value']['value'],
                                ]);

                                return [];
                            },
                        ],
                    ],
                ];
            },
        ]);

        $result = (new CustomAtRuleTransformer())->transformWithDependencies(
            '@dep "foo.js"; @dep2 "bar.js"; .foo { width: 32px; }',
            [],
            $visitor
        );

        $t->same('.foo{width:32px}', $result['code']);
        $t->same([
            ['type' => 'file', 'filePath' => 'foo.js'],
            ['type' => 'file', 'filePath' => 'bar.js'],
        ], $result['dependencies']);
    },
    'custom at-rules collect visitor factory dependencies after bundling' => static function (TestRunner $t): void {
        $visitor = static function (array $context): array {
            $addDependency = $context['addDependency'];

            return [
                'Rule' => [
                    'unknown' => [
                        'dep' => static function (array $rule) use ($addDependency): array {
                            $addDependency([
                                'type' => 'file',
                                'filePath' => $rule['preludeTokens'][0]['value']['value'],
                            ]);

                            return [];
                        },
                    ],
                ],
            ];
        };

        $result = (new CustomAtRuleTransformer())->bundleWithDependencies('/entry.css', [
            '/entry.css' => '@import "./deps.css"; .entry { width: 16px; }',
            '/deps.css' => '@dep "tokens.json";',
        ], [], $visitor);

        $t->same('.entry{width:16px}', $result['code']);
        $t->same([
            ['type' => 'file', 'filePath' => 'tokens.json'],
        ], $result['dependencies']);
    },
    'custom at-rules map upstream composed unknown rules and token visitors' => static function (TestRunner $t): void {
        $declared = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'unknown' => [
                        'test' => static function (array $rule): array {
                            $rule['name'] = 'blue';

                            return [
                                'type' => 'unknown',
                                'value' => $rule,
                            ];
                        },
                    ],
                ],
            ],
            [
                'Rule' => [
                    'unknown' => static function (array $rule) use (&$declared): array {
                        $declared[$rule['name']] = $rule['prelude'];

                        return [];
                    },
                ],
                'Token' => [
                    'at-keyword' => static function (array $token) use (&$declared): ?string {
                        return $declared[$token['value']] ?? null;
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
@test #056ef0;

.menu_link {
  background: @blue;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.menu_link{background:#056ef0}', $result);
        $t->same(['blue' => '#056ef0'], $declared);
    },
    'custom at-rules compose upstream function visitors with declaration-list parser' => static function (TestRunner $t): void {
        $definitions = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'custom' => [
                        'tokens' => static function (array $rule) use (&$definitions): array {
                            foreach ($rule['declarations'] as $declaration) {
                                $definitions[$rule['prelude'] . '.' . $declaration['property']] = $declaration['value'];
                            }

                            return [];
                        },
                    ],
                ],
            ],
            [
                'Function' => static function (array $arguments, string $raw, string $name) use (&$definitions): ?string {
                    if ($name !== 'theme') {
                        return null;
                    }

                    return $definitions[$arguments[0] ?? ''] ?? null;
                },
            ],
            [
                'Function' => [
                    'spacing' => static fn (array $arguments): ?string => ($arguments[0] ?? null) === 'card' ? '16px' : null,
                ],
            ],
        ]);

        $css = <<<'CSS'
@tokens wp {
  accent: yellow;
}

.wp-block-card {
  color: theme('wp.accent');
  padding: spacing('card');
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<custom-ident>',
                'body' => 'declaration-list',
            ],
        ], $visitor);

        $t->same('.wp-block-card{color:#ff0;padding:16px}', $result);
        $t->same(['wp.accent' => 'yellow'], $definitions);
    },
    'custom at-rules expose upstream dashed-ident preludes to token visitors' => static function (TestRunner $t): void {
        $aliases = [];
        $seenPreludeTokens = [];
        $seenValueTokens = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'unknown' => [
                        'token' => static function (array $rule) use (&$aliases, &$seenPreludeTokens): array {
                            $seenPreludeTokens = $rule['preludeTokens'];
                            $aliases[$rule['preludeTokens'][0]['value']] = $rule['preludeTokens'][1]['value'];

                            return [];
                        },
                    ],
                ],
            ],
            [
                'Token' => [
                    'at-keyword' => static function (array $token) use (&$aliases, &$seenValueTokens): ?string {
                        $seenValueTokens[] = $token;

                        return $aliases[$token['value']] ?? null;
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
@token --wp-accent #056ef0;

.wp-block-card {
  outline-color: @--wp-accent;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.wp-block-card{outline-color:#056ef0}', $result);
        $t->same(['type' => 'dashed-ident', 'value' => '--wp-accent'], $seenPreludeTokens[0]);
        $t->same(['type' => 'raw', 'value' => '#056ef0'], $seenPreludeTokens[1]);
        $t->same(['type' => 'at-keyword', 'value' => '--wp-accent', 'raw' => '@--wp-accent'], $seenValueTokens[0]);
    },
    'custom at-rules compose upstream Token dimension custom-unit visitors' => static function (TestRunner $t): void {
        $customUnits = [];
        $seenPreludeToken = null;
        $seenDimensions = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'unknown' => [
                        'unit' => static function (array $rule) use (&$customUnits, &$seenPreludeToken): array {
                            $seenPreludeToken = $rule['preludeTokens'][0] ?? null;
                            if (($seenPreludeToken['type'] ?? null) === 'dashed-ident') {
                                $customUnits[$seenPreludeToken['value']] = true;
                            }

                            return [];
                        },
                    ],
                ],
            ],
            [
                'Token' => [
                    'dimension' => static function (array $token) use (&$customUnits, &$seenDimensions): ?array {
                        $seenDimensions[] = $token;
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
            ],
        ]);

        $css = <<<'CSS'
@unit --step;

.wp-block-card {
  --step: .25rem;
  font-size: 3--step;
  margin: 2--step 1rem;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.wp-block-card{--step:.25rem;font-size:calc(3*var(--step));margin:calc(2*var(--step)) 1rem}', $result);
        $t->same(['type' => 'dashed-ident', 'value' => '--step'], $seenPreludeToken);
        $t->same(['3--step', '2--step'], array_column($seenDimensions, 'raw'));
        $t->same([3.0, 2.0], array_column($seenDimensions, 'value'));
    },
    'custom at-rules visit unknown at-rule blocks inside style rules' => static function (TestRunner $t): void {
        $css = <<<'CSS'
.wp-block-card {
  @when editor {
    color: yellow;

    & .wp-block-card__title {
      color: red;
    }
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], [
            'Rule' => [
                'unknown' => [
                    'when' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->media(
                        '(prefers-color-scheme: ' . $rule['prelude'] . ')',
                        $transformer->styleBlock($rule['body'])
                    ),
                ],
            ],
        ]);

        $t->same('@media (prefers-color-scheme:editor){.wp-block-card{color:#ff0}.wp-block-card .wp-block-card__title{color:red}}', $result);
    },
    'custom at-rules visit upstream native media boolean rule visitors' => static function (TestRunner $t): void {
        $seenQuery = null;
        $result = (new CustomAtRuleTransformer())->transform('@media (hover) { .foo { color: red; } }', [], [
            'Rule' => [
                'media' => static function (array $media) use (&$seenQuery): ?array {
                    $mediaQueries = $media['value']['query']['mediaQueries'];
                    $seenQuery = $mediaQueries[0];
                    $condition = $mediaQueries[0]['condition'] ?? null;
                    if (
                        !is_array($condition)
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
                                ['type' => 'class', 'name' => 'hoverable'],
                                ['type' => 'combinator', 'value' => 'descendant']
                            );
                        }
                        unset($selector);
                    }
                    unset($rule);

                    return $media['value']['rules'];
                },
            ],
        ]);

        $t->same('.hoverable .foo{color:red}', $result);
        $t->same('all', $seenQuery['mediaType']);
        $t->same('hover', $seenQuery['condition']['value']['name'] ?? null);
    },
    'custom at-rules clone upstream native media plain-feature visitor rules' => static function (TestRunner $t): void {
        $seenFeature = null;
        $css = <<<'CSS'
@media (prefers-color-scheme: dark) {
  body {
    background: black;
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], [
            'Rule' => [
                'media' => static function (array $media) use (&$seenFeature): ?array {
                    $query = $media['value']['query']['mediaQueries'][0] ?? null;
                    $condition = is_array($query) ? ($query['condition'] ?? null) : null;
                    $feature = is_array($condition) ? ($condition['value'] ?? null) : null;
                    $seenFeature = $feature;
                    if (
                        !is_array($feature)
                        || ($feature['type'] ?? null) !== 'plain'
                        || ($feature['name'] ?? null) !== 'prefers-color-scheme'
                        || ($feature['value']['value'] ?? null) !== 'dark'
                    ) {
                        return null;
                    }

                    $mediaRule = $media;
                    $clonedRules = [];
                    foreach ($mediaRule['value']['rules'] as &$rule) {
                        if (($rule['type'] ?? null) !== 'style') {
                            continue;
                        }

                        $clonedSelectors = [];
                        foreach ($rule['value']['selectors'] as &$selector) {
                            $clonedSelectors[] = [
                                ['type' => 'type', 'name' => 'html'],
                                ['type' => 'attribute', 'name' => 'theme', 'operation' => ['operator' => 'equal', 'value' => 'dark']],
                                ['type' => 'combinator', 'value' => 'descendant'],
                                ...$selector,
                            ];
                            array_unshift(
                                $selector,
                                ['type' => 'type', 'name' => 'html'],
                                [
                                    'type' => 'pseudo-class',
                                    'kind' => 'not',
                                    'selectors' => [[
                                        ['type' => 'attribute', 'name' => 'theme', 'operation' => ['operator' => 'equal', 'value' => 'light']],
                                    ]],
                                ],
                                ['type' => 'combinator', 'value' => 'descendant']
                            );
                        }
                        unset($selector);

                        $clone = $rule;
                        $clone['value']['selectors'] = $clonedSelectors;
                        $clonedRules[] = $clone;
                    }
                    unset($rule);

                    return [$mediaRule, ...$clonedRules];
                },
            ],
        ]);

        $t->same('@media (prefers-color-scheme:dark){html:not([theme=light]) body{background:#000}}html[theme="dark"] body{background:#000}', $result);
        $t->same('prefers-color-scheme', $seenFeature['name'] ?? null);
        $t->same('dark', $seenFeature['value']['value'] ?? null);
    },
    'custom at-rules compose upstream known style rule visitors' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
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

                        return $rule;
                    },
                ],
            ],
            [
                'Rule' => [
                    'style' => static function (array $rule): ?array {
                        $fallbackSelectors = [];
                        foreach ($rule['selectors'] as $selector) {
                            if (!str_contains($selector, ':focus-visible')) {
                                continue;
                            }
                            $fallbackSelectors[] = str_replace(':focus-visible', '.focus-visible', $selector);
                        }
                        if ($fallbackSelectors === []) {
                            return null;
                        }

                        return [
                            array_replace($rule, ['selectors' => $fallbackSelectors]),
                            $rule,
                        ];
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
.test:focus-visible {
  margin-left: 20px;
  margin-right: @margin-left;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.test.focus-visible{margin-left:20px;margin-right:20px}.test:focus-visible{margin-left:20px;margin-right:20px}', $result);
        $t->same('.focus-visible', substr($result, 5, 14));
    },
    'custom at-rules compose upstream FunctionExit and Length value visitors' => static function (TestRunner $t): void {
        $seenFunctions = [];
        $seenLengthUnits = [];
        $genericArgumentUnits = [];

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'FunctionExit' => [
                    'f1' => static function (array $function) use (&$seenFunctions): array {
                        $seenFunctions[] = $function['name'];

                        return [
                            'type' => 'length',
                            'unit' => 'px',
                            'value' => 32,
                        ];
                    },
                ],
            ],
            [
                'FunctionExit' => static function (array $function) use (&$seenFunctions, &$genericArgumentUnits): ?array {
                    $seenFunctions[] = $function['name'];
                    $argument = $function['arguments'][0] ?? null;
                    if (is_array($argument) && ($argument['type'] ?? null) === 'length') {
                        $genericArgumentUnits[$function['name']] = $argument['unit'] ?? ($argument['value']['unit'] ?? null);
                    }

                    return is_array($argument) ? $argument : null;
                },
            ],
            [
                'Length' => static function (array $length) use (&$seenLengthUnits): ?array {
                    $seenLengthUnits[] = $length['unit'];
                    if ($length['unit'] !== 'px') {
                        return null;
                    }

                    return [
                        'unit' => 'rem',
                        'value' => $length['value'] / 16,
                    ];
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: f3(f2(f1(test))); }', [], $visitor);

        $t->same('.foo{width:2rem}', $result);
        $t->same(['f1', 'f2', 'f3'], $seenFunctions);
        $t->same(['px', 'rem', 'rem'], $seenLengthUnits);
        $t->same(['f2' => 'rem', 'f3' => 'rem'], $genericArgumentUnits);
    },
    'custom at-rules compose upstream Color and Length value visitors' => static function (TestRunner $t): void {
        $seenColors = [];
        $seenLengths = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Length' => static function (array $length) use (&$seenLengths): ?array {
                    $seenLengths[] = $length;

                    return $length['unit'] === 'px'
                        ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                        : null;
                },
            ],
            [
                'Color' => static function (array $color) use (&$seenColors): ?array {
                    $seenColors[] = $color;
                    if (($color['type'] ?? null) !== 'rgb') {
                        return null;
                    }

                    return [
                        'type' => 'rgb',
                        'r' => $color['g'],
                        'g' => $color['r'],
                        'b' => $color['b'],
                        'alpha' => $color['alpha'],
                    ];
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: 16px; color: red; }', [], $visitor);

        $t->same('.foo{width:1rem;color:#0f0}', $result);
        $t->same([['unit' => 'px', 'value' => 16.0]], $seenLengths);
        $t->same([['type' => 'rgb', 'r' => 255, 'g' => 0, 'b' => 0, 'alpha' => 1]], $seenColors);
    },
    'custom at-rules compose upstream sequential Color visitors' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Color' => static function (array $color) use (&$seen): array {
                    $seen[] = $color;

                    return [
                        'type' => 'rgb',
                        'r' => $color['g'],
                        'g' => $color['r'],
                        'b' => $color['b'],
                        'alpha' => $color['alpha'],
                    ];
                },
            ],
            [
                'Color' => static function (array $color) use (&$seen): ?array {
                    $seen[] = $color;
                    if (($color['type'] ?? null) !== 'rgb' || $color['g'] <= 0) {
                        return null;
                    }

                    $color['alpha'] /= 2;

                    return $color;
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { color: red; }', [], $visitor);

        $t->same('.foo{color:#00ff0080}', $result);
        $t->same(2, count($seen));
        $t->same([255, 0, 0, 1], [$seen[0]['r'], $seen[0]['g'], $seen[0]['b'], $seen[0]['alpha']]);
        $t->same([0, 255, 0, 1], [$seen[1]['r'], $seen[1]['g'], $seen[1]['b'], $seen[1]['alpha']]);
    },
    'custom at-rules compose upstream EnvironmentVariable visitors in media and declarations' => static function (TestRunner $t): void {
        $tokens = [
            '--branding-small' => [
                'type' => 'length',
                'value' => [
                    'unit' => 'px',
                    'value' => 600,
                ],
            ],
            '--branding-padding' => [
                'type' => 'length',
                'value' => [
                    'unit' => 'px',
                    'value' => 20,
                ],
            ],
        ];
        $seenNames = [];

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'EnvironmentVariable' => [
                    '--branding-small' => static function (array $environmentVariable) use (&$seenNames, $tokens): array {
                        $seenNames[] = $environmentVariable['name'];

                        return $tokens['--branding-small'];
                    },
                ],
            ],
            [
                'EnvironmentVariable' => [
                    '--branding-padding' => static function (array $environmentVariable) use (&$seenNames, $tokens): array {
                        $seenNames[] = $environmentVariable['name'];

                        return $tokens['--branding-padding'];
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
@media (max-width: env(--branding-small)) {
  body {
    padding: env(--branding-padding);
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('@media (width<=600px){body{padding:20px}}', $result);
        $t->same([
            ['type' => 'custom', 'ident' => '--branding-small'],
            ['type' => 'custom', 'ident' => '--branding-padding'],
        ], $seenNames);
    },
    'custom at-rules compose upstream Variable visitors in declaration values' => static function (TestRunner $t): void {
        $tokens = [
            '--branding-small' => [
                'type' => 'length',
                'value' => [
                    'unit' => 'px',
                    'value' => 600,
                ],
            ],
            '--branding-padding' => [
                'type' => 'length',
                'value' => [
                    'unit' => 'px',
                    'value' => 20,
                ],
            ],
        ];
        $seenNames = [];

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Variable' => static function (array $variable) use (&$seenNames, $tokens): ?array {
                    $seenNames[] = $variable['name']['ident'];

                    return $variable['name']['ident'] === '--branding-small' ? $tokens['--branding-small'] : null;
                },
            ],
            [
                'Variable' => static function (array $variable) use (&$seenNames, $tokens): ?array {
                    $seenNames[] = $variable['name']['ident'];

                    return $variable['name']['ident'] === '--branding-padding' ? $tokens['--branding-padding'] : null;
                },
            ],
        ]);

        $css = <<<'CSS'
body {
  padding: var(--branding-padding);
  width: var(--branding-small);
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('body{padding:20px;width:600px}', $result);
        $t->same(['--branding-padding', '--branding-padding', '--branding-small'], $seenNames);
    },
    'custom at-rules serialize upstream raw env and var visitor replacements' => static function (TestRunner $t): void {
        $result = (new CustomAtRuleTransformer())->transform('.foo { margin: env(--gap); padding: var(--pad); }', [], [
            'EnvironmentVariable' => [
                '--gap' => static fn (): array => ['raw' => '10px'],
            ],
            'Variable' => [
                '--pad' => static fn (): array => ['raw' => '20px'],
            ],
        ]);

        $t->same('.foo{margin:10px;padding:20px}', $result);
    },
    'custom at-rules compose upstream Declaration custom property visitors' => static function (TestRunner $t): void {
        $seenTokenTypes = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => [
                    'custom' => [
                        'size' => static function (array $declaration) use (&$seenTokenTypes): array {
                            $seenTokenTypes[] = $declaration['value'][0]['type'];

                            return [
                                [
                                    'property' => 'unparsed',
                                    'value' => [
                                        'propertyId' => ['property' => 'width'],
                                        'value' => $declaration['value'],
                                    ],
                                ],
                                [
                                    'property' => 'unparsed',
                                    'value' => [
                                        'propertyId' => ['property' => 'height'],
                                        'value' => $declaration['value'],
                                    ],
                                ],
                            ];
                        },
                    ],
                ],
            ],
            [
                'Declaration' => [
                    'custom' => [
                        'bg' => static function (array $declaration) use (&$seenTokenTypes): ?array {
                            $seenTokenTypes[] = $declaration['value'][0]['type'];
                            if (($declaration['value'][0]['type'] ?? null) !== 'color') {
                                return null;
                            }

                            return [
                                'property' => 'background-color',
                                'value' => $declaration['value'][0]['value'],
                            ];
                        },
                    ],
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { size: 16px; bg: #ff0; }', [], $visitor);

        $t->same('.foo{width:16px;height:16px;background-color:#ff0}', $result);
        $t->same(['length', 'color'], $seenTokenTypes);
    },
    'custom at-rules compose upstream Declaration replacements with Length visitors' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => [
                    'custom' => [
                        'size' => static fn (): array => [
                            [
                                'property' => 'width',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => ['unit' => 'px', 'value' => 32],
                                    ],
                                ],
                            ],
                            [
                                'property' => 'height',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => ['unit' => 'px', 'value' => 32],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'Length' => static fn (array $length): ?array => $length['unit'] === 'px'
                    ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                    : null,
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { size: test; }', [], $visitor);

        $t->same('.foo{width:2rem;height:2rem}', $result);
    },
    'custom at-rules visit upstream unparsed known declarations before value visitors' => static function (TestRunner $t): void {
        $seenProperties = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => [
                    'width' => static function (array $declaration) use (&$seenProperties): ?array {
                        $seenProperties[] = $declaration['property'];
                        if ($declaration['property'] !== 'unparsed') {
                            return null;
                        }

                        return [
                            [
                                'property' => 'width',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => ['unit' => 'px', 'value' => 32],
                                    ],
                                ],
                            ],
                            [
                                'property' => 'height',
                                'value' => [
                                    'type' => 'length-percentage',
                                    'value' => [
                                        'type' => 'dimension',
                                        'value' => ['unit' => 'px', 'value' => 32],
                                    ],
                                ],
                            ],
                        ];
                    },
                ],
            ],
            [
                'Length' => static fn (array $length): ?array => $length['unit'] === 'px'
                    ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                    : null,
            ],
        ]);

        $css = '.foo { width: test; } .bar { width: 16px; }';
        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.foo{width:2rem;height:2rem}.bar{width:1rem}', $result);
        $t->same(['unparsed', 'width'], $seenProperties);
    },
    'custom at-rules compose upstream returned unparsed Declaration values' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => [
                    'width' => static function (array $declaration): ?array {
                        if (
                            $declaration['property'] === 'unparsed'
                            && ($declaration['value']['value'][0]['type'] ?? null) === 'token'
                            && ($declaration['value']['value'][0]['value']['type'] ?? null) === 'ident'
                        ) {
                            return [
                                'property' => 'unparsed',
                                'value' => [
                                    'propertyId' => ['property' => 'width'],
                                    'value' => [[
                                        'type' => 'var',
                                        'value' => [
                                            'name' => [
                                                'ident' => '--' . $declaration['value']['value'][0]['value']['value'],
                                            ],
                                        ],
                                    ]],
                                ],
                            ];
                        }

                        return null;
                    },
                ],
            ],
            [
                'Declaration' => [
                    'width' => static function (array $declaration): ?array {
                        if ($declaration['property'] !== 'unparsed') {
                            return null;
                        }

                        return [
                            'property' => 'unparsed',
                            'value' => [
                                'propertyId' => ['property' => 'width'],
                                'value' => [[
                                    'type' => 'function',
                                    'value' => [
                                        'name' => 'calc',
                                        'arguments' => $declaration['value']['value'],
                                    ],
                                ]],
                            ],
                        ];
                    },
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: test; }', [], $visitor);

        $t->same('.foo{width:calc(var(--test))}', $result);
    },
    'custom at-rules compose upstream Declaration all-property visitors' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => static function (array $declaration): ?array {
                    if (($declaration['value']['propertyId']['property'] ?? null) !== 'width') {
                        return null;
                    }

                    return [
                        'property' => 'width',
                        'value' => [
                            'type' => 'length-percentage',
                            'value' => [
                                'type' => 'dimension',
                                'value' => ['unit' => 'px', 'value' => 32],
                            ],
                        ],
                    ];
                },
            ],
            [
                'Declaration' => static function (array $declaration): ?array {
                    if (($declaration['value']['propertyId']['property'] ?? null) !== 'height') {
                        return null;
                    }

                    return [
                        'property' => 'height',
                        'value' => [
                            'type' => 'length-percentage',
                            'value' => [
                                'type' => 'dimension',
                                'value' => ['unit' => 'px', 'value' => 32],
                            ],
                        ],
                    ];
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: test; height: test; }', [], $visitor);

        $t->same('.foo{width:32px;height:32px}', $result);
    },
    'custom at-rules compose upstream DeclarationExit all-property visitors' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'DeclarationExit' => static function (array $declaration): ?array {
                    if (($declaration['value']['propertyId']['property'] ?? null) !== 'width') {
                        return null;
                    }

                    return [
                        'property' => 'width',
                        'value' => [
                            'type' => 'length-percentage',
                            'value' => [
                                'type' => 'dimension',
                                'value' => ['unit' => 'px', 'value' => 32],
                            ],
                        ],
                    ];
                },
            ],
            [
                'DeclarationExit' => static function (array $declaration): ?array {
                    if (($declaration['value']['propertyId']['property'] ?? null) !== 'height') {
                        return null;
                    }

                    return [
                        'property' => 'height',
                        'value' => [
                            'type' => 'length-percentage',
                            'value' => [
                                'type' => 'dimension',
                                'value' => ['unit' => 'px', 'value' => 32],
                            ],
                        ],
                    ];
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: test; height: test; }', [], $visitor);

        $t->same('.foo{width:32px;height:32px}', $result);
    },
    'custom at-rules compose upstream StyleSheet and StyleSheetExit visitors' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'StyleSheet' => static function (array $stylesheet) use (&$seen): void {
                    $seen[] = 'enter-a:' . count($stylesheet['rules']);
                },
                'StyleSheetExit' => static function (array $stylesheet) use (&$seen): void {
                    $seen[] = 'exit-a:' . count($stylesheet['rules']);
                },
            ],
            [
                'StyleSheet' => static function (array $stylesheet) use (&$seen): void {
                    $seen[] = 'enter-b:' . count($stylesheet['rules']);
                },
                'StyleSheetExit' => static function (array $stylesheet) use (&$seen): array {
                    $seen[] = 'exit-b:' . count($stylesheet['rules']);
                    usort(
                        $stylesheet['rules'],
                        static fn (array $left, array $right): int => strcmp(
                            (string) ($left['value']['selectors'][0][0]['name'] ?? ''),
                            (string) ($right['value']['selectors'][0][0]['name'] ?? '')
                        )
                    );

                    return $stylesheet;
                },
            ],
        ]);

        $css = <<<'CSS'
.foo {
  width: 32px;
}

.bar {
  width: 80px;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.bar{width:80px}.foo{width:32px}', $result);
        $t->same(['enter-a:2', 'enter-b:2', 'exit-a:2', 'exit-b:2'], $seen);
    },
    'custom at-rules serialize upstream StyleSheetExit style-rule replacements' => static function (TestRunner $t): void {
        $result = (new CustomAtRuleTransformer())->transform('.foo { color: red; }', [], [
            'StyleSheetExit' => static function (array $stylesheet): array {
                $stylesheet['rules'][] = [
                    'type' => 'style',
                    'value' => [
                        'selectors' => [
                            [
                                ['type' => 'class', 'name' => 'visitor-ready'],
                            ],
                        ],
                        'declarations' => [
                            'declarations' => [
                                [
                                    'property' => 'color',
                                    'value' => [
                                        'type' => 'rgb',
                                        'r' => 0,
                                        'g' => 255,
                                        'b' => 0,
                                        'alpha' => 1,
                                    ],
                                ],
                                [
                                    'property' => 'width',
                                    'value' => [
                                        'type' => 'length-percentage',
                                        'value' => [
                                            'type' => 'dimension',
                                            'value' => ['unit' => 'px', 'value' => 32],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                return $stylesheet;
            },
        ]);

        $t->same('.foo{color:red}.visitor-ready{color:#0f0;width:32px}', $result);
    },
    'custom at-rules emit upstream returned supports rules from style visitors' => static function (TestRunner $t): void {
        $seenHeight = null;
        $result = (new CustomAtRuleTransformer())->transform('.foo { color: red; height: 100vh; }', [], [
            'Rule' => [
                'style' => static function (array $rule) use (&$seenHeight): array {
                    foreach ($rule['declarations'] as $declaration) {
                        if (($declaration['property'] ?? null) === 'height') {
                            $seenHeight = $declaration['value'];
                        }
                    }

                    $fallbackRule = [
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
                    ];

                    return [
                        $rule,
                        [
                            'type' => 'supports',
                            'value' => [
                                'condition' => [
                                    'type' => 'declaration',
                                    'propertyId' => ['property' => '-webkit-touch-callout'],
                                    'value' => 'none',
                                ],
                                'rules' => [$fallbackRule],
                            ],
                        ],
                    ];
                },
            ],
        ]);

        $t->same('.foo{color:red;height:100vh}@supports (-webkit-touch-callout:none){.foo{height:-webkit-fill-available}}', $result);
        $t->same('100vh', $seenHeight);
    },
    'custom at-rules emit upstream returned supports rules from custom parser bodies' => static function (TestRunner $t): void {
        $seenBodyRuleType = null;
        $result = (new CustomAtRuleTransformer())->transform('@viewport-fix { .wp-block-cover { height: 100vh; } }', [
            'viewport-fix' => [
                'prelude' => null,
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'viewport-fix' => static function (array $rule) use (&$seenBodyRuleType): array {
                        $seenBodyRuleType = $rule['bodyRules'][0]['type'] ?? null;
                        $fallbackRule = [
                            'type' => 'style',
                            'value' => [
                                'selectors' => $rule['bodyRules'][0]['value']['selectors'],
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
                        ];

                        return [
                            ...$rule['bodyRules'],
                            [
                                'type' => 'supports',
                                'value' => [
                                    'condition' => [
                                        'type' => 'declaration',
                                        'propertyId' => ['property' => '-webkit-touch-callout'],
                                        'value' => 'none',
                                    ],
                                    'rules' => [$fallbackRule],
                                ],
                            ],
                        ];
                    },
                ],
            ],
        ]);

        $t->same('.wp-block-cover{height:100vh}@supports (-webkit-touch-callout:none){.wp-block-cover{height:-webkit-fill-available}}', $result);
        $t->same('style', $seenBodyRuleType);
    },
    'custom at-rules compose upstream MediaQuery visitors for native and returned rules' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'MediaQuery' => static function (array $query) use (&$seen): array {
                    $seen[] = [
                        'phase' => 'enter',
                        'mediaType' => $query['mediaType'] ?? ($query['raw'] ?? null),
                        'feature' => $query['condition']['value']['name'] ?? null,
                    ];

                    return ['raw' => '(min-width: 500px)'];
                },
            ],
            [
                'MediaQueryExit' => static function (array $query) use (&$seen): array {
                    $seen[] = [
                        'phase' => 'exit',
                        'raw' => $query['raw'] ?? null,
                    ];

                    return ['raw' => str_replace('500px', '640px', (string) ($query['raw'] ?? ''))];
                },
            ],
        ]);

        $native = (new CustomAtRuleTransformer())->transform('@media (hover) { .card { color: red; } }', [], $visitor);
        $returned = (new CustomAtRuleTransformer())->transform('@breakpoints { .card { color: yellow; } }', [
            'breakpoints' => [
                'prelude' => null,
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'breakpoints' => static fn (array $rule): array => [
                        'type' => 'media',
                        'value' => [
                            'query' => [
                                'mediaQueries' => [
                                    ['raw' => '(min-width: 480px)'],
                                ],
                            ],
                            'rules' => $rule['bodyRules'],
                        ],
                    ],
                ],
            ],
            'MediaQuery' => $visitor['MediaQuery'],
            'MediaQueryExit' => $visitor['MediaQueryExit'],
        ]);

        $t->same('@media (width>=640px){.card{color:red}}', $native);
        $t->same('@media (width>=640px){.card{color:#ff0}}', $returned);
        $t->same([
            ['phase' => 'enter', 'mediaType' => 'all', 'feature' => 'hover'],
            ['phase' => 'exit', 'raw' => '(min-width: 500px)'],
            ['phase' => 'enter', 'mediaType' => '(min-width: 480px)', 'feature' => null],
            ['phase' => 'exit', 'raw' => '(min-width: 500px)'],
        ], $seen);
    },
    'custom at-rules compose upstream SupportsCondition visitors for native and returned rules' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'SupportsCondition' => static function (array $condition) use (&$seen): array {
                    $seen[] = 'enter:' . ($condition['type'] ?? 'unknown') . ':' . ($condition['propertyId']['property'] ?? '');
                    if (($condition['type'] ?? null) === 'declaration' && ($condition['propertyId']['property'] ?? null) === 'display') {
                        $condition['value'] = 'flex';
                    }

                    return $condition;
                },
            ],
            [
                'SupportsConditionExit' => static function (array $condition) use (&$seen): array {
                    $seen[] = 'exit:' . ($condition['type'] ?? 'unknown') . ':' . ($condition['value'] ?? '');

                    return $condition;
                },
            ],
        ]);

        $native = (new CustomAtRuleTransformer())->transform('@supports (display: grid) { .card { display: grid; } }', [], $visitor);
        $returned = (new CustomAtRuleTransformer())->transform('@viewport-fix { .card { height: 100vh; } }', [
            'viewport-fix' => [
                'prelude' => null,
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'viewport-fix' => static function (array $rule): array {
                        return [
                            ...$rule['bodyRules'],
                            [
                                'type' => 'supports',
                                'value' => [
                                    'condition' => [
                                        'type' => 'declaration',
                                        'propertyId' => ['property' => 'display'],
                                        'value' => 'grid',
                                    ],
                                    'rules' => $rule['bodyRules'],
                                ],
                            ],
                        ];
                    },
                ],
            ],
            'SupportsCondition' => $visitor['SupportsCondition'],
            'SupportsConditionExit' => $visitor['SupportsConditionExit'],
        ]);

        $t->same('@supports (display:flex){.card{display:grid}}', $native);
        $t->same('.card{height:100vh}@supports (display:flex){.card{height:100vh}}', $returned);
        $t->same([
            'enter:declaration:display',
            'exit:declaration:flex',
            'enter:declaration:display',
            'exit:declaration:flex',
        ], $seen);
    },
    'custom at-rules compose upstream Selector prefix visitors' => static function (TestRunner $t): void {
        $seenSelectorTypes = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Selector' => static function (array $selector) use (&$seenSelectorTypes): array {
                    $seenSelectorTypes[] = array_column($selector, 'type');

                    return array_merge([
                        ['type' => 'class', 'name' => 'prefix'],
                        ['type' => 'combinator', 'value' => 'descendant'],
                    ], $selector);
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.a, .b { color: red; }', [], $visitor);

        $t->same('.prefix .a,.prefix .b{color:red}', $result);
        $t->same([['class'], ['class']], $seenSelectorTypes);
    },
    'custom at-rules expose upstream nth-of-S selectors to Selector visitors' => static function (TestRunner $t): void {
        $seenNth = null;
        $visitor = [
            'Selector' => static function (array $selector) use (&$seenNth): array {
                foreach ($selector as &$component) {
                    if (($component['type'] ?? null) === 'pseudo-class' && ($component['kind'] ?? null) === 'nth-child' && isset($component['of'])) {
                        $seenNth = $component;
                        unset($component['of']);
                        $component['kind'] = 'nth-of-type';
                    }
                }
                unset($component);

                return $selector;
            },
        ];

        $result = (new CustomAtRuleTransformer())->transform('a:nth-child(even of a) { color: red; }', [], $visitor);

        $t->same('a:nth-of-type(2n){color:red}', $result);
        $t->same('2n', $seenNth['formula'] ?? null);
        $t->same('type', $seenNth['of'][0][0]['type'] ?? null);
        $t->same('a', $seenNth['of'][0][0]['name'] ?? null);
    },
    'custom at-rules compose upstream Url visitors in declaration values' => static function (TestRunner $t): void {
        $seenUrls = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Url' => static function (array $url) use (&$seenUrls): array {
                    $seenUrls[] = [
                        'url' => $url['url'],
                        'raw' => $url['raw'],
                    ];
                    $url['url'] = 'https://mywebsite.com/' . $url['url'];

                    return $url;
                },
            ],
            [
                'Url' => static function (array $url) use (&$seenUrls): array {
                    $seenUrls[] = [
                        'url' => $url['url'],
                        'raw' => $url['raw'],
                    ];
                    $url['url'] = str_replace('/foo.png', '/assets/foo.png', $url['url']);

                    return $url;
                },
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { background: url(foo.png); }', [], $visitor);

        $t->same('.foo{background:url(https://mywebsite.com/assets/foo.png)}', $result);
        $t->same([
            ['url' => 'foo.png', 'raw' => 'url(foo.png)'],
            ['url' => 'https://mywebsite.com/foo.png', 'raw' => 'url(foo.png)'],
        ], $seenUrls);
    },
    'custom at-rules compose upstream DashedIdent visitors for custom properties and variables' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'DashedIdent' => static function (string $ident) use (&$seen): string {
                    $seen[] = $ident;

                    return '--prefix-' . substr($ident, 2);
                },
            ],
            [
                'DashedIdent' => static function (string $ident) use (&$seen): string {
                    $seen[] = $ident;

                    return str_replace('--prefix-', '--theme-', $ident);
                },
            ],
        ]);

        $css = <<<'CSS'
.foo {
  --foo: #ff0;
  color: var(--foo);
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.foo{--theme-foo:#ff0;color:var(--theme-foo)}', $result);
        $t->same(['--foo', '--prefix-foo', '--foo', '--prefix-foo'], $seen);
    },
    'custom at-rules map upstream CustomIdent visitors for keyframes and animation names' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'CustomIdent' => static function (string $ident) use (&$seen): string {
                    $seen[] = $ident;

                    return 'prefix-' . $ident;
                },
            ],
        ]);

        $css = <<<'CSS'
@keyframes test {
  from { color: red }
  to { color: green }
}

.foo {
  animation: test;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('@keyframes prefix-test{0%{color:red}to{color:green}}.foo{animation:prefix-test}', $result);
        $t->same(['test', 'test'], $seen);
    },
    'custom at-rules apply upstream identifier visitors after parser replacements' => static function (TestRunner $t): void {
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'custom' => [
                        'tokens' => static fn (array $rule, CustomAtRuleTransformer $transformer): array => $transformer->styleRule(':root', [
                            '--' . $rule['prelude'] => 'var(--' . $rule['prelude'] . ')',
                        ]),
                    ],
                ],
            ],
            [
                'DashedIdent' => static fn (string $ident): string => '--wp-' . substr($ident, 2),
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('@tokens accent;', [
            'tokens' => [
                'prelude' => '<custom-ident>',
            ],
        ], $visitor);

        $t->same(':root{--wp-accent:var(--wp-accent)}', $result);
    },
    'custom at-rules map upstream style attribute Length visitors' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transformStyleAttribute('height: calc(100vh - 64px)', [
            'Length' => static function (array $length) use (&$seen): ?array {
                $seen[] = [
                    'unit' => $length['unit'],
                    'value' => $length['value'],
                ];

                return $length['unit'] === 'px'
                    ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                    : null;
            },
        ]);

        $t->same('height:calc(100vh - 4rem)', $result);
        $t->same([
            ['unit' => 'vh', 'value' => 100.0],
            ['unit' => 'px', 'value' => 64.0],
        ], $seen);
    },
    'custom at-rules collect upstream visitor factory dependencies from style attributes' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transformStyleAttributeWithDependencies(
            'height: 12px',
            static function (array $context) use (&$seen): array {
                $addDependency = $context['addDependency'];

                return [
                    'Length' => static function (array $length) use (&$seen, $addDependency): void {
                        $seen[] = [
                            'unit' => $length['unit'],
                            'value' => $length['value'],
                        ];
                        $addDependency([
                            'type' => 'file',
                            'filePath' => 'test.json',
                        ]);
                    },
                ];
            }
        );

        $t->same('height:12px', $result['code']);
        $t->same([
            ['type' => 'file', 'filePath' => 'test.json'],
        ], $result['dependencies']);
        $t->same([
            ['unit' => 'px', 'value' => 12.0],
        ], $seen);
    },
];
