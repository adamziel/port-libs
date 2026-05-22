<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CustomMediaException;
use PortLibs\LightningCSS\CustomMediaTransformer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@custom-media --wp-print-color print and (color);

@media screen and (--wp-print-color) {
  .wp-block-post-title {
    color: green;
  }
}
CSS;

try {
    (new CustomMediaTransformer())->transform($css);
    echo "ok\n";
} catch (CustomMediaException $exception) {
    echo json_encode([
        'kind' => $exception->kind,
        'name' => $exception->name,
        'mediaLocation' => $exception->mediaLocation,
        'customMediaLocation' => $exception->customMediaLocation,
        'message' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT) . "\n";
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
