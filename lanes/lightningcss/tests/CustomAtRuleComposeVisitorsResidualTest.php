<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

return [
    'custom at-rules compose residual upstream same Declaration properties' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::same properties lines 179-221.
        $seen = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Declaration' => [
                    'color' => static function (array $declaration) use (&$seen): ?array {
                        $seen[] = [
                            'first',
                            $declaration['property'] ?? null,
                            [
                                $declaration['value']['r'] ?? null,
                                $declaration['value']['g'] ?? null,
                                $declaration['value']['b'] ?? null,
                                $declaration['value']['alpha'] ?? null,
                            ],
                        ];

                        if (($declaration['property'] ?? null) !== 'color' || ($declaration['value']['type'] ?? null) !== 'rgb') {
                            return null;
                        }

                        return [
                            'property' => 'color',
                            'value' => [
                                'type' => 'rgb',
                                'r' => $declaration['value']['g'],
                                'g' => $declaration['value']['r'],
                                'b' => $declaration['value']['b'],
                                'alpha' => $declaration['value']['alpha'],
                            ],
                        ];
                    },
                ],
            ],
            [
                'Declaration' => [
                    'color' => static function (array $declaration) use (&$seen): array {
                        $seen[] = [
                            'second',
                            $declaration['property'] ?? null,
                            [
                                $declaration['value']['r'] ?? null,
                                $declaration['value']['g'] ?? null,
                                $declaration['value']['b'] ?? null,
                                $declaration['value']['alpha'] ?? null,
                            ],
                        ];

                        if (
                            ($declaration['property'] ?? null) === 'color'
                            && ($declaration['value']['type'] ?? null) === 'rgb'
                            && $declaration['value']['g'] > 0
                        ) {
                            $declaration['value']['alpha'] /= 2;
                        }

                        return $declaration;
                    },
                ],
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { color: red; }', [], $visitor);

        $t->same('.foo{color:#00ff0080}', $result, 'upstream node/test/composeVisitors.test.mjs lines 179-221');
        $t->same([
            ['first', 'color', [255, 0, 0, 1]],
            ['second', 'color', [0, 255, 0, 1]],
        ], $seen);
    },
    'custom at-rules compose residual upstream FunctionExit and Length tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::tokens and functions lines 423-467.
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'FunctionExit' => [
                    'f1' => static fn (): array => [
                        'type' => 'length',
                        'unit' => 'px',
                        'value' => 32,
                    ],
                ],
            ],
            [
                'FunctionExit' => static fn (array $function): ?array => is_array($function['arguments'][0] ?? null)
                    ? $function['arguments'][0]
                    : null,
            ],
            [
                'Length' => static fn (array $length): ?array => $length['unit'] === 'px'
                    ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                    : null,
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform('.foo { width: f3(f2(f1(test))); }', [], $visitor);

        $t->same('.foo{width:2rem}', $result, 'upstream node/test/composeVisitors.test.mjs lines 423-467');
    },
    'custom at-rules compose residual upstream unknown rule token tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::unknown rules lines 469-514.
        $declared = [];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Rule' => [
                    'unknown' => [
                        'test' => static function (array $rule): array {
                            $rule['name'] = 'blue';

                            return ['type' => 'unknown', 'value' => $rule];
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

        $t->same('.menu_link{background:#056ef0}', $result, 'upstream node/test/composeVisitors.test.mjs lines 469-514');
    },
    'custom at-rules compose residual upstream custom rule tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::custom at rules lines 516-595.
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

        $t->same('.testA{color:red}.testB{color:#0f0}', $result, 'upstream node/test/composeVisitors.test.mjs lines 516-595');
    },
    'custom at-rules compose residual upstream known style rule tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::known rules lines 597-671.
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
                            if (str_contains($selector, ':focus-visible')) {
                                $fallbackSelectors[] = str_replace(':focus-visible', '.focus-visible', $selector);
                            }
                        }

                        return $fallbackSelectors === []
                            ? null
                            : [array_replace($rule, ['selectors' => $fallbackSelectors]), $rule];
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

        $t->same(
            '.test.focus-visible{margin-left:20px;margin-right:20px}.test:focus-visible{margin-left:20px;margin-right:20px}',
            $result,
            'upstream node/test/composeVisitors.test.mjs lines 597-671'
        );
    },
    'custom at-rules compose residual upstream environment variable tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::environment variables lines 673-718.
        $tokens = [
            '--branding-small' => ['type' => 'length', 'value' => ['unit' => 'px', 'value' => 600]],
            '--branding-padding' => ['type' => 'length', 'value' => ['unit' => 'px', 'value' => 20]],
        ];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'EnvironmentVariable' => [
                    '--branding-small' => static fn (): array => $tokens['--branding-small'],
                ],
            ],
            [
                'EnvironmentVariable' => [
                    '--branding-padding' => static fn (): array => $tokens['--branding-padding'],
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

        $t->same('@media (width<=600px){body{padding:20px}}', $result, 'upstream node/test/composeVisitors.test.mjs lines 673-718');
    },
    'custom at-rules compose residual upstream variable tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::variables lines 720-768.
        $tokens = [
            '--branding-small' => ['type' => 'length', 'value' => ['unit' => 'px', 'value' => 600]],
            '--branding-padding' => ['type' => 'length', 'value' => ['unit' => 'px', 'value' => 20]],
        ];
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'Variable' => static fn (array $variable): ?array => $variable['name']['ident'] === '--branding-small'
                    ? $tokens['--branding-small']
                    : null,
            ],
            [
                'Variable' => static fn (array $variable): ?array => $variable['name']['ident'] === '--branding-padding'
                    ? $tokens['--branding-padding']
                    : null,
            ],
        ]);

        $result = (new CustomAtRuleTransformer())->transform(
            'body { padding: var(--branding-padding); width: var(--branding-small); }',
            [],
            $visitor
        );

        $t->same('body{padding:20px;width:600px}', $result, 'upstream node/test/composeVisitors.test.mjs lines 720-768');
    },
    'custom at-rules compose residual upstream stylesheet tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::StyleSheet lines 770-801.
        $enterCount = 0;
        $exitCount = 0;
        $visitor = CustomAtRuleTransformer::composeVisitors([
            [
                'StyleSheet' => static function () use (&$enterCount): void {
                    $enterCount++;
                },
                'StyleSheetExit' => static function () use (&$exitCount): void {
                    $exitCount++;
                },
            ],
            [
                'StyleSheet' => static function () use (&$enterCount): void {
                    $enterCount++;
                },
                'StyleSheetExit' => static function () use (&$exitCount): void {
                    $exitCount++;
                },
            ],
        ]);

        (new CustomAtRuleTransformer())->transform('body { color: blue; }', [], $visitor);

        $t->same(2, $enterCount, 'upstream node/test/composeVisitors.test.mjs line 799');
        $t->same(2, $exitCount, 'upstream node/test/composeVisitors.test.mjs line 800');
    },
    'custom at-rules compose residual upstream visitor factory dependency tail row' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d node/test/composeVisitors.test.mjs::visitor function lines 803-856.
        $visitor = CustomAtRuleTransformer::composeVisitors([
            static function (array $context): array {
                $addDependency = $context['addDependency'];

                return [
                    'Rule' => [
                        'unknown' => [
                            'dep' => static function (array $rule) use ($addDependency): array {
                                $addDependency(['type' => 'file', 'filePath' => $rule['preludeTokens'][0]['value']['value']]);

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
                                $addDependency(['type' => 'file', 'filePath' => $rule['preludeTokens'][0]['value']['value']]);

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

        $t->same('.foo{width:32px}', $result['code'], 'upstream node/test/composeVisitors.test.mjs line 854');
        $t->same([
            ['type' => 'file', 'filePath' => 'foo.js'],
            ['type' => 'file', 'filePath' => 'bar.js'],
        ], $result['dependencies'], 'upstream node/test/composeVisitors.test.mjs lines 855-856');
    },
];
