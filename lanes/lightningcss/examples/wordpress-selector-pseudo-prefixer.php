<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.wp-block-post-content::selection {
  color: red;
}

.wp-block-search__input:placeholder-shown {
  color: red;
}

.wp-block-file input::file-selector-button {
  color: red;
}

.wp-block-login input:autofill {
  color: red;
}

.wp-block-cover:fullscreen {
  outline: 0;
}

.wp-block-cover::backdrop {
  background: black;
}

.wp-block-navigation a:any-link {
  color: red;
}
CSS;

$prefixer = new TransitionPrefixer();
$actual = [
    'firefox61' => $prefixer->prefixForTargets($css, ['firefox' => 61]),
    'chrome88' => $prefixer->prefixForTargets($css, ['chrome' => 88]),
    'safari14' => $prefixer->prefixForTargets($css, ['safari' => 14]),
    'ie11' => $prefixer->prefixForTargets($css, ['ie' => 11]),
];

$expected = [
    'firefox61' => '.wp-block-post-content::-moz-selection{color:red}.wp-block-post-content::selection{color:red}.wp-block-search__input:placeholder-shown{color:red}.wp-block-file input::file-selector-button{color:red}.wp-block-login input:autofill{color:red}.wp-block-cover:-moz-full-screen{outline:0}.wp-block-cover:fullscreen{outline:0}.wp-block-cover::backdrop{background:#000}.wp-block-navigation a:any-link{color:red}',
    'chrome88' => '.wp-block-post-content::selection{color:red}.wp-block-search__input:placeholder-shown{color:red}.wp-block-file input::-webkit-file-upload-button{color:red}.wp-block-file input::file-selector-button{color:red}.wp-block-login input:-webkit-autofill{color:red}.wp-block-login input:autofill{color:red}.wp-block-cover:fullscreen{outline:0}.wp-block-cover::backdrop{background:#000}.wp-block-navigation a:any-link{color:red}',
    'safari14' => '.wp-block-post-content::selection{color:red}.wp-block-search__input:placeholder-shown{color:red}.wp-block-file input::-webkit-file-upload-button{color:red}.wp-block-file input::file-selector-button{color:red}.wp-block-login input:-webkit-autofill{color:red}.wp-block-login input:autofill{color:red}.wp-block-cover:-webkit-full-screen{outline:0}.wp-block-cover:fullscreen{outline:0}.wp-block-cover::backdrop{background:#000}.wp-block-navigation a:any-link{color:red}',
    'ie11' => '.wp-block-post-content::selection{color:red}.wp-block-search__input:-ms-input-placeholder{color:red}.wp-block-search__input:placeholder-shown{color:red}.wp-block-file input::-ms-browse{color:red}.wp-block-file input::file-selector-button{color:red}.wp-block-login input:autofill{color:red}.wp-block-cover:-ms-fullscreen{outline:0}.wp-block-cover:fullscreen{outline:0}.wp-block-cover::-ms-backdrop{background:#000}.wp-block-cover::backdrop{background:#000}.wp-block-navigation a:any-link{color:red}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected selector pseudo prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
