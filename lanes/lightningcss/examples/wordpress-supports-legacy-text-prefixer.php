<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
@supports (tab-size: 4) {
  .wp-block-code {
    tab-size: 4;
  }
}

@supports (text-align-last: center) {
  .wp-block-quote {
    text-align-last: center;
  }
}

@supports (text-decoration-skip-ink: all) {
  .wp-block-post-title a {
    text-decoration-skip-ink: all;
  }
}

@supports (box-decoration-break: clone) {
  .wp-block-heading mark {
    box-decoration-break: clone;
  }
}
CSS;

$actual = [
    'firefox48' => $prefixer->prefixForTargets($css, ['firefox' => 48]),
    'firefox91' => $prefixer->prefixForTargets($css, ['firefox' => 91]),
    'safari12' => $prefixer->prefixForTargets($css, ['safari' => 12]),
    'chrome130' => $prefixer->prefixForTargets($css, ['chrome' => 130]),
];

$expected = [
    'firefox48' => '@supports ((-moz-tab-size:4) or (tab-size:4)){.wp-block-code{-moz-tab-size:4;tab-size:4}}@supports ((-moz-text-align-last:center) or (text-align-last:center)){.wp-block-quote{-moz-text-align-last:center;text-align-last:center}}@supports (text-decoration-skip-ink:all){.wp-block-post-title a{text-decoration-skip-ink:all}}@supports (box-decoration-break:clone){.wp-block-heading mark{box-decoration-break:clone}}',
    'firefox91' => '@supports (tab-size:4){.wp-block-code{tab-size:4}}@supports (text-align-last:center){.wp-block-quote{text-align-last:center}}@supports (text-decoration-skip-ink:all){.wp-block-post-title a{text-decoration-skip-ink:all}}@supports (box-decoration-break:clone){.wp-block-heading mark{box-decoration-break:clone}}',
    'safari12' => '@supports (tab-size:4){.wp-block-code{tab-size:4}}@supports (text-align-last:center){.wp-block-quote{text-align-last:center}}@supports ((-webkit-text-decoration-skip-ink:all) or (text-decoration-skip-ink:all)){.wp-block-post-title a{-webkit-text-decoration-skip-ink:all;text-decoration-skip-ink:all}}@supports ((-webkit-box-decoration-break:clone) or (box-decoration-break:clone)){.wp-block-heading mark{-webkit-box-decoration-break:clone;box-decoration-break:clone}}',
    'chrome130' => '@supports (tab-size:4){.wp-block-code{tab-size:4}}@supports (text-align-last:center){.wp-block-quote{text-align-last:center}}@supports (text-decoration-skip-ink:all){.wp-block-post-title a{text-decoration-skip-ink:all}}@supports (box-decoration-break:clone){.wp-block-heading mark{box-decoration-break:clone}}',
];

if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected legacy text supports prefix output:\n" . var_export($actual, true) . "\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
