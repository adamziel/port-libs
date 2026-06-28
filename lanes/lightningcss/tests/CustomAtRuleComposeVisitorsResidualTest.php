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
];
