<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media (width > calc(1px + 1rem)) {
    .wp-block-query.is-fluid-gap {
      color: yellow;
    }
  }

  @media (width >= calc((2px + 4px))) {
    .wp-block-query.is-nested-calc {
      color: yellow;
    }
  }

  @media (aspect-ratio >= max(1 / 2, 1 / 3)) {
    .wp-block-query.is-ratio-window {
      color: yellow;
    }
  }

  @media (aspect-ratio >= calc((1 / 2))) {
    .wp-block-query.is-ratio-calc {
      color: yellow;
    }
  }

  @media (100px < width < calc(100vw - 50px)) {
    .wp-block-query.is-fluid-window {
      color: chartreuse;
    }
  }

  @media (calc((2px + 4px)) <= width <= calc((10px + 2px))) {
    .wp-block-query.is-nested-window {
      color: chartreuse;
    }
  }

  @media (round(22px, 5px) <= width <= round(up, 22px, 5px)) {
    .wp-block-query.is-rounded-window {
      color: yellow;
    }
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'chrome85' => $prefixer->prefixForTargets($css, ['chrome' => 85]),
    'firefox64' => $prefixer->prefixForTargets($css, ['firefox' => 64]),
    'firefox85' => $prefixer->prefixForTargets($css, ['firefox' => 85]),
];

$expected = [
    'chrome85' => '@layer theme.blocks{@media not (max-width:calc(1px + 1rem)){.wp-block-query.is-fluid-gap{color:#ff0}}@media (min-width:6px){.wp-block-query.is-nested-calc{color:#ff0}}@media (min-aspect-ratio:.5){.wp-block-query.is-ratio-window{color:#ff0}.wp-block-query.is-ratio-calc{color:#ff0}}@media (not (max-width:100px)) and (not (min-width:calc(100vw - 50px))){.wp-block-query.is-fluid-window{color:#7fff00}}@media (min-width:6px) and (max-width:12px){.wp-block-query.is-nested-window{color:#7fff00}}@media (min-width:20px) and (max-width:25px){.wp-block-query.is-rounded-window{color:#ff0}}}',
    'firefox64' => '@layer theme.blocks{@media (width>calc(1px + 1rem)){.wp-block-query.is-fluid-gap{color:#ff0}}@media (width>=6px){.wp-block-query.is-nested-calc{color:#ff0}}@media (aspect-ratio>=.5){.wp-block-query.is-ratio-window{color:#ff0}.wp-block-query.is-ratio-calc{color:#ff0}}@media (not (max-width:100px)) and (not (min-width:calc(100vw - 50px))){.wp-block-query.is-fluid-window{color:#7fff00}}@media (min-width:6px) and (max-width:12px){.wp-block-query.is-nested-window{color:#7fff00}}@media (min-width:20px) and (max-width:25px){.wp-block-query.is-rounded-window{color:#ff0}}}',
    'firefox85' => '@layer theme.blocks{@media (width>calc(1px + 1rem)){.wp-block-query.is-fluid-gap{color:#ff0}}@media (width>=6px){.wp-block-query.is-nested-calc{color:#ff0}}@media (aspect-ratio>=.5){.wp-block-query.is-ratio-window{color:#ff0}.wp-block-query.is-ratio-calc{color:#ff0}}@media (not (max-width:100px)) and (not (min-width:calc(100vw - 50px))){.wp-block-query.is-fluid-window{color:#7fff00}}@media (min-width:6px) and (max-width:12px){.wp-block-query.is-nested-window{color:#7fff00}}@media (min-width:20px) and (max-width:25px){.wp-block-query.is-rounded-window{color:#ff0}}}',
];

if (($argv[1] ?? null) === '--self-test' && $actual !== $expected) {
    fwrite(STDERR, "Unexpected media calc range layer prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
