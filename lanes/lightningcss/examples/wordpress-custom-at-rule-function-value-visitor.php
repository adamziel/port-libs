<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card {
  width: theme("card-space");
  color: theme("accent");
}
CSS;

$seen = [
    'functions' => [],
    'lengths' => [],
    'colors' => [],
];

$visitor = CustomAtRuleTransformer::composeVisitors([
    [
        'Function' => [
            'theme' => static function (array $arguments, string $raw, string $name) use (&$seen): ?array {
                $seen['functions'][] = [$name, $arguments[0] ?? null, $raw];

                return match ($arguments[0] ?? null) {
                    'card-space' => [
                        'type' => 'length',
                        'unit' => 'px',
                        'value' => 32,
                    ],
                    'accent' => [
                        'type' => 'color',
                        'value' => [
                            'type' => 'rgb',
                            'r' => 255,
                            'g' => 0,
                            'b' => 0,
                            'alpha' => 1,
                        ],
                    ],
                    default => null,
                };
            },
        ],
    ],
    [
        'Length' => static function (array $length) use (&$seen): ?array {
            $seen['lengths'][] = $length;

            return $length['unit'] === 'px'
                ? ['unit' => 'rem', 'value' => $length['value'] / 16]
                : null;
        },
        'Color' => static function (array $color) use (&$seen): ?array {
            $seen['colors'][] = $color;
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

$result = (new CustomAtRuleTransformer())->transform($css, [], $visitor);
$expected = '.wp-block-card{width:2rem;color:#0f0}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected custom function visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seen['functions'] !== [
        ['theme', 'card-space', 'theme("card-space")'],
        ['theme', 'accent', 'theme("accent")'],
    ]) {
        fwrite(STDERR, "Unexpected Function visitor sequence:\n" . json_encode($seen['functions']) . "\n");
        exit(1);
    }
    if ($seen['lengths'] !== [['unit' => 'px', 'value' => 32]]) {
        fwrite(STDERR, "Unexpected Length visitor sequence:\n" . json_encode($seen['lengths']) . "\n");
        exit(1);
    }
    if ($seen['colors'] !== [['type' => 'rgb', 'r' => 255, 'g' => 0, 'b' => 0, 'alpha' => 1]]) {
        fwrite(STDERR, "Unexpected Color visitor sequence:\n" . json_encode($seen['colors']) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
