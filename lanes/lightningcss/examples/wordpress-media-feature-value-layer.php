<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (prefers-color-scheme: env(--wp--custom--color-scheme)) and (width: 782px) {
    .wp-block-query {
      color: yellow;
    }
  }

  @media (theme-state: expanded) and (color: 2) {
    .wp-block-query.is-strong-color {
      color: chartreuse;
    }
  }
}
CSS;

$minifier = new CssMinifier();
$actual = [
    'minified' => $minifier->minify($css),
];

foreach ([
    'invalidIdentNumber' => '@layer theme.blocks { @media (hover: 1) { .wp-block-query { color: yellow; } } }',
    'invalidTypedVar' => '@layer theme.blocks { @media (width: var(--wp--custom--query-breakpoint)) { .wp-block-query { color: yellow; } } }',
    'invalidUnknownColor' => '@layer theme.blocks { @media (theme-state: #fff) { .wp-block-query { color: yellow; } } }',
] as $name => $invalidCss) {
    try {
        $minifier->minify($invalidCss);
        $actual[$name] = 'accepted';
    } catch (InvalidArgumentException) {
        $actual[$name] = 'rejected';
    }
}

$expected = [
    'minified' => '@layer theme.blocks{@media (prefers-color-scheme:env(--wp--custom--color-scheme)) and (width:782px){.wp-block-query{color:#ff0}}@media (theme-state:expanded) and (color:2){.wp-block-query.is-strong-color{color:#7fff00}}}',
    'invalidIdentNumber' => 'rejected',
    'invalidTypedVar' => 'rejected',
    'invalidUnknownColor' => 'rejected',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected media feature value layer output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo json_encode($actual, JSON_PRETTY_PRINT) . PHP_EOL;
