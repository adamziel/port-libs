<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-list theme("card-gap") var(--wp-gap) env(--wp-breakpoint) 2--wp-fluid-step @--wp-accent;

.wp-block-card {
  color: red;
}
CSS;

$seenPreludeAst = null;
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'wp-token-list' => [
        'prelude' => '*',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Function' => [
            'theme' => static fn (array $arguments): string => ($arguments[0] ?? '') === 'card-gap' ? '24px' : '0px',
        ],
        'Variable' => [
            '--wp-gap' => static fn (array $variable): array => [
                'unit' => 'rem',
                'value' => 1.0,
            ],
        ],
        'EnvironmentVariable' => [
            '--wp-breakpoint' => static fn (array $environmentVariable): array => [
                'type' => 'length',
                'unit' => 'px',
                'value' => 782.0,
            ],
        ],
        'Token' => [
            'dimension' => static fn (array $token): array => [
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
            ],
            'at-keyword' => static fn (array $token): array => [
                'type' => 'color',
                'value' => [
                    'type' => 'rgb',
                    'r' => 5,
                    'g' => 110,
                    'b' => 240,
                    'alpha' => 1,
                ],
            ],
        ],
    ],
    [
        'Rule' => [
            'custom' => [
                'wp-token-list' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seenPreludeAst): array {
                    $seenPreludeAst = $rule['preludeAst'];

                    return $transformer->styleRule(':root', [
                        [
                            'property' => '--wp-token-list',
                            'value' => $rule['prelude'],
                            'important' => false,
                        ],
                    ]);
                },
            ],
        ],
    ],
]));

$expected = ':root{--wp-token-list:24px 1rem 782px calc(2*var(--wp-fluid-step)) #056ef0}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom token-list prelude output:\n{$result}\n");
        exit(1);
    }
    if (($seenPreludeAst['type'] ?? null) !== 'token-list') {
        fwrite(STDERR, "Unexpected prelude AST type:\n" . json_encode($seenPreludeAst) . "\n");
        exit(1);
    }
    $componentTypes = array_map(
        static fn (array $component): string => (string) ($component['type'] ?? ''),
        $seenPreludeAst['value'] ?? []
    );
    if ($componentTypes !== ['length', 'length', 'length', 'function', 'color']) {
        fwrite(STDERR, "Unexpected token-list component types:\n" . json_encode($componentTypes) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
