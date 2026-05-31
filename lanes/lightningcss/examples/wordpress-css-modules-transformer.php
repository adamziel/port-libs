<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
:global(.wp-block-button) .card {
  background: white;

  .cardIcon {
    color: yellow;
  }

  composes: reset from "./core.module.css";
  composes: has-spacing from global;
}

:local(.cardTitle) {
  composes: heading from "./typography.module.css";
  color: yellow;
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

echo $result['code'] . PHP_EOL;
echo json_encode($result['exports'], JSON_PRETTY_PRINT) . PHP_EOL;
