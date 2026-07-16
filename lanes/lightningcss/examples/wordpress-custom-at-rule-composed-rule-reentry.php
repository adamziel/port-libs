<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tokens card;
CSS;

$events = [];
$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'custom' => [
                'tokens' => static function (array $rule) use (&$events): array {
                    $events[] = 'custom:' . $rule['prelude'];

                    return [
                        [
                            'type' => 'style',
                            'value' => [
                                'selectors' => [
                                    [
                                        ['type' => 'class', 'name' => 'wp-block-' . $rule['prelude']],
                                    ],
                                ],
                                'declarations' => [
                                    'declarations' => [[
                                        'property' => 'color',
                                        'value' => [
                                            'type' => 'rgb',
                                            'r' => 255,
                                            'g' => 255,
                                            'b' => 0,
                                            'alpha' => 1,
                                        ],
                                    ]],
                                    'importantDeclarations' => [],
                                ],
                            ],
                        ],
                        [
                            'type' => 'media',
                            'value' => [
                                'query' => [
                                    'mediaQueries' => [
                                        ['raw' => '(min-width: 40rem)'],
                                    ],
                                ],
                                'rules' => [[
                                    'type' => 'style',
                                    'value' => [
                                        'selectors' => [
                                            [
                                                ['type' => 'class', 'name' => 'wp-block-' . $rule['prelude'] . '__media'],
                                            ],
                                        ],
                                        'declarations' => [
                                            'declarations' => [[
                                                'property' => 'color',
                                                'value' => [
                                                    'type' => 'rgb',
                                                    'r' => 255,
                                                    'g' => 0,
                                                    'b' => 0,
                                                    'alpha' => 1,
                                                ],
                                            ]],
                                            'importantDeclarations' => [],
                                        ],
                                    ],
                                ]],
                            ],
                        ],
                    ];
                },
            ],
        ],
    ],
    [
        'Rule' => [
            'style' => static function (array $rule) use (&$events): array {
                $events[] = 'style:' . ($rule['value']['selectors'][0][0]['name'] ?? '');
                $rule['value']['declarations']['declarations'][] = [
                    'property' => 'outline-color',
                    'value' => [
                        'type' => 'rgb',
                        'r' => 5,
                        'g' => 110,
                        'b' => 240,
                        'alpha' => 1,
                    ],
                ];

                return $rule;
            },
        ],
    ],
    [
        'Rule' => [
            'media' => static function (array $rule) use (&$events): array {
                $events[] = 'media:' . ($rule['value']['query']['mediaQueries'][0]['raw'] ?? '');
                $rule['value']['query']['mediaQueries'][0]['raw'] = '(min-width: 48rem)';

                return $rule;
            },
        ],
    ],
]);

$result = (new CustomAtRuleTransformer())->transform($css, [
    'tokens' => [
        'prelude' => '<custom-ident>',
    ],
], $visitor);

if (($argv[1] ?? null) === '--self-test') {
    $expected = '.wp-block-card{color:#ff0;outline-color:#056ef0}@media (width>=48rem){.wp-block-card__media{color:red}}';
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected CSS output:\n{$result}\n");
        exit(1);
    }
    if ($events !== ['custom:card', 'style:wp-block-card', 'media:(min-width: 40rem)']) {
        fwrite(STDERR, "Composed Rule visitors did not re-enter returned style/media rules.\n");
        exit(1);
    }
}

echo $result . PHP_EOL;
