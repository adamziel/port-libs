<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$css = <<<'CSS'
@page {
  @top-left-corner {
    @bottom-left {
      content: "Chapter";
    }
  }
}
CSS;

try {
    echo (new CssFormatter())->format($css);
} catch (InvalidArgumentException $exception) {
    echo $exception->getMessage() . "\n";
}
