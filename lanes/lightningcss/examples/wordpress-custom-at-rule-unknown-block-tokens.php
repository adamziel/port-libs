<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@wp-token-block card { #056ef0 4px draft var(--wp-gap, 8px draft) }

.wp-block-card {
  --wp-gap: .5rem;
  color: var(--wp-card-accent);
  padding: var(--wp-card-space);
  border-style: var(--wp-card-state);
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

$formatToken = static function (array $component) use ($formatLength): string {
    if (($component['type'] ?? null) === 'length') {
        return $formatLength($component['value'] ?? []);
    }
    if (($component['type'] ?? null) === 'token') {
        $token = $component['value'] ?? [];

        return is_string($token['value'] ?? null) ? $token['value'] : '';
    }

    return '';
};

$formatVariable = static function (array $variable) use ($formatToken): string {
    $name = $variable['name']['ident'] ?? '';
    $fallback = $variable['fallback'] ?? null;
    if (is_array($fallback) && $fallback !== []) {
        $fallbackCss = implode(' ', array_filter(array_map($formatToken, $fallback), static fn (string $value): bool => $value !== ''));

        return $name === '' ? '' : 'var(' . $name . ',' . $fallbackCss . ')';
    }

    return $name === '' ? '' : 'var(' . $name . ')';
};

$blockSummary = [];
$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'Length' => static fn (array $length): ?array => ($length['unit'] ?? null) === 'px'
        ? ['unit' => 'rem', 'value' => $length['value'] / 16]
        : null,
    'Token' => [
        'ident' => static fn (array $token): ?string => ($token['value'] ?? null) === 'draft' ? 'live' : null,
    ],
    'Rule' => [
        'unknown' => [
            'wp-token-block' => static function (array $rule, CustomAtRuleTransformer $transformer) use (&$blockSummary, $formatColor, $formatLength, $formatToken, $formatVariable): array {
                $block = is_array($rule['block'] ?? null) ? $rule['block'] : [];
                $blockSummary = array_column($block, 'type');

                return $transformer->styleRule(':root', [
                    ['property' => '--wp-card-accent', 'value' => $formatColor($block[0]['value'] ?? [])],
                    ['property' => '--wp-card-space', 'value' => $formatLength($block[1]['value'] ?? [])],
                    ['property' => '--wp-card-state', 'value' => $formatToken($block[2] ?? [])],
                    ['property' => '--wp-card-gap', 'value' => $formatVariable($block[3]['value'] ?? [])],
                ]);
            },
        ],
    ],
]);

$expected = ':root{--wp-card-accent:#056ef0;--wp-card-space:0.25rem;--wp-card-state:live;--wp-card-gap:var(--wp-gap,0.5rem live)}.wp-block-card{--wp-gap:.5rem;color:var(--wp-card-accent);padding:var(--wp-card-space);border-style:var(--wp-card-state);gap:var(--wp-card-gap)}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected unknown-block token transform output:\n{$result}\n");
        exit(1);
    }
    if ($blockSummary !== ['color', 'length', 'token', 'var']) {
        fwrite(STDERR, "Unexpected unknown-block token summary:\n" . json_encode($blockSummary) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
