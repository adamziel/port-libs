<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@container unknown(foo) {
  .wp-block-query {
    gap: 1rem;
  }
}

.wp-block-query {
  color: yellow;
}
CSS;

echo json_encode((new CssMinifier())->minifyWithErrorRecovery($css, 'block-query.css'), JSON_PRETTY_PRINT) . PHP_EOL;
