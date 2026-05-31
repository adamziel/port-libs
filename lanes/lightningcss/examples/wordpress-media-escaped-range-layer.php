<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme.blocks {
  @media scr\65 en and (w\69 dth >= 240px) {
    .wp-block-query.is-escaped-wide {
      color: yellow;
    }
  }

  @media \6f nly scr\65 en a\6e d (w\69 dth >= 240px) {
    .wp-block-query.is-escaped-only-wide {
      color: yellow;
    }
  }

  @media (100px <= w\69 dth <= 200px) {
    .wp-block-query.is-escaped-compact {
      color: yellow;
    }
  }

  @media (hover) o\72 (100px <= w\69 dth <= 200px) {
    .wp-block-query.is-escaped-compact-hover {
      color: yellow;
    }
  }

  @media (theme\2d breakpoint >= 2) {
    .wp-block-query.is-escaped-breakpoint {
      color: yellow;
    }
  }

  @media (theme\2d state = exp\61 nded) {
    .wp-block-query.is-escaped-expanded {
      color: chartreuse;
    }
  }
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy' => $prefixer->prefixForTargets($css, ['firefox' => 60]),
    'modern' => $prefixer->prefixForTargets($css, ['firefox' => 64]),
];

$expected = [
    'legacy' => '@layer theme.blocks{@media screen and (min-width:240px){.wp-block-query.is-escaped-wide{color:#ff0}}@media only screen and (min-width:240px){.wp-block-query.is-escaped-only-wide{color:#ff0}}@media (min-width:100px) and (max-width:200px){.wp-block-query.is-escaped-compact{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-escaped-compact-hover{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-escaped-breakpoint{color:#ff0}}@media (theme-state:expanded){.wp-block-query.is-escaped-expanded{color:#7fff00}}}',
    'modern' => '@layer theme.blocks{@media screen and (width>=240px){.wp-block-query.is-escaped-wide{color:#ff0}}@media only screen and (width>=240px){.wp-block-query.is-escaped-only-wide{color:#ff0}}@media (min-width:100px) and (max-width:200px){.wp-block-query.is-escaped-compact{color:#ff0}}@media (hover) or ((min-width:100px) and (max-width:200px)){.wp-block-query.is-escaped-compact-hover{color:#ff0}}@media (theme-breakpoint>=2){.wp-block-query.is-escaped-breakpoint{color:#ff0}}@media (theme-state=expanded){.wp-block-query.is-escaped-expanded{color:#7fff00}}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected escaped media range layer output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
