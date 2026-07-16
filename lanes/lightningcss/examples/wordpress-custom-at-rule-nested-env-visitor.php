<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomAtRuleTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-card__media {
  background-image: linear-gradient(red env(--wp-card-gradient-start), blue env(--wp-card-gradient-end));
  width: calc(env(--wp-card-media-width) - env(--wp-card-gutter));
}
CSS;

$tokens = [
    '--wp-card-gradient-start' => '25%',
    '--wp-card-gradient-end' => '75%',
    '--wp-card-media-width' => '48rem',
    '--wp-card-gutter' => '2rem',
];
$seenNames = [];

$result = (new CustomAtRuleTransformer())->transform($css, [], [
    'EnvironmentVariable' => static function (array $environmentVariable) use (&$seenNames, $tokens): ?array {
        $name = $environmentVariable['name']['ident'] ?? $environmentVariable['name']['value'] ?? '';
        $seenNames[] = $name;

        return isset($tokens[$name]) ? ['raw' => $tokens[$name]] : null;
    },
]);

$expected = '.wp-block-card__media{background-image:linear-gradient(red 25%,#00f 75%);width:46rem}';

if (($argv[1] ?? null) === '--self-test') {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected nested env visitor output:\n{$result}\n");
        exit(1);
    }
    if ($seenNames !== [
        '--wp-card-gradient-start',
        '--wp-card-gradient-end',
        '--wp-card-media-width',
        '--wp-card-gutter',
    ]) {
        fwrite(STDERR, "Unexpected nested env visitor names: " . json_encode($seenNames) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo $result . PHP_EOL;
