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
];
