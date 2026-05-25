<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/MediaQueryParser.php';
require_once __DIR__ . '/../src/CssMinifier.php';
require_once __DIR__ . '/../src/TransitionPrefixer.php';

use PortLibs\LightningCSS\TransitionPrefixer;

$css = <<<'CSS'
.wp-block-group.is-style-card {
  border-radius: 10px 100px 10px 100px / 120px 120px;
}

.wp-block-image.is-style-rounded img {
  -webkit-border-radius: 0px 10px 0px 10px;
  border-radius: 0px 10px 0px 10px;
}

.wp-block-cover.is-style-rounded-corners {
  border-top-left-radius: 16px 8px;
  border-top-right-radius: 24px 12px;
  border-bottom-right-radius: 16px 8px;
  border-bottom-left-radius: 24px 12px;
}

.wp-block-button.is-style-pill > .wp-block-button__link {
  border-start-start-radius: 2px;
  border-radius: 999px;
}
CSS;

$expected = '.wp-block-group.is-style-card{-webkit-border-radius:10px 100px/120px;border-radius:10px 100px/120px}.wp-block-image.is-style-rounded img{-webkit-border-radius:0 10px;border-radius:0 10px}.wp-block-cover.is-style-rounded-corners{-webkit-border-radius:16px 24px/8px 12px;border-radius:16px 24px/8px 12px}.wp-block-button.is-style-pill>.wp-block-button__link{-webkit-border-radius:999px;border-radius:999px}';
$actual = (new TransitionPrefixer())->prefixForTargets($css, ['chrome' => 4]);

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected minified CSS:\n{$actual}\n");
    exit(1);
}

echo $actual . PHP_EOL;
