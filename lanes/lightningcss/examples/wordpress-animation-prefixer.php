<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-cover.is-style-reveal {
  -webkit-animation: 200ms var(--wp--custom--ease) wp-cover-reveal;
  -moz-animation: 200ms var(--wp--custom--ease) wp-cover-reveal;
  animation: 200ms var(--wp--custom--ease) wp-cover-reveal scroll();
}

.wp-block-query .wp-block-post {
  animation-name: wp-post-enter;
  animation-duration: 200ms;
}

@supports (animation: 200ms wp-post-enter) {
  .wp-block-query .wp-block-post {
    animation: 200ms wp-post-enter;
  }
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, ['firefox' => 6, 'safari' => 6]),
    'opera_12' => $prefixer->prefixForTargets($css, ['opera' => 12]),
    'modern_frontend' => $prefixer->prefixForTargets($css, ['firefox' => 20, 'safari' => 14]),
];

$expected = [
    'legacy_editor' => '.wp-block-cover.is-style-reveal{-webkit-animation:.2s var(--wp--custom--ease) wp-cover-reveal;-moz-animation:.2s var(--wp--custom--ease) wp-cover-reveal;animation:.2s var(--wp--custom--ease) wp-cover-reveal;animation-timeline:scroll()}.wp-block-query .wp-block-post{-webkit-animation-name:wp-post-enter;-moz-animation-name:wp-post-enter;animation-name:wp-post-enter;-webkit-animation-duration:.2s;-moz-animation-duration:.2s;animation-duration:.2s}@supports ((-webkit-animation:200ms wp-post-enter) or (-moz-animation:200ms wp-post-enter) or (animation:200ms wp-post-enter)){.wp-block-query .wp-block-post{-webkit-animation:.2s wp-post-enter;-moz-animation:.2s wp-post-enter;animation:.2s wp-post-enter}}',
    'opera_12' => '.wp-block-cover.is-style-reveal{-o-animation:.2s var(--wp--custom--ease) wp-cover-reveal;animation:.2s var(--wp--custom--ease) wp-cover-reveal;animation-timeline:scroll()}.wp-block-query .wp-block-post{-o-animation-name:wp-post-enter;animation-name:wp-post-enter;-o-animation-duration:.2s;animation-duration:.2s}@supports ((-o-animation:200ms wp-post-enter) or (animation:200ms wp-post-enter)){.wp-block-query .wp-block-post{-o-animation:.2s wp-post-enter;animation:.2s wp-post-enter}}',
    'modern_frontend' => '.wp-block-cover.is-style-reveal{animation:.2s var(--wp--custom--ease) wp-cover-reveal;animation-timeline:scroll()}.wp-block-query .wp-block-post{animation-name:wp-post-enter;animation-duration:.2s}@supports (animation:200ms wp-post-enter){.wp-block-query .wp-block-post{animation:.2s wp-post-enter}}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected animation prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
