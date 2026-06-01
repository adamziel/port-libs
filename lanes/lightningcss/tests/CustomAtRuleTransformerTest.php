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
    'custom at-rules expose upstream typed prelude and body parser aliases' => static function (TestRunner $t): void {
        $definitions = [];
        $seen = [];
        $css = <<<'CSS'
@tokens spacing {
  space: 16px;
  accent: yellow !important;
}

@breakpoint 1024px {
  .card {
    width: token('spacing.space');
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<custom-ident>',
                'body' => 'declaration-list',
            ],
            'breakpoint' => [
                'prelude' => '<length>',
                'body' => 'rule-list',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'tokens' => static function (array $rule) use (&$definitions, &$seen): array {
                        $seen['tokensPrelude'] = $rule['preludeAst'];
                        $seen['tokensBodyType'] = $rule['bodyAst']['type'];
                        $seen['tokensImportant'] = $rule['bodyAst']['value']['importantDeclarations'][0]['value']['name'] ?? null;

                        foreach ($rule['bodyAst']['value']['declarations'] as $declaration) {
                            if (($declaration['property'] ?? null) !== 'custom') {
                                continue;
                            }

                            $definitions[$rule['preludeAst']['value'] . '.' . $declaration['value']['name']] = $declaration['value']['value'];
                        }

                        return [];
                    },
                    'breakpoint' => static function (array $rule) use (&$seen): array {
                        $seen['breakpointPrelude'] = $rule['preludeAst'];
                        $seen['breakpointBodyType'] = $rule['bodyAst']['type'];
                        $seen['breakpointRuleType'] = $rule['bodyAst']['value'][0]['type'] ?? null;

                        return [
                            'type' => 'media',
                            'value' => [
                                'query' => [
                                    'mediaQueries' => [[
                                        'mediaType' => 'all',
                                        'condition' => [
                                            'type' => 'feature',
                                            'value' => [
                                                'type' => 'range',
                                                'name' => 'width',
                                                'operator' => 'less-than-equal',
                                                'value' => $rule['preludeAst'],
                                            ],
                                        ],
                                    ]],
                                ],
                                'rules' => $rule['bodyAst']['value'],
                            ],
                        ];
                    },
                ],
            ],
            'Function' => [
                'token' => static function (array $arguments) use (&$definitions): ?array {
                    return $definitions[$arguments[0] ?? ''] ?? null;
                },
            ],
        ]);

        $t->same('@media (width<=1024px){.card{width:16px}}', $result);
        $t->same(['type' => 'custom-ident', 'value' => 'spacing'], $seen['tokensPrelude']);
        $t->same('declaration-list', $seen['tokensBodyType']);
        $t->same('accent', $seen['tokensImportant']);
        $t->same(['type' => 'length', 'value' => ['type' => 'value', 'value' => ['unit' => 'px', 'value' => 1024.0]]], $seen['breakpointPrelude']);
        $t->same('rule-list', $seen['breakpointBodyType']);
        $t->same('style', $seen['breakpointRuleType']);
    },
    'custom at-rules parse upstream repeated and alternative prelude syntax strings' => static function (TestRunner $t): void {
        $seen = [];
        $css = <<<'CSS'
@tokens heading body;
@breakpoints 320px, 48rem;
@preset compact;
@preset 2;

.wp-block-card {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<custom-ident>+',
            ],
            'breakpoints' => [
                'prelude' => '<length>#',
            ],
            'preset' => [
                'prelude' => 'compact|<number>',
            ],
        ], [
            'Rule' => [
                'custom' => [
                    'tokens' => static function (array $rule) use (&$seen): array {
                        $seen['tokensPrelude'] = $rule['prelude'];
                        $seen['tokensAst'] = $rule['preludeAst'];

                        return [];
                    },
                    'breakpoints' => static function (array $rule) use (&$seen): array {
                        $seen['breakpointsAst'] = $rule['preludeAst'];

                        return [];
                    },
                    'preset' => static function (array $rule) use (&$seen): array {
                        $seen['presets'][] = $rule['preludeAst'];

                        return [];
                    },
                ],
            ],
        ]);

        $t->same('.wp-block-card{color:red}', $result);
        $t->same('heading body', $seen['tokensPrelude']);
        $t->same('repeated', $seen['tokensAst']['type']);
        $t->same(['heading', 'body'], array_column($seen['tokensAst']['value']['components'], 'value'));
        $t->same(['type' => 'space'], $seen['tokensAst']['value']['multiplier']);
        $t->same('repeated', $seen['breakpointsAst']['type']);
        $t->same(['px', 'rem'], array_map(
            static fn (array $component): string => $component['value']['value']['unit'],
            $seen['breakpointsAst']['value']['components']
        ));
        $t->same([320.0, 48.0], array_map(
            static fn (array $component): float => $component['value']['value']['value'],
            $seen['breakpointsAst']['value']['components']
        ));
        $t->same(['type' => 'comma'], $seen['breakpointsAst']['value']['multiplier']);
        $t->same(['type' => 'literal', 'value' => 'compact'], $seen['presets'][0]);
        $t->same(['type' => 'number', 'value' => 2.0], $seen['presets'][1]);

        $transformer = new CustomAtRuleTransformer();
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@tokens inherit;', [
            'tokens' => [
                'prelude' => '<custom-ident>+',
            ],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@breakpoints 320px 48rem;', [
            'breakpoints' => [
                'prelude' => '<length>#',
            ],
        ]));
    },
    'custom at-rules parse upstream extended SyntaxString component preludes' => static function (TestRunner $t): void {
        $seen = [];
        $css = <<<'CSS'
@motion calc(25px + 25px);
@space calc(100% - 25px);
@tilt 90deg;
@delay 250ms;
@density 2dppx;
@move translateX(10px);
@chain translateX(10px) rotate(45deg);
@hero url(hero.png);
@palette red blue;

.keep {
  color: red;
}
CSS;

        $definitions = [
            'motion' => ['prelude' => '<length>'],
            'space' => ['prelude' => '<length-percentage>'],
            'tilt' => ['prelude' => '<angle>'],
            'delay' => ['prelude' => '<time>'],
            'density' => ['prelude' => '<resolution>'],
            'move' => ['prelude' => '<transform-function>'],
            'chain' => ['prelude' => '<transform-list>'],
            'hero' => ['prelude' => '<image>'],
            'palette' => ['prelude' => 'foo | <color>+ | <integer>'],
        ];

        $result = (new CustomAtRuleTransformer())->transform($css, $definitions, [
            'Rule' => [
                'custom' => static function (array $rule) use (&$seen): array {
                    $seen[$rule['name']] = $rule['preludeAst'];

                    return [];
                },
            ],
        ]);

        $t->same('.keep{color:red}', $result);
        $t->same(['unit' => 'px', 'value' => 50.0], $seen['motion']['value']['value']);
        $t->same(['type' => 'calc', 'value' => ['type' => 'raw', 'value' => '100% - 25px']], $seen['space']['value']);
        $t->same(['type' => 'deg', 'value' => 90.0], $seen['tilt']['value']);
        $t->same(['type' => 'milliseconds', 'value' => 250.0], $seen['delay']['value']);
        $t->same(['type' => 'dppx', 'value' => 2.0], $seen['density']['value']);
        $t->same('translateX', $seen['move']['value']['type']);
        $t->same(['unit' => 'px', 'value' => 10.0], $seen['move']['value']['value']['value']);
        $t->same(['translateX', 'rotate'], array_column($seen['chain']['value'], 'type'));
        $t->same(['type' => 'deg', 'value' => 45.0], $seen['chain']['value'][1]['value']);
        $t->same('url', $seen['hero']['value']['type']);
        $t->same('hero.png', $seen['hero']['value']['value']['url']);
        $t->same('repeated', $seen['palette']['type']);
        $t->same('space', $seen['palette']['value']['multiplier']['type']);
        $t->same([255, 0, 0], [
            $seen['palette']['value']['components'][0]['value']['r'],
            $seen['palette']['value']['components'][0]['value']['g'],
            $seen['palette']['value']['components'][0]['value']['b'],
        ]);
        $t->same([0, 0, 255], [
            $seen['palette']['value']['components'][1]['value']['r'],
            $seen['palette']['value']['components'][1]['value']['g'],
            $seen['palette']['value']['components'][1]['value']['b'],
        ]);

        $transformer = new CustomAtRuleTransformer();
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@chain translateX(10px), rotate(45deg);', [
            'chain' => [
                'prelude' => '<transform-list>#',
            ],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@size calc(100% - 25px);', [
            'size' => [
                'prelude' => '<length> | <percentage>',
            ],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $transformer->transform('@motion 50%;', [
            'motion' => [
                'prelude' => '<length>',
            ],
        ]));
    },
    'custom at-rules visit upstream SyntaxString image preludes before custom rule visitors' => static function (TestRunner $t): void {
        $events = [];
        $rules = [];
        $css = <<<'CSS'
@hero url(block-card.png);
@placeholder none;

.keep {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'hero' => ['prelude' => '<image>'],
            'placeholder' => ['prelude' => '<image>'],
        ], [
            'Image' => static function (array $image) use (&$events): array {
                $events[] = 'enter:' . $image['type'] . ':' . (($image['value']['url'] ?? '') ?: 'none');
                if (($image['type'] ?? null) === 'url') {
                    $image['value']['url'] = 'theme/' . $image['value']['url'];
                }

                return $image;
            },
            'Url' => static function (array $url) use (&$events): array {
                $events[] = 'url:' . $url['url'];
                $url['url'] = 'assets/' . $url['url'];

                return $url;
            },
            'ImageExit' => static function (array $image) use (&$events): ?array {
                $events[] = 'exit:' . $image['type'] . ':' . (($image['value']['url'] ?? '') ?: 'none');
                if (($image['type'] ?? null) === 'none') {
                    return [
                        'type' => 'url',
                        'value' => [
                            'url' => 'fallback.svg',
                            'raw' => 'url(fallback.svg)',
                            'loc' => ['line' => 1, 'column' => 1],
                        ],
                    ];
                }

                return $image;
            },
            'Rule' => [
                'custom' => static function (array $rule) use (&$rules, &$events): array {
                    $events[] = 'rule:' . $rule['name'] . ':' . $rule['prelude'];
                    $rules[$rule['name']] = [
                        'prelude' => $rule['prelude'],
                        'preludeAst' => $rule['preludeAst'],
                    ];

                    return [];
                },
            ],
        ]);

        $t->same('.keep{color:red}', $result);
        $t->same([
            'enter:url:block-card.png',
            'url:theme/block-card.png',
            'exit:url:assets/theme/block-card.png',
            'rule:hero:url(assets/theme/block-card.png)',
            'enter:none:none',
            'exit:none:none',
            'rule:placeholder:url(fallback.svg)',
        ], $events);
        $t->same('url(assets/theme/block-card.png)', $rules['hero']['prelude']);
        $t->same('url', $rules['hero']['preludeAst']['value']['type']);
        $t->same('assets/theme/block-card.png', $rules['hero']['preludeAst']['value']['value']['url']);
        $t->same('url(fallback.svg)', $rules['placeholder']['prelude']);
        $t->same('url', $rules['placeholder']['preludeAst']['value']['type']);
        $t->same('fallback.svg', $rules['placeholder']['preludeAst']['value']['value']['url']);

        $composedRules = [];
        $composedVisitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Image' => static function (array $image): array {
                    if (($image['type'] ?? null) === 'url') {
                        $image['value']['url'] = 'cdn/' . $image['value']['url'];
                    }

                    return $image;
                },
            ],
            [
                'ImageExit' => static function (array $image): array {
                    if (($image['type'] ?? null) === 'url') {
                        $image['value']['url'] .= '?v=1';
                    }

                    return $image;
                },
                'Rule' => [
                    'custom' => static function (array $rule) use (&$composedRules): array {
                        $composedRules[$rule['name']] = $rule['prelude'];

                        return [];
                    },
                ],
            ],
        ]);

        $composedResult = (new CustomAtRuleTransformer())->transform('@hero url(card.png);', [
            'hero' => ['prelude' => '<image>'],
        ], $composedVisitor);
        $t->same('', $composedResult);
        $t->same('url(cdn/card.png?v=1)', $composedRules['hero']);
    },
    'custom at-rules visit upstream identifier preludes before custom rule visitors' => static function (TestRunner $t): void {
        $seen = [
            'custom' => [],
            'dashed' => [],
            'rules' => [],
        ];
        $css = <<<'CSS'
@slot hero;
@tokens --accent --spacing;

.keep {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'slot' => ['prelude' => '<custom-ident>'],
            'tokens' => ['prelude' => '<dashed-ident>+'],
        ], [
            'CustomIdent' => static function (string $ident) use (&$seen): string {
                $seen['custom'][] = $ident;

                return 'wp-' . $ident;
            },
            'DashedIdent' => static function (string $ident) use (&$seen): string {
                $seen['dashed'][] = $ident;

                return '--wp-' . substr($ident, 2);
            },
            'Rule' => [
                'custom' => static function (array $rule) use (&$seen): array {
                    $seen['rules'][$rule['name']] = [
                        'prelude' => $rule['prelude'],
                        'preludeAst' => $rule['preludeAst'],
                    ];

                    return [];
                },
            ],
        ]);

        $t->same('.keep{color:red}', $result);
        $t->same(['hero'], $seen['custom']);
        $t->same(['--accent', '--spacing'], $seen['dashed']);
        $t->same('wp-hero', $seen['rules']['slot']['prelude']);
        $t->same(['type' => 'custom-ident', 'value' => 'wp-hero'], $seen['rules']['slot']['preludeAst']);
        $t->same('--wp-accent --wp-spacing', $seen['rules']['tokens']['prelude']);
        $t->same('repeated', $seen['rules']['tokens']['preludeAst']['type']);
        $t->same(['--wp-accent', '--wp-spacing'], array_map(
            static fn (array $component): string => $component['value'],
            $seen['rules']['tokens']['preludeAst']['value']['components'],
        ));
    },
    'custom at-rules visit upstream unit component preludes before custom rule visitors' => static function (TestRunner $t): void {
        $seen = [
            'angles' => [],
            'times' => [],
            'resolutions' => [],
            'rules' => [],
        ];
        $css = <<<'CSS'
@tilt 90deg;
@delays 250ms 1s;
@density 2dppx;

.keep {
  color: red;
}
CSS;

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Angle' => static function (array $angle) use (&$seen): array {
                    $seen['angles'][] = $angle;

                    return [
                        'type' => 'turn',
                        'value' => $angle['value'] / 360,
                    ];
                },
            ],
            [
                'Time' => static function (array $time) use (&$seen): array {
                    $seen['times'][] = $time;

                    return [
                        'type' => 'seconds',
                        'value' => $time['type'] === 'milliseconds' ? $time['value'] / 1000 : $time['value'] * 2,
                    ];
                },
                'Resolution' => static function (array $resolution) use (&$seen): array {
                    $seen['resolutions'][] = $resolution;

                    return [
                        'type' => 'resolution',
                        'value' => [
                            'type' => 'dpi',
                            'value' => $resolution['value'] * 96,
                        ],
                    ];
                },
            ],
            [
                'Rule' => [
                    'custom' => static function (array $rule) use (&$seen): array {
                        $seen['rules'][$rule['name']] = [
                            'prelude' => $rule['prelude'],
                            'preludeAst' => $rule['preludeAst'],
                        ];

                        return [];
                    },
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tilt' => ['prelude' => '<angle>'],
            'delays' => ['prelude' => '<time>+'],
            'density' => ['prelude' => '<resolution>'],
        ], $visitor);

        $t->same('.keep{color:red}', $result);
        $t->same([['type' => 'deg', 'value' => 90.0]], $seen['angles']);
        $t->same([
            ['type' => 'milliseconds', 'value' => 250.0],
            ['type' => 'seconds', 'value' => 1.0],
        ], $seen['times']);
        $t->same([['type' => 'dppx', 'value' => 2.0]], $seen['resolutions']);
        $t->same('0.25turn', $seen['rules']['tilt']['prelude']);
        $t->same(['type' => 'turn', 'value' => 0.25], $seen['rules']['tilt']['preludeAst']['value']);
        $t->same('0.25s 2s', $seen['rules']['delays']['prelude']);
        $t->same(['seconds', 'seconds'], array_map(
            static fn (array $component): string => $component['value']['type'],
            $seen['rules']['delays']['preludeAst']['value']['components']
        ));
        $t->same([0.25, 2.0], array_map(
            static fn (array $component): float => $component['value']['value'],
            $seen['rules']['delays']['preludeAst']['value']['components']
        ));
        $t->same('192dpi', $seen['rules']['density']['prelude']);
        $t->same(['type' => 'dpi', 'value' => 192.0], $seen['rules']['density']['preludeAst']['value']);
    },
    'custom at-rules visit upstream length-percentage preludes before custom rule visitors' => static function (TestRunner $t): void {
        $seen = [
            'lengths' => [],
            'rules' => [],
        ];
        $css = <<<'CSS'
@space 16px;
@gaps 16px, 2rem, 25%;

.keep {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'space' => ['prelude' => '<length-percentage>'],
            'gaps' => ['prelude' => '<length-percentage>#'],
        ], [
            'Length' => static function (array $length) use (&$seen): array {
                $seen['lengths'][] = $length;

                return [
                    'unit' => 'rem',
                    'value' => $length['unit'] === 'px' ? $length['value'] / 16 : $length['value'] * 2,
                ];
            },
            'Rule' => [
                'custom' => static function (array $rule) use (&$seen): array {
                    $seen['rules'][$rule['name']] = [
                        'prelude' => $rule['prelude'],
                        'preludeAst' => $rule['preludeAst'],
                    ];

                    return [];
                },
            ],
        ]);

        $t->same('.keep{color:red}', $result);
        $t->same([
            ['unit' => 'px', 'value' => 16.0],
            ['unit' => 'px', 'value' => 16.0],
            ['unit' => 'rem', 'value' => 2.0],
        ], $seen['lengths']);
        $t->same('1rem', $seen['rules']['space']['prelude']);
        $t->same([
            'type' => 'length-percentage',
            'value' => [
                'type' => 'dimension',
                'value' => ['unit' => 'rem', 'value' => 1.0],
            ],
        ], $seen['rules']['space']['preludeAst']);
        $t->same('1rem,4rem,25%', $seen['rules']['gaps']['prelude']);

        $gapComponents = $seen['rules']['gaps']['preludeAst']['value']['components'];
        $t->same(['dimension', 'dimension', 'percentage'], array_map(
            static fn (array $component): string => $component['value']['type'],
            $gapComponents
        ));
        $t->same(['rem', 'rem'], array_map(
            static fn (array $component): string => $component['value']['value']['unit'],
            array_slice($gapComponents, 0, 2)
        ));
        $t->same([1.0, 4.0], array_map(
            static fn (array $component): float => $component['value']['value']['value'],
            array_slice($gapComponents, 0, 2)
        ));
        $t->same(0.25, $gapComponents[2]['value']['value']);
    },
    'custom at-rules visit upstream ratio component preludes before custom rule visitors' => static function (TestRunner $t): void {
        $seen = [
            'ratios' => [],
            'rules' => [],
        ];
        $css = <<<'CSS'
@viewport 16 / 9 {
  .hero {
    color: yellow;
  }
}

@ratios 1/1, 3/2;
CSS;

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Ratio' => static function (array $ratio) use (&$seen): array {
                    $seen['ratios'][] = $ratio;

                    return match ($ratio) {
                        [16.0, 9.0] => [4.0, 3.0],
                        [1.0, 1.0] => [2.0, 1.0],
                        [3.0, 2.0] => [9.0, 4.0],
                        default => $ratio,
                    };
                },
            ],
            [
                'Rule' => [
                    'custom' => [
                        'viewport' => static function (array $rule) use (&$seen): array {
                            $seen['rules']['viewport'] = [
                                'prelude' => $rule['prelude'],
                                'preludeAst' => $rule['preludeAst'],
                            ];

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
                        'ratios' => static function (array $rule) use (&$seen): array {
                            $seen['rules']['ratios'] = [
                                'prelude' => $rule['prelude'],
                                'preludeAst' => $rule['preludeAst'],
                            ];

                            return [];
                        },
                    ],
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'viewport' => [
                'prelude' => '<ratio>',
                'body' => 'rule-list',
            ],
            'ratios' => [
                'prelude' => '<ratio>#',
            ],
        ], $visitor);

        $t->same('@media (aspect-ratio:4/3){.hero{color:#ff0}}', $result);
        $t->same([[16.0, 9.0], [1.0, 1.0], [3.0, 2.0]], $seen['ratios']);
        $t->same('4/3', $seen['rules']['viewport']['prelude']);
        $t->same(['type' => 'ratio', 'value' => [4.0, 3.0]], $seen['rules']['viewport']['preludeAst']);
        $t->same('2,9/4', $seen['rules']['ratios']['prelude']);
        $t->same('repeated', $seen['rules']['ratios']['preludeAst']['type']);
        $t->same([[2.0, 1.0], [9.0, 4.0]], array_map(
            static fn (array $component): array => $component['value'],
            $seen['rules']['ratios']['preludeAst']['value']['components']
        ));
    },
    'custom at-rules visit upstream universal token-list preludes before custom rule visitors' => static function (TestRunner $t): void {
        $seen = [
            'events' => [],
            'rule' => null,
        ];
        $css = <<<'CSS'
@plugin theme("card-gap") var(--wp-gap) env(--wp-breakpoint) 3--wp-step @--wp-accent "draft";

.keep {
  color: red;
}
CSS;

        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Function' => [
                    'theme' => static function (array $arguments) use (&$seen): string {
                        $seen['events'][] = 'function:theme:' . ($arguments[0] ?? '');

                        return '16px';
                    },
                ],
                'Variable' => [
                    '--wp-gap' => static function (array $variable) use (&$seen): array {
                        $seen['events'][] = 'variable:' . ($variable['name']['ident'] ?? '');

                        return [
                            'unit' => 'px',
                            'value' => 24.0,
                        ];
                    },
                ],
                'EnvironmentVariable' => [
                    '--wp-breakpoint' => static function (array $environmentVariable) use (&$seen): array {
                        $seen['events'][] = 'environment:' . ($environmentVariable['name']['ident'] ?? $environmentVariable['name']['value'] ?? '');

                        return [
                            'type' => 'length',
                            'unit' => 'px',
                            'value' => 782.0,
                        ];
                    },
                ],
                'Token' => [
                    'dimension' => static function (array $token) use (&$seen): array {
                        $seen['events'][] = 'token:dimension:' . $token['value'] . $token['unit'];

                        return [
                            'type' => 'function',
                            'value' => [
                                'name' => 'calc',
                                'arguments' => [
                                    ['type' => 'raw', 'value' => (string) $token['value']],
                                    ['type' => 'token', 'value' => ['type' => 'delim', 'value' => '*']],
                                    [
                                        'type' => 'var',
                                        'value' => [
                                            'name' => ['ident' => $token['unit']],
                                            'fallback' => null,
                                            'raw' => 'var(' . $token['unit'] . ')',
                                        ],
                                    ],
                                ],
                            ],
                        ];
                    },
                    'at-keyword' => static function (array $token) use (&$seen): array {
                        $seen['events'][] = 'token:at-keyword:' . $token['value'];

                        return [
                            'type' => 'color',
                            'value' => [
                                'type' => 'rgb',
                                'r' => 5,
                                'g' => 110,
                                'b' => 240,
                                'alpha' => 1,
                            ],
                        ];
                    },
                    'string' => static function (array $token) use (&$seen): array {
                        $seen['events'][] = 'token:string:' . $token['value'];

                        return [
                            'type' => 'token',
                            'raw' => '"live"',
                            'value' => [
                                'type' => 'string',
                                'value' => 'live',
                            ],
                        ];
                    },
                ],
            ],
            [
                'Rule' => [
                    'custom' => [
                        'plugin' => static function (array $rule) use (&$seen): array {
                            $seen['events'][] = 'rule:' . $rule['name'] . ':' . $rule['prelude'];
                            $seen['rule'] = [
                                'prelude' => $rule['prelude'],
                                'preludeAst' => $rule['preludeAst'],
                            ];

                            return [];
                        },
                    ],
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'plugin' => [
                'prelude' => '*',
            ],
        ], $visitor);

        $t->same('.keep{color:red}', $result);
        $t->same([
            'function:theme:card-gap',
            'variable:--wp-gap',
            'environment:--wp-breakpoint',
            'token:dimension:3--wp-step',
            'token:at-keyword:--wp-accent',
            'token:string:draft',
            'rule:plugin:16px 24px 782px calc(3*var(--wp-step)) #056ef0 "live"',
        ], $seen['events']);
        $t->same('16px 24px 782px calc(3*var(--wp-step)) #056ef0 "live"', $seen['rule']['prelude']);
        $t->same('token-list', $seen['rule']['preludeAst']['type']);
        $t->same([
            'length',
            'length',
            'length',
            'function',
            'color',
            'token',
        ], array_map(
            static fn (array $component): string => $component['type'],
            $seen['rule']['preludeAst']['value']
        ));
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
    'custom at-rules compose upstream Token scalar visitors in declaration values' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Token' => [
                    'ident' => static function (array $token) use (&$seen): ?string {
                        $seen[] = $token;

                        return $token['value'] === 'draft' ? 'published' : null;
                    },
                    'hash' => static function (array $token) use (&$seen): array {
                        $seen[] = $token;

                        return [
                            'type' => 'token',
                            'value' => [
                                'type' => 'hash',
                                'value' => '123456',
                            ],
                        ];
                    },
                ],
            ],
            [
                'Token' => [
                    'id-hash' => static function (array $token) use (&$seen): array {
                        $seen[] = $token;

                        return [
                            'type' => 'token',
                            'value' => [
                                'type' => 'id-hash',
                                'value' => 'wp-card-live',
                            ],
                        ];
                    },
                    'string' => static function (array $token) use (&$seen): array {
                        $seen[] = $token;

                        return [
                            'type' => 'token',
                            'value' => [
                                'type' => 'string',
                                'value' => 'live',
                            ],
                        ];
                    },
                    'number' => static function (array $token) use (&$seen): array {
                        $seen[] = $token;

                        return [
                            'type' => 'token',
                            'value' => [
                                'type' => 'number',
                                'value' => $token['value'] * 2,
                            ],
                        ];
                    },
                    'percentage' => static function (array $token) use (&$seen): array {
                        $seen[] = $token;

                        return [
                            'type' => 'token',
                            'value' => [
                                'type' => 'percentage',
                                'value' => $token['value'] * 2,
                            ],
                        ];
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
.wp-block-card {
  --wp-state: draft;
  --wp-color-token: #056ef0;
  --wp-anchor-token: #card;
  --wp-label: "draft";
  --wp-columns: 3;
  --wp-progress: 25%;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('.wp-block-card{--wp-state:published;--wp-color-token:#123456;--wp-anchor-token:#wp-card-live;--wp-label:"live";--wp-columns:6;--wp-progress:50%}', $result);
        $t->same(['ident', 'hash', 'id-hash', 'string', 'number', 'percentage'], array_column($seen, 'type'));
        $t->same(['draft', '056ef0', 'card', 'draft', 3.0, 0.25], array_column($seen, 'value'));
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
    'custom at-rules map upstream Declaration raw overflow-scrolling visitor' => static function (TestRunner $t): void {
        $seen = [];
        $visitOverflow = static function (array $declaration) use (&$seen): array {
            $seen[] = $declaration['property'];

            return [
                $declaration,
                [
                    'property' => '-webkit-overflow-scrolling',
                    'raw' => 'touch',
                ],
            ];
        };

        $result = (new CustomAtRuleTransformer())->transform('.foo { overflow: auto; }', [], [
            'Declaration' => [
                'overflow' => $visitOverflow,
                'overflow-x' => $visitOverflow,
                'overflow-y' => $visitOverflow,
            ],
        ]);

        $t->same('.foo{-webkit-overflow-scrolling:touch;overflow:auto}', $result);
        $t->same(['overflow'], $seen);
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

        $t->same('@media (prefers-color-scheme:dark){html:not([theme=light]) body{background:#000}}html[theme=dark] body{background:#000}', $result);
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
    'custom at-rules expose upstream transform AST to style visitors' => static function (TestRunner $t): void {
        $seen = [];
        $css = <<<'CSS'
.foo {
  transform: translateX(50px);
}

.bar {
  transform: translateX(20%);
}

.baz {
  transform: translateX(calc(100vw - 20px));
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], [
            'Rule' => [
                'style' => static function (array $style) use (&$seen): array {
                    $clone = null;
                    foreach ($style['value']['declarations']['declarations'] as $property) {
                        if (($property['property'] ?? null) !== 'transform') {
                            continue;
                        }

                        $clonedTransforms = [];
                        foreach ($property['value'] as $transform) {
                            $seen[] = [
                                'function' => $transform['type'] ?? null,
                                'argument' => $transform['value']['type'] ?? null,
                            ];
                            if (($transform['type'] ?? null) !== 'translateX') {
                                $clonedTransforms[] = $transform;
                                continue;
                            }

                            if ($clone === null) {
                                $clone = $style;
                                $clone['value']['declarations']['declarations'] = [];
                            }

                            $argument = $transform['value'];
                            if (($argument['type'] ?? null) === 'dimension') {
                                $argument = [
                                    'type' => 'dimension',
                                    'value' => [
                                        'unit' => $argument['value']['unit'],
                                        'value' => -$argument['value']['value'],
                                    ],
                                ];
                            } elseif (($argument['type'] ?? null) === 'percentage') {
                                $argument = [
                                    'type' => 'percentage',
                                    'value' => -$argument['value'],
                                ];
                            } elseif (($argument['type'] ?? null) === 'calc') {
                                $argument = [
                                    'type' => 'calc',
                                    'value' => [
                                        'type' => 'product',
                                        'value' => [-1, $argument],
                                    ],
                                ];
                            }

                            $clonedTransforms[] = [
                                'type' => 'translateX',
                                'value' => $argument,
                            ];
                        }

                        if ($clone !== null) {
                            $clone['value']['declarations']['declarations'][] = [
                                'property' => 'transform',
                                'value' => $clonedTransforms,
                            ];
                        }
                    }

                    if ($clone === null) {
                        return $style;
                    }

                    $lastSelector = array_key_last($clone['value']['selectors']);
                    $clone['value']['selectors'][$lastSelector][] = [
                        'type' => 'pseudo-class',
                        'kind' => 'dir',
                        'direction' => 'rtl',
                    ];

                    return [$style, $clone];
                },
            ],
        ]);

        $t->same('.foo{transform:translate(50px)}.foo:dir(rtl){transform:translate(-50px)}.bar{transform:translate(20%)}.bar:dir(rtl){transform:translate(-20%)}.baz{transform:translate(calc(100vw - 20px))}.baz:dir(rtl){transform:translate(-1*calc(100vw - 20px))}', $result);
        $t->same([
            ['function' => 'translateX', 'argument' => 'dimension'],
            ['function' => 'translateX', 'argument' => 'percentage'],
            ['function' => 'translateX', 'argument' => 'calc'],
        ], $seen);
    },
    'custom at-rules expose upstream nested unknown style rules to apply visitors' => static function (TestRunner $t): void {
        $defined = [];
        $seen = [];
        $seenApplyPrelude = null;

        $css = <<<'CSS'
--toolbar-theme {
  color: white;
  border: 1px solid green;
}

.toolbar {
  @apply --toolbar-theme;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], [
            'Rule' => [
                'style' => static function (array $rule) use (&$defined, &$seen, &$seenApplyPrelude): array {
                    $selector = $rule['value']['selectors'][0] ?? [];
                    $seen[] = [
                        'selectorType' => $selector[0]['type'] ?? null,
                        'selectorName' => $selector[0]['name'] ?? null,
                        'childRules' => count($rule['value']['rules'] ?? []),
                    ];

                    if (
                        count($selector) === 1
                        && ($selector[0]['type'] ?? null) === 'type'
                        && str_starts_with((string) ($selector[0]['name'] ?? ''), '--')
                    ) {
                        $defined[$selector[0]['name']] = $rule['value']['declarations'];

                        return ['type' => 'ignored'];
                    }

                    $remaining = [];
                    foreach (($rule['value']['rules'] ?? []) as $child) {
                        if (($child['type'] ?? null) !== 'unknown' || ($child['value']['name'] ?? null) !== 'apply') {
                            $remaining[] = $child;
                            continue;
                        }

                        foreach (($child['value']['prelude'] ?? []) as $token) {
                            $seenApplyPrelude = $token;
                            if (($token['type'] ?? null) === 'dashed-ident' && isset($defined[$token['value']])) {
                                $rule['value']['declarations']['declarations'] = [
                                    ...($rule['value']['declarations']['declarations'] ?? []),
                                    ...($defined[$token['value']]['declarations'] ?? []),
                                ];
                                $rule['value']['declarations']['importantDeclarations'] = [
                                    ...($rule['value']['declarations']['importantDeclarations'] ?? []),
                                    ...($defined[$token['value']]['importantDeclarations'] ?? []),
                                ];
                            }
                        }
                    }
                    $rule['value']['rules'] = $remaining;

                    return $rule;
                },
            ],
        ]);

        $t->same('.toolbar{color:#fff;border:1px solid green}', $result);
        $t->same([
            ['selectorType' => 'type', 'selectorName' => '--toolbar-theme', 'childRules' => 0],
            ['selectorType' => 'class', 'selectorName' => 'toolbar', 'childRules' => 1],
        ], $seen);
        $t->same(['type' => 'dashed-ident', 'value' => '--toolbar-theme'], $seenApplyPrelude);
        $t->same(['declarations', 'importantDeclarations'], array_keys($defined['--toolbar-theme']));
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
    'custom at-rules compose upstream EnvironmentVariableExit and VariableExit visitors' => static function (TestRunner $t): void {
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'EnvironmentVariableExit' => [
                    '--branding-small' => static function (array $environmentVariable) use (&$seen): array {
                        $seen[] = ['env-exit', $environmentVariable['name']['ident']];

                        return [
                            'type' => 'length',
                            'value' => [
                                'unit' => 'px',
                                'value' => 600,
                            ],
                        ];
                    },
                ],
            ],
            [
                'VariableExit' => [
                    '--card-gap' => static function (array $variable) use (&$seen): array {
                        $seen[] = ['var-exit', $variable['name']['ident']];

                        return ['raw' => '24px'];
                    },
                ],
            ],
        ]);

        $css = <<<'CSS'
@media (max-width: env(--branding-small)) {
  .card {
    padding: var(--card-gap);
  }
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);

        $t->same('@media (width<=600px){.card{padding:24px}}', $result);
        $t->same([
            ['env-exit', '--branding-small'],
            ['var-exit', '--card-gap'],
        ], $seen);
    },
    'custom at-rules compose upstream EnvironmentVariable visitors inside generic functions' => static function (TestRunner $t): void {
        $tokens = [
            '--percentage1' => '25%',
            '--percentage2' => '75%',
            '--length1' => '10px',
            '--length2' => '20px',
        ];
        $seenNames = [];

        $result = (new CustomAtRuleTransformer())->transform(
            '.test { background: linear-gradient(red env(--percentage1), blue env(--percentage2)); width: calc(env(--length1) - env(--length2)); }',
            [],
            [
                'EnvironmentVariable' => static function (array $environmentVariable) use (&$seenNames, $tokens): ?array {
                    $name = $environmentVariable['name']['ident'] ?? $environmentVariable['name']['value'] ?? '';
                    $seenNames[] = $name;

                    return isset($tokens[$name]) ? ['raw' => $tokens[$name]] : null;
                },
            ]
        );

        $t->same('.test{background:linear-gradient(red 25%,#00f 75%);width:-10px}', $result);
        $t->same(['--percentage1', '--percentage2', '--length1', '--length2'], $seenNames);
    },
    'custom at-rules revisit upstream raw Function variables' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transform('.foo { color: theme("foo"); background: theme("red"); }', [], [
            'Function' => [
                'theme' => static function (array $arguments): ?array {
                    if (($arguments[0] ?? null) === 'foo') {
                        return ['raw' => 'var(--foo)'];
                    }
                    if (($arguments[0] ?? null) === 'red') {
                        return ['raw' => 'rgba(255, 0, 0)'];
                    }

                    return null;
                },
            ],
            'DashedIdent' => static function (string $ident) use (&$seen): string {
                $seen[] = $ident;

                return '--prefix-' . substr($ident, 2);
            },
        ]);

        $t->same('.foo{color:var(--prefix-foo);background:red}', $result);
        $t->same(['--foo'], $seen);
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
    'custom at-rules compose upstream Declaration replacements in any order' => static function (TestRunner $t): void {
        $expandSize = [
            'Declaration' => [
                'custom' => [
                    'size' => static fn (): array => [
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
                        [
                            'property' => 'height',
                            'value' => [
                                'type' => 'length-percentage',
                                'value' => [
                                    'type' => 'dimension',
                                    'value' => ['unit' => 'px', 'value' => 16],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $removeWidth = [
            'Declaration' => [
                'width' => static fn (array $declaration): array|null => ($declaration['property'] ?? null) === 'width'
                    ? []
                    : null,
            ],
        ];

        $results = [];
        foreach ([[$expandSize, $removeWidth], [$removeWidth, $expandSize]] as $visitorOrder) {
            $results[] = (new CustomAtRuleTransformer())->transform(
                '.foo { size: 16px; }',
                [],
                CustomAtRuleTransformer::composeVisitors($visitorOrder)
            );
        }

        $t->same(['.foo{height:16px}', '.foo{height:16px}'], $results);
    },
    'custom at-rules compose upstream DeclarationExit replacements in any order' => static function (TestRunner $t): void {
        $expandSize = [
            'DeclarationExit' => [
                'custom' => [
                    'size' => static fn (): array => [
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
                        [
                            'property' => 'height',
                            'value' => [
                                'type' => 'length-percentage',
                                'value' => [
                                    'type' => 'dimension',
                                    'value' => ['unit' => 'px', 'value' => 16],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $removeWidth = [
            'DeclarationExit' => [
                'width' => static fn (array $declaration): array|null => ($declaration['property'] ?? null) === 'width'
                    ? []
                    : null,
            ],
        ];

        $results = [];
        foreach ([[$expandSize, $removeWidth], [$removeWidth, $expandSize]] as $visitorOrder) {
            $results[] = (new CustomAtRuleTransformer())->transform(
                '.foo { size: 16px; }',
                [],
                CustomAtRuleTransformer::composeVisitors($visitorOrder)
            );
        }

        $t->same(['.foo{height:16px}', '.foo{height:16px}'], $results);
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
    'custom at-rules apply upstream StyleSheet enter replacements before child visitors' => static function (TestRunner $t): void {
        $seen = [];
        $seenLengths = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
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
                    $seen['customName'] = is_array($customRule) ? ($customRule['name'] ?? null) : null;
                    $seen['customPreludeType'] = is_array($customRule) ? ($customRule['preludeAst']['type'] ?? null) : null;
                    $seen['customBodyType'] = is_array($bodyAst) ? ($bodyAst['type'] ?? null) : null;
                    $seen['customDeclarationName'] = $declaration['value']['name'] ?? null;
                    $seen['customDeclarationTokenType'] = $accentTokens[0]['type'] ?? null;

                    return [
                        'rules' => [
                            [
                                'type' => 'style',
                                'value' => [
                                    'selectors' => [
                                        [
                                            ['type' => 'class', 'name' => 'tokens-from-sheet'],
                                        ],
                                    ],
                                    'declarations' => [
                                        'declarations' => [
                                            [
                                                'property' => 'unparsed',
                                                'value' => [
                                                    'propertyId' => ['property' => 'color'],
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
                'Length' => static function (array $length) use (&$seenLengths): ?array {
                    $seenLengths[] = $length['value'];

                    return $length['unit'] === 'px'
                        ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                        : null;
                },
            ],
        ]);

        $css = <<<'CSS'
@tokens 8px {
  accent: yellow;
}

.card {
  width: 32px;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<length>',
                'body' => 'declaration-list',
            ],
        ], $visitor);

        $t->same('.tokens-from-sheet{color:#ff0;width:1rem}.card{width:2rem}', $result);
        $t->same(['custom', 'style'], $seen['ruleTypes']);
        $t->same('tokens', $seen['customName']);
        $t->same('length', $seen['customPreludeType']);
        $t->same('declaration-list', $seen['customBodyType']);
        $t->same('accent', $seen['customDeclarationName']);
        $t->same('color', $seen['customDeclarationTokenType']);
        $t->same([16.0, 32.0], $seenLengths);
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
    'custom at-rules map upstream generic Rule visitor currentColor passthrough' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transform('.foo { color: currentColor; }', [], [
            'Rule' => static function (array $rule) use (&$seen): array {
                $seen[] = [
                    'type' => $rule['type'] ?? null,
                    'selector' => $rule['value']['selectors'][0][0]['name'] ?? null,
                    'property' => $rule['value']['declarations']['declarations'][0]['property'] ?? null,
                    'value' => $rule['value']['declarations']['declarations'][0]['value'] ?? null,
                ];

                return $rule;
            },
        ]);

        $t->same('.foo{color:currentColor}', $result);
        $t->same([
            [
                'type' => 'style',
                'selector' => 'foo',
                'property' => 'color',
                'value' => 'currentColor',
            ],
        ], $seen);
    },
    'custom at-rules reject upstream visitor returned invalid dashed var names' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new CustomAtRuleTransformer())->transform('.foo { background: opacity(abcdef); }', [], [
            'Function' => static function (array $arguments, string $raw, string $name): ?array {
                if (($arguments[0] ?? null) !== 'abcdef') {
                    return null;
                }

                return [
                    'type' => 'function',
                    'value' => [
                        'name' => $name,
                        'arguments' => [[
                            'type' => 'var',
                            'value' => [
                                'name' => [
                                    'ident' => $arguments[0],
                                ],
                            ],
                        ]],
                    ],
                ];
            },
        ]));
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
    'custom at-rules apply upstream RuleExit custom and unknown visitors after parser' => static function (TestRunner $t): void {
        $seen = [];
        $css = <<<'CSS'
@tokens wp {
  accent: yellow;
  spacing: 16px;
}

@dep "tokens.json";

.card {
  color: red;
}
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<custom-ident>',
                'body' => 'declaration-list',
            ],
        ], [
            'RuleExit' => [
                'custom' => [
                    'tokens' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                        $seen['customBodyType'] = $rule['bodyAst']['type'] ?? null;
                        $seen['firstDeclaration'] = $rule['declarations'][0]['property'] ?? null;

                        return $transformer->styleRule('.tokens-ready', [
                            'outline-color' => $rule['declarations'][0]['value'] ?? 'transparent',
                        ]);
                    },
                ],
                'unknown' => [
                    'dep' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                        $seen['depName'] = $rule['preludeTokens'][0]['value']['value'] ?? null;

                        return $transformer->styleRule('.dep-ready', [
                            'outline-color' => '#056ef0',
                        ]);
                    },
                ],
            ],
        ]);

        $t->same('.tokens-ready{outline-color:#ff0}.dep-ready{outline-color:#056ef0}.card{color:red}', $result);
        $t->same('declaration-list', $seen['customBodyType']);
        $t->same('accent', $seen['firstDeclaration']);
        $t->same('tokens.json', $seen['depName']);
    },
    'custom at-rules compose upstream RuleExit style visitors' => static function (TestRunner $t): void {
        $seenSelectors = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'RuleExit' => [
                    'style' => static function (array $rule): ?array {
                        if (($rule['selector'] ?? null) !== '.card') {
                            return null;
                        }

                        $clone = $rule;
                        $clone['selector'] = '.card--exit';
                        $clone['selectors'] = ['.card--exit'];

                        return [$rule, $clone];
                    },
                ],
            ],
            [
                'RuleExit' => [
                    'style' => static function (array $rule) use (&$seenSelectors): array {
                        $seenSelectors[] = $rule['selector'] ?? '';
                        $rule['declarations'][] = [
                            'property' => 'height',
                            'value' => '16px',
                            'important' => false,
                        ];

                        return $rule;
                    },
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.card { width: 16px; }', [], $visitor);

        $t->same('.card{width:16px;height:16px}.card--exit{width:16px;height:16px}', $result);
        $t->same(['.card', '.card--exit'], $seenSelectors);
    },
    'custom at-rules apply upstream RuleExit media visitors after body traversal' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transform('@media (hover) { .notice { width: 16px; } }', [], [
            'RuleExit' => [
                'media' => static function (array $rule) use (&$seen): array {
                    $seen['type'] = $rule['type'] ?? null;
                    $seen['feature'] = $rule['value']['query']['mediaQueries'][0]['condition']['value']['name'] ?? null;
                    $rule['value']['query'] = [
                        'mediaQueries' => [
                            ['raw' => '(min-width: 640px)'],
                        ],
                    ];

                    return $rule;
                },
            ],
        ]);

        $t->same('@media (width>=640px){.notice{width:16px}}', $result);
        $t->same('media', $seen['type']);
        $t->same('hover', $seen['feature']);
    },
    'custom at-rules expose visited media children to generic RuleExit visitors' => static function (TestRunner $t): void {
        $seen = [];
        $result = (new CustomAtRuleTransformer())->transform('@media (hover) { .card { width: 16px; } }', [], [
            'Length' => static function (array $length): ?array {
                if (($length['unit'] ?? null) !== 'px') {
                    return null;
                }

                return [
                    'unit' => 'rem',
                    'value' => ((float) $length['value']) / 16,
                ];
            },
            'RuleExit' => static function (array $rule) use (&$seen): ?array {
                if (($rule['type'] ?? null) !== 'media') {
                    return null;
                }

                $seen['declaration'] = $rule['value']['rules'][0]['value']['declarations']['declarations'][0] ?? null;

                return null;
            },
        ]);

        $t->same('@media (hover){.card{width:1rem}}', $result);
        $t->same([
            'property' => 'width',
            'raw' => '1rem',
            'important' => false,
        ], $seen['declaration']);
    },
    'custom at-rules preserve visited preludes and bodies without replacements' => static function (TestRunner $t): void {
        $seen = [];
        $css = <<<'CSS'
@tokens theme {
  .card {
    width: 16px;
  }
}

@alias accent;
CSS;

        $result = (new CustomAtRuleTransformer())->transform($css, [
            'tokens' => [
                'prelude' => '<custom-ident>',
                'body' => 'rule-list',
            ],
            'alias' => [
                'prelude' => '<custom-ident>',
            ],
        ], [
            'CustomIdent' => static fn (string $ident): string => 'wp-' . $ident,
            'Length' => static function (array $length): ?array {
                if (($length['unit'] ?? null) !== 'px') {
                    return null;
                }

                return [
                    'unit' => 'rem',
                    'value' => ((float) $length['value']) / 16,
                ];
            },
            'RuleExit' => [
                'custom' => static function (array $rule) use (&$seen): ?array {
                    $seen[$rule['name']] = $rule;

                    return null;
                },
            ],
        ]);

        $t->same('@tokens wp-theme{.card{width:1rem}}@alias wp-accent;', $result);
        $t->same('wp-theme', $seen['tokens']['prelude']);
        $t->same('1rem', $seen['tokens']['bodyRules'][0]['value']['declarations']['declarations'][0]['raw']);
        $t->same('wp-accent', $seen['alias']['prelude']);
    },
];
