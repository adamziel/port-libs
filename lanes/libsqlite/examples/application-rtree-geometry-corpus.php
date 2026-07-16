<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRTreeGeometry;

$rect = static fn (float $minX, float $maxX, float $minY, float $maxY): array => [
    'minX' => $minX,
    'maxX' => $maxX,
    'minY' => $minY,
    'maxY' => $maxY,
];

$events = [
    ['id' => 'site-health', 'rectangle' => $rect(-74.01, -73.99, 40.70, 40.72)],
    ['id' => 'plugin-meetup', 'rectangle' => $rect(-73.98, -73.95, 40.72, 40.75)],
    ['id' => 'remote-webinar', 'rectangle' => $rect(-122.45, -122.40, 37.75, 37.80)],
    ['id' => 'edge-touch', 'rectangle' => $rect(-73.95, -73.94, 40.71, 40.73)],
];

$viewport = $rect(-74.02, -73.95, 40.70, 40.73);
$matches = SQLiteRTreeGeometry::filterOverlapping($events, $viewport);

echo json_encode([
    'applicationUse' => 'Preview RTREE-style bounding-box filtering for copied Application event/location rows without requiring ext/sqlite.',
    'viewport' => $viewport,
    'matchingEventIds' => array_column($matches, 'id'),
    'viewportArea' => SQLiteRTreeGeometry::area($viewport),
    'edgeInclusive' => SQLiteRTreeGeometry::overlaps($viewport, $rect(-73.95, -73.94, 40.71, 40.73)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
