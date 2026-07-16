<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\SideBySideDiffRenderer;

$asset = static fn (int $index): string => '{"handle":"acme-card-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '","label":"カード📦' . $index . '","src":"file:./build/card-' . $index . '.js"}';

$before = '{"version":"1.0.0","assets":[' . implode(',', array_map($asset, range(0, 48))) . ']}';
$after = '{"version":"1.1.0","assets":[{"handle":"acme-card-view","label":"ビュー📦","src":"file:./build/view.js"},' . implode(',', array_map($asset, range(0, 48))) . ']}';

echo (new SideBySideDiffRenderer())->renderTextDiff($before, $after, [
    'tabWidth' => 4,
    'columnWidth' => 40,
]);
