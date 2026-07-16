<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();
$css = <<<'CSS'
.wp-block-cover.is-style-framed {
  border-image: url("/wp-content/themes/acme/assets/frame.png") 30 fill / 12px / 4px round;
}

@supports (border-image: url("/wp-content/themes/acme/assets/frame.png") 30 fill / 12px / 4px round) {
  .wp-block-cover.is-style-framed {
    border-image: url("/wp-content/themes/acme/assets/frame.png") 30 fill / 12px / 4px round;
  }
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'chrome' => 14,
        'firefox' => 14,
        'opera' => '12.1',
        'safari' => '5.1',
    ]),
    'modern_frontend' => $prefixer->prefixForTargets($css, [
        'chrome' => 15,
        'firefox' => 15,
        'opera' => '12.2',
        'safari' => 6,
    ]),
];

$expected = [
    'legacy_editor' => '.wp-block-cover.is-style-framed{-webkit-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;-moz-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;-o-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round}@supports ((-webkit-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round) or (-moz-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round) or (-o-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round) or (border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round)){.wp-block-cover.is-style-framed{-webkit-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;-moz-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;-o-border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round;border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round}}',
    'modern_frontend' => '.wp-block-cover.is-style-framed{border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round}@supports (border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round){.wp-block-cover.is-style-framed{border-image:url("/wp-content/themes/acme/assets/frame.png") 30 fill/12px/4px round}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected border-image target-prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
