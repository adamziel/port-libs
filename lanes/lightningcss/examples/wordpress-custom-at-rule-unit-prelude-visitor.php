<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@tilt 90deg;
@delay 250ms;
@density 2dppx;

.wp-block-card {
  animation-duration: var(--wp-card-delay);
  transform: rotate(var(--wp-card-tilt));
}
CSS;

$seen = [];
$transformer = new CustomAtRuleTransformer();
$result = $transformer->transform($css, [
    'tilt' => ['prelude' => '<angle>'],
    'delay' => ['prelude' => '<time>'],
    'density' => ['prelude' => '<resolution>'],
], CustomAtRuleTransformer::composeVisitors([
    [
        'Angle' => static fn (array $angle): array => [
            'type' => $angle['type'],
            'value' => $angle['value'] / 2,
        ],
        'Time' => static fn (array $time): array => [
            'type' => 'milliseconds',
            'value' => $time['type'] === 'milliseconds' ? $time['value'] * 2 : $time['value'],
        ],
    ],
    [
        'Resolution' => static fn (array $resolution): array => [
            'type' => 'dppx',
            'value' => $resolution['value'] * 2,
        ],
        'Rule' => [
            'custom' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$seen): array {
                $seen[$rule['name']] = $rule['preludeAst'];

                return $transformer->styleRule(':root', [
                    '--wp-card-' . $rule['name'] => $rule['prelude'],
                ]);
            },
        ],
    ],
]));

$expected = ':root{--wp-card-tilt:45deg;--wp-card-delay:500ms;--wp-card-density:4dppx}.wp-block-card{animation-duration:var(--wp-card-delay);transform:rotate(var(--wp-card-tilt))}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom at-rule unit prelude visitor output:\n{$result}\n");
        exit(1);
    }
    if (($seen['tilt']['value']['value'] ?? null) !== 45.0) {
        fwrite(STDERR, "Unexpected angle prelude AST:\n" . json_encode($seen['tilt']) . "\n");
        exit(1);
    }
    if (($seen['delay']['value']['value'] ?? null) !== 500.0) {
        fwrite(STDERR, "Unexpected time prelude AST:\n" . json_encode($seen['delay']) . "\n");
        exit(1);
    }
    if (($seen['density']['value']['value'] ?? null) !== 4.0) {
        fwrite(STDERR, "Unexpected resolution prelude AST:\n" . json_encode($seen['density']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
