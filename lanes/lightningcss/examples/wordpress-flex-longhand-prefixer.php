<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TransitionPrefixer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$prefixer = new TransitionPrefixer();

$css = <<<'CSS'
.wp-block-columns.is-layout-flex {
  display: flex;
  flex-flow: row wrap;
  align-items: flex-end;
  justify-content: space-between;
}

.wp-block-buttons .wp-block-button {
  flex: 1;
  order: 1;
  align-self: flex-end;
}

.wp-block-navigation__container {
  place-content: space-between flex-end;
}
CSS;

$actual = [
    'legacy_editor' => $prefixer->prefixForTargets($css, [
        'safari' => 4,
        'firefox' => 4,
        'ie' => 10,
    ]),
    'chrome_28' => $prefixer->prefixForTargets($css, ['chrome' => 28]),
    'modern_frontend' => $prefixer->prefixForTargets($css, ['safari' => 11]),
];

$expected = [
    'legacy_editor' => '.wp-block-columns.is-layout-flex{display:-webkit-box;display:-moz-box;display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-box-orient:horizontal;-moz-box-orient:horizontal;-webkit-box-direction:normal;-moz-box-direction:normal;-webkit-flex-flow:wrap;-ms-flex-flow:wrap;flex-flow:wrap;-webkit-box-align:end;-moz-box-align:end;-ms-flex-align:end;-webkit-align-items:flex-end;align-items:flex-end;-webkit-box-pack:justify;-moz-box-pack:justify;-ms-flex-pack:justify;-webkit-justify-content:space-between;justify-content:space-between}.wp-block-buttons .wp-block-button{-webkit-box-flex:1;-moz-box-flex:1;-webkit-flex:1;-ms-flex:1;flex:1;-webkit-box-ordinal-group:1;-moz-box-ordinal-group:1;-ms-flex-order:1;-webkit-order:1;order:1;-ms-flex-item-align:end;-webkit-align-self:flex-end;align-self:flex-end}.wp-block-navigation__container{-ms-flex-line-pack:justify;-webkit-box-pack:end;-moz-box-pack:end;-ms-flex-pack:end;-webkit-align-content:space-between;align-content:space-between;-webkit-justify-content:flex-end;justify-content:flex-end}',
    'chrome_28' => '.wp-block-columns.is-layout-flex{display:-webkit-flex;display:flex;-webkit-flex-flow:wrap;flex-flow:wrap;-webkit-align-items:flex-end;align-items:flex-end;-webkit-justify-content:space-between;justify-content:space-between}.wp-block-buttons .wp-block-button{-webkit-flex:1;flex:1;-webkit-order:1;order:1;-webkit-align-self:flex-end;align-self:flex-end}.wp-block-navigation__container{-webkit-align-content:space-between;align-content:space-between;-webkit-justify-content:flex-end;justify-content:flex-end}',
    'modern_frontend' => '.wp-block-columns.is-layout-flex{display:flex;flex-flow:wrap;align-items:flex-end;justify-content:space-between}.wp-block-buttons .wp-block-button{flex:1;order:1;align-self:flex-end}.wp-block-navigation__container{place-content:space-between flex-end}',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected flex longhand prefix output:\n" . var_export($actual, true) . "\n");
        exit(1);
    }

    echo "OK\n";
    exit(0);
}

echo implode(PHP_EOL, $actual) . PHP_EOL;
