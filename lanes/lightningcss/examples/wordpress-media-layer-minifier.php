<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@layer theme;
@layer blocks {}
@layer utilities;

@layer blocks {
  .wp-block-query {
    color: red;
  }
}

@layer blocks {
  .wp-block-query {
    background: #fff;
  }

  @media all {
    .wp-block-query__empty {
      color: chartreuse;
    }
  }

  @media all, all {
    .wp-block-query__always-list {
      color: chartreuse;
    }
  }

  @media not all {
    .wp-block-query__debug {
      color: red;
    }
  }

  @media not all, not all {
    .wp-block-query__dead-list {
      color: red;
    }
  }

  @media all AND (min-width: 600px) AnD ((hover) AND (color)) {
    .wp-block-query {
      color: yellow;
    }
  }

  @media screen AND ((color) Or (hover)) {
    .wp-block-query__screen-feature {
      color: yellow;
    }
  }

  @media (not (color)) and (hover) {
    .wp-block-query__not-color-hover {
      color: yellow;
    }
  }

  @media screen AND ((not (color)) AND (hover)) {
    .wp-block-query__screen-not-color-hover {
      color: yellow;
    }
  }

  @media ((hover) AND ((color) AND (width >= 782px))) {
    .wp-block-query__wrapped-and {
      color: yellow;
    }
  }

  @media ((hover) Or ((color) AND (width >= 782px))) {
    .wp-block-query__wrapped-mixed {
      color: chartreuse;
    }
  }

  @media screen/* migration breakpoint */and (width >= 782px) {
    .wp-block-query__commented-media {
      color: yellow;
    }
  }

  @media only/* legacy qualifier */screen/* wide breakpoint */and (min-width: 960px) {
    .wp-block-query__legacy-commented-media {
      color: chartreuse;
    }
  }

  @media ((width > 480px) AnD (hover)) Or (pointer: coarse) {
    .wp-block-query.is-adaptive {
      color: yellow;
    }
  }

  @media not (((width < 960px))) {
    .wp-block-query.is-wide {
      color: chartreuse;
    }
  }

  @media not (not (width < 720px)) {
    .wp-block-query.is-narrow {
      color: yellow;
    }
  }

  @media (grid: +1) {
    .wp-block-query.is-masonry-capable {
      color: chartreuse;
    }
  }
}
CSS;

$minifier = new CssMinifier();
$actual = $minifier->minify($css);
$expected = '@layer theme;@layer blocks{.wp-block-query{color:red;background:#fff}.wp-block-query__empty{color:#7fff00}.wp-block-query__always-list{color:#7fff00}@media (width>=600px) and (hover) and (color){.wp-block-query{color:#ff0}}@media screen and ((color) or (hover)){.wp-block-query__screen-feature{color:#ff0}}@media (not (color)) and (hover){.wp-block-query__not-color-hover{color:#ff0}}@media screen and (not (color)) and (hover){.wp-block-query__screen-not-color-hover{color:#ff0}}@media (hover) and (color) and (width>=782px){.wp-block-query__wrapped-and{color:#ff0}}@media (hover) or ((color) and (width>=782px)){.wp-block-query__wrapped-mixed{color:#7fff00}}@media screen and (width>=782px){.wp-block-query__commented-media{color:#ff0}}@media only screen and (width>=960px){.wp-block-query__legacy-commented-media{color:#7fff00}}@media ((width>480px) and (hover)) or (pointer:coarse){.wp-block-query.is-adaptive{color:#ff0}}@media (width>=960px){.wp-block-query.is-wide{color:#7fff00}}@media (width<720px){.wp-block-query.is-narrow{color:#ff0}}@media (grid:1){.wp-block-query.is-masonry-capable{color:#7fff00}}}@layer utilities;';

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected media layer minifier output:\n{$actual}\n");
        exit(1);
    }

    try {
        $minifier->minify('@layer blocks { @media (min-width: hi) { .wp-block-query { color: chartreuse; } } }');
        fwrite(STDERR, "Expected invalid layered media query to be rejected.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }

    foreach ([
        '@layer theme, blocks {}',
        '@import "blocks/query-card.css" layer(theme, blocks) {};',
        '@layer blocks { @media screen and (color) or (hover) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media (width > 480px) and (hover) or (pointer) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media (unk\\6e own(foo)) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media (width >= 782px) and (unk\\6e own(foo)) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media ((color) or unknown(foo)) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media (not unknown(foo)) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media (hover) and { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media not (color) and (hover) { .wp-block-query { color: chartreuse; } } }',
        '@layer blocks { @media screen and (hover) and not (color) { .wp-block-query { color: chartreuse; } } }',
    ] as $invalidLayerCss) {
        try {
            $minifier->minify($invalidLayerCss);
            fwrite(STDERR, "Expected invalid layer syntax to be rejected: {$invalidLayerCss}\n");
            exit(1);
        } catch (InvalidArgumentException) {
        }
    }
}

echo $actual . PHP_EOL;
