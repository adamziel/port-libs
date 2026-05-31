<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@motion fade {
  from { opacity: 0 }
  to { opacity: 1 }
}

.wp-block-card {
  --accent: #056ef0;
  color: var(--accent);
  animation: fade;
}
CSS;

$transformer = new CustomAtRuleTransformer();
$seenDashed = [];
$seenCustom = [];
$result = $transformer->transform($css, [
    'motion' => [
        'prelude' => '<custom-ident>',
        'body' => 'rule-list',
    ],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Rule' => [
            'custom' => [
                'motion' => static function (array $rule): array {
                    return [
                        'type' => 'unknown',
                        'value' => [
                            'name' => 'keyframes',
                            'prelude' => $rule['prelude'],
                            'body' => $rule['body'],
                            'hasBlock' => true,
                        ],
                    ];
                },
            ],
        ],
    ],
    [
        'DashedIdent' => static function (string $ident) use (&$seenDashed): string {
            $seenDashed[] = $ident;

            return '--wp-' . substr($ident, 2);
        },
        'CustomIdent' => static function (string $ident) use (&$seenCustom): string {
            $seenCustom[] = $ident;

            return 'wp-' . $ident;
        },
    ],
]));

$expected = '@keyframes wp-fade{0%{opacity:0}to{opacity:1}}.wp-block-card{--wp-accent:#056ef0;color:var(--wp-accent);animation:wp-fade}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected identifier visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seenDashed !== ['--accent', '--accent']) {
        fwrite(STDERR, "Unexpected dashed identifiers: " . json_encode($seenDashed) . "\n");
        exit(1);
    }
    if ($seenCustom !== ['fade', 'fade']) {
        fwrite(STDERR, "Unexpected custom identifiers: " . json_encode($seenCustom) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
