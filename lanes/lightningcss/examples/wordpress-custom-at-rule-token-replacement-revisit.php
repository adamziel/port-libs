<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-fluid-token 3--wp-fluid-step;

.wp-block-card {
  color: red;
}
CSS;

$seenPreludeAst = null;
$events = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'wp-fluid-token' => [
        'prelude' => '*',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Token' => [
            'dimension' => static function (array $token) use (&$events): array {
                $events[] = 'token:' . $token['raw'];

                return [
                    'type' => 'function',
                    'value' => [
                        'name' => 'fluid',
                        'arguments' => [
                            [
                                'type' => 'token',
                                'value' => [
                                    'type' => 'number',
                                    'value' => $token['value'],
                                ],
                            ],
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
        ],
    ],
    [
        'Variable' => [
            '--wp-fluid-step' => static function (array $variable) use (&$events): array {
                $events[] = 'variable:' . ($variable['name']['ident'] ?? '');

                return [
                    'unit' => 'rem',
                    'value' => 0.25,
                ];
            },
        ],
        'FunctionExit' => [
            'fluid' => static function (array $function) use (&$events): array {
                $argument = $function['arguments'][1] ?? [];
                $events[] = 'function-exit:' . ($function['name'] ?? '') . ':' . ($argument['type'] ?? '') . ':' . ($argument['unit'] ?? '');

                return [
                    'unit' => 'rem',
                    'value' => 2.0,
                ];
            },
        ],
    ],
    [
        'Rule' => [
            'custom' => [
                'wp-fluid-token' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seenPreludeAst): array {
                    $seenPreludeAst = $rule['preludeAst'];

                    return $transformer->styleRule(':root', [
                        [
                            'property' => '--wp-fluid-token',
                            'value' => $rule['prelude'],
                            'important' => false,
                        ],
                    ]);
                },
            ],
        ],
    ],
]));

$expected = ':root{--wp-fluid-token:2rem}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom token replacement output:\n{$result}\n");
        exit(1);
    }
    if ($events !== [
        'token:3--wp-fluid-step',
        'variable:--wp-fluid-step',
        'function-exit:fluid:length:rem',
    ]) {
        fwrite(STDERR, "Unexpected replacement visitor events:\n" . json_encode($events) . "\n");
        exit(1);
    }
    if (($seenPreludeAst['type'] ?? null) !== 'token-list') {
        fwrite(STDERR, "Unexpected prelude AST type:\n" . json_encode($seenPreludeAst) . "\n");
        exit(1);
    }
    if (($seenPreludeAst['value'][0]['type'] ?? null) !== 'length') {
        fwrite(STDERR, "Unexpected prelude component:\n" . json_encode($seenPreludeAst) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
