<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssModulesTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
.card {
  background: white;

  .cardIcon {
    color: yellow;
  }

  composes: reset from "./core.module.css";
  composes: has-spacing from global;
}

:global(.wp-block-button) .card {
  border-radius: 4px;
}

:local(.cardTitle) {
  composes: heading from "./typography.module.css";
  color: yellow;
}

@media (min-width: 600px) {
  .cardCompact {
    composes: card;
    gap: 8px;
  }
}
CSS;

$result = (new CssModulesTransformer())->transform($css, [
    'hash' => 'BlockA',
]);

echo $result['code'] . PHP_EOL;
echo json_encode($result['exports'], JSON_PRETTY_PRINT) . PHP_EOL;

try {
    (new CssModulesTransformer())->transform(<<<'CSS'
.card {
  :global {
    .legacyUtility {
      color: red;
    }
  }
}
CSS);
    echo 'bare-global: accepted' . PHP_EOL;
} catch (InvalidArgumentException) {
    echo 'bare-global: rejected' . PHP_EOL;
}
