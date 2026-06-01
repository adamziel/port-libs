<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-block card { #056ef0 4px var(--wp-gap) }

.wp-block-card {
  --wp-gap: .5rem;
  color: var(--wp-card-accent);
  padding: var(--wp-card-space);
  gap: var(--wp-card-gap);
}
CSS;

$formatColor = static function (array $color): string {
    if (($color['type'] ?? null) !== 'rgb') {
        return '';
    }

    return sprintf('#%02x%02x%02x', (int) ($color['r'] ?? 0), (int) ($color['g'] ?? 0), (int) ($color['b'] ?? 0));
};

$formatLength = static function (array $length): string {
    return rtrim(rtrim(sprintf('%.10F', (float) ($length['value'] ?? 0)), '0'), '.') . strtolower((string) ($length['unit'] ?? 'px'));
};

$formatVariable = static function (array $variable): string {
    $name = $variable['name']['ident'] ?? '';

    return $name === '' ? '' : 'var(' . $name . ')';
};

$blockSummary = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Rule' => [
        'unknown' => [
            'wp-token-block' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$blockSummary, $formatColor, $formatLength, $formatVariable): array {
                $block = is_array($rule['block'] ?? null) ? $rule['block'] : [];
                $blockSummary = array_column($block, 'type');

                return $transformer->styleRule(':root', [
                    ['property' => '--wp-card-accent', 'value' => $formatColor($block[0]['value'] ?? [])],
                    ['property' => '--wp-card-space', 'value' => $formatLength($block[1]['value'] ?? [])],
                    ['property' => '--wp-card-gap', 'value' => $formatVariable($block[2]['value'] ?? [])],
                ]);
            },
        ],
    ],
]);

$expected = ':root{--wp-card-accent:#056ef0;--wp-card-space:4px;--wp-card-gap:var(--wp-gap)}.wp-block-card{--wp-gap:.5rem;color:var(--wp-card-accent);padding:var(--wp-card-space);gap:var(--wp-card-gap)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected unknown-block token transform output:\n{$result}\n");
        exit(1);
    }
    if ($blockSummary !== ['color', 'length', 'var']) {
        fwrite(STDERR, "Unexpected unknown-block token summary:\n" . json_encode($blockSummary) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
