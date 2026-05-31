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
];
