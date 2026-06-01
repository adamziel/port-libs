<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-list theme("card-gap"), var(--wp-gap), @--wp-accent, env(--wp-breakpoint);

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
            'theme' => static fn (array $arguments): string => ($arguments[0] ?? '') === 'card-gap' ? '16px' : '0px',
        ],
        'Variable' => [
            '--wp-gap' => static fn (array $variable): array => [
                'unit' => 'px',
                'value' => 24.0,
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

$expected = ':root{--wp-token-list:16px,24px,#056ef0,782px}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom comma token-list prelude output:\n{$result}\n");
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
    if ($componentTypes !== ['length', 'token', 'length', 'token', 'color', 'token', 'length']) {
        fwrite(STDERR, "Unexpected token-list component types:\n" . json_encode($componentTypes) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
