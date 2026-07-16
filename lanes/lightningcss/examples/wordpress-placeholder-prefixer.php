<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-search__input::placeholder {
  color: var(--wp--preset--color--contrast);
  opacity: .72;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'legacy_controls' => $prefixer->prefixForTargets($css, ['chrome' => 45, 'firefox' => 45, 'ie' => 11]),
    'modern_controls' => $prefixer->prefixForTargets($css, ['chrome' => 57, 'firefox' => 51, 'edge' => 19, 'safari' => 11]),
];

$expected = [
    'legacy_controls' => '.wp-block-search__input::-webkit-input-placeholder{color:var(--wp--preset--color--contrast);opacity:.72}.wp-block-search__input::-moz-placeholder{color:var(--wp--preset--color--contrast);opacity:.72}.wp-block-search__input::-ms-input-placeholder{color:var(--wp--preset--color--contrast);opacity:.72}.wp-block-search__input::placeholder{color:var(--wp--preset--color--contrast);opacity:.72}',
    'modern_controls' => '.wp-block-search__input::placeholder{color:var(--wp--preset--color--contrast);opacity:.72}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected placeholder prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
