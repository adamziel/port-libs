<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token --wp-gap #056ef0;
@wp-layer --wp-slot {
  color: red;
}

.wp-block-card {
  color: blue;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform(
    $css,
    ['wp-layer' => ['prelude' => '*', 'body' => 'style-block']],
    [
        'Rule' => [
            'unknown' => [
                'wp-token' => static function (array $rule) use (&$seen): array {
                    $seen['unknownPreludeTypes'] = array_map(
                        static fn (array $component): string => $component['type'],
                        $rule['preludeTokens']
                    );
                    $rule['prelude'] = [
                        ['type' => 'dashed-ident', 'value' => '--wp-card-gap'],
                        ['type' => 'color', 'value' => ['type' => 'rgb', 'r' => 5, 'g' => 110, 'b' => 240, 'alpha' => 1]],
                        ['type' => 'function', 'value' => ['name' => 'var', 'arguments' => [
                            ['type' => 'ident', 'value' => '--wp-scale'],
                        ]]],
                    ];

                    return ['type' => 'unknown', 'value' => $rule];
                },
            ],
            'custom' => [
                'wp-layer' => static function (array $rule) use (&$seen): array {
                    $seen['customPreludeTypes'] = array_map(
                        static fn (array $component): string => $component['type'],
                        is_array($rule['preludeAst']['value'] ?? null) ? $rule['preludeAst']['value'] : []
                    );
                    $rule['prelude'] = [
                        ['type' => 'dashed-ident', 'value' => '--wp-slot-live'],
                    ];

                    return ['type' => 'custom', 'value' => $rule];
                },
            ],
        ],
    ]
);

$expected = '@wp-token --wp-card-gap #056ef0 var(--wp-scale);@wp-layer --wp-slot-live{color:red}.wp-block-card{color:#00f}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule token-list output:\n{$result}\n");
        exit(1);
    }
    if (($seen['unknownPreludeTypes'] ?? []) !== ['dashed-ident', 'color']) {
        fwrite(STDERR, "Unexpected unknown prelude token sequence:\n" . json_encode($seen) . "\n");
        exit(1);
    }
    if (($seen['customPreludeTypes'] ?? []) !== ['dashed-ident']) {
        fwrite(STDERR, "Unexpected custom prelude token sequence:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
