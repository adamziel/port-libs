<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-block card { #056ef0 4px var(--wp-gap) }

.wp-block-card {
  color: red;
}
CSS;

$seen = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Rule' => [
        'unknown' => [
            'wp-token-block' => static function (array $rule) use (&$seen): array {
                $seen['blockTypes'] = array_map(
                    static fn (array $component): string => $component['type'],
                    is_array($rule['block'] ?? null) ? $rule['block'] : []
                );

                $rule['prelude'] = [
                    ['type' => 'token', 'value' => ['type' => 'ident', 'value' => 'card-live']],
                ];
                $rule['block'] = [
                    ['type' => 'color', 'value' => ['type' => 'rgb', 'r' => 171, 'g' => 205, 'b' => 239, 'alpha' => 1]],
                    ['type' => 'length', 'value' => ['unit' => 'rem', 'value' => 1.5]],
                    ['type' => 'var', 'value' => ['name' => ['ident' => '--wp-scale']]],
                ];
                unset($rule['body']);

                return ['type' => 'unknown', 'value' => $rule];
            },
        ],
    ],
]);

$expected = '@wp-token-block card-live{#abcdef 1.5rem var(--wp-scale)}.wp-block-card{color:red}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected returned unknown-block token output:\n{$result}\n");
        exit(1);
    }
    if (($seen['blockTypes'] ?? []) !== ['color', 'length', 'var']) {
        fwrite(STDERR, "Unexpected returned unknown-block token sequence:\n" . json_encode($seen) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
