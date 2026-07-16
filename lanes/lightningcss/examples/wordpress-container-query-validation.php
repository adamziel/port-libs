<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssMinifier;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@container none (width < 100vw) {
  .wp-block-query {
    gap: 1rem;
  }
}
CSS;

try {
    echo (new CssMinifier())->minify($css) . PHP_EOL;
} catch (InvalidArgumentException $exception) {
    echo json_encode([
        'status' => 'invalid-container-query',
        'message' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
}
