<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRTreeGeometry;

$tests = [];

$rect = static fn (float|int $minX, float|int $maxX, float|int $minY, float|int $maxY): array => [
    'minX' => $minX,
    'maxX' => $maxX,
    'minY' => $minY,
    'maxY' => $maxY,
];

$overlapCases = [
    'identical rectangles overlap' => [$rect(0, 10, 0, 10), $rect(0, 10, 0, 10), true],
    'interior intersection overlaps' => [$rect(0, 10, 0, 10), $rect(5, 15, 5, 15), true],
    'shared vertical edge overlaps' => [$rect(0, 10, 0, 10), $rect(10, 20, 2, 8), true],
    'shared horizontal edge overlaps' => [$rect(0, 10, 0, 10), $rect(2, 8, 10, 20), true],
    'shared corner overlaps' => [$rect(0, 10, 0, 10), $rect(10, 20, 10, 20), true],
    'separated on x does not overlap' => [$rect(0, 10, 0, 10), $rect(10.1, 20, 0, 10), false],
    'separated on y does not overlap' => [$rect(0, 10, 0, 10), $rect(0, 10, 10.1, 20), false],
    'negative coordinates overlap' => [$rect(-10, -1, -5, 5), $rect(-3, 4, -2, 2), true],
    'point rectangle on edge overlaps' => [$rect(0, 10, 0, 10), $rect(10, 10, 4, 4), true],
    'distant point rectangle misses' => [$rect(0, 10, 0, 10), $rect(11, 11, 4, 4), false],
    'fractional coordinates overlap' => [$rect(1.25, 2.5, 3.5, 4.75), $rect(2.0, 3.0, 4.0, 5.0), true],
    'fractional coordinates miss' => [$rect(1.25, 2.5, 3.5, 4.75), $rect(2.51, 3.0, 4.0, 5.0), false],
];

foreach ($overlapCases as $name => [$left, $right, $expected]) {
    $tests['rtree geometry overlap ' . $name] = static function (TestRunner $t) use ($left, $right, $expected): void {
        $t->same($expected, SQLiteRTreeGeometry::overlaps($left, $right));
        $t->same($expected, SQLiteRTreeGeometry::overlaps($right, $left));
    };
}

$containmentCases = [
    'outer contains identical rectangle' => [$rect(0, 10, 0, 10), $rect(0, 10, 0, 10), true],
    'outer contains interior rectangle' => [$rect(0, 10, 0, 10), $rect(2, 8, 3, 7), true],
    'outer contains vertical line on boundary' => [$rect(0, 10, 0, 10), $rect(0, 0, 2, 8), true],
    'outer contains horizontal line on boundary' => [$rect(0, 10, 0, 10), $rect(2, 8, 10, 10), true],
    'outer does not contain lower x spill' => [$rect(0, 10, 0, 10), $rect(-1, 8, 3, 7), false],
    'outer does not contain upper x spill' => [$rect(0, 10, 0, 10), $rect(2, 11, 3, 7), false],
    'outer does not contain lower y spill' => [$rect(0, 10, 0, 10), $rect(2, 8, -1, 7), false],
    'outer does not contain upper y spill' => [$rect(0, 10, 0, 10), $rect(2, 8, 3, 11), false],
    'negative outer contains negative inner' => [$rect(-20, -5, -10, -1), $rect(-15, -10, -8, -2), true],
    'fractional boundary contains' => [$rect(1.5, 4.5, 2.5, 6.5), $rect(1.5, 4.0, 3.0, 6.5), true],
];

foreach ($containmentCases as $name => [$outer, $inner, $expected]) {
    $tests['rtree geometry containment ' . $name] = static function (TestRunner $t) use ($outer, $inner, $expected): void {
        $t->same($expected, SQLiteRTreeGeometry::contains($outer, $inner));
        $t->same($expected, SQLiteRTreeGeometry::within($inner, $outer));
    };
}

$metricCases = [
    'unit square area' => [static fn (): mixed => SQLiteRTreeGeometry::area($rect(0, 1, 0, 1)), 1.0],
    'wide rectangle area' => [static fn (): mixed => SQLiteRTreeGeometry::area($rect(2, 8, 3, 5)), 12.0],
    'vertical line area' => [static fn (): mixed => SQLiteRTreeGeometry::area($rect(4, 4, 1, 9)), 0.0],
    'point area' => [static fn (): mixed => SQLiteRTreeGeometry::area($rect(4, 4, 9, 9)), 0.0],
    'unit square margin' => [static fn (): mixed => SQLiteRTreeGeometry::margin($rect(0, 1, 0, 1)), 4.0],
    'wide rectangle margin' => [static fn (): mixed => SQLiteRTreeGeometry::margin($rect(2, 8, 3, 5)), 16.0],
    'point margin' => [static fn (): mixed => SQLiteRTreeGeometry::margin($rect(4, 4, 9, 9)), 0.0],
    'center positive rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::center($rect(0, 10, 2, 8)), ['x' => 5.0, 'y' => 5.0]],
    'center negative rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::center($rect(-10, -2, -8, -4)), ['x' => -6.0, 'y' => -6.0]],
    'center fractional rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::center($rect(1.5, 2.5, 3.25, 4.75)), ['x' => 2.0, 'y' => 4.0]],
];

foreach ($metricCases as $name => [$callback, $expected]) {
    $tests['rtree geometry metric ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$distanceCases = [
    'point inside rectangle has zero distance' => [$rect(0, 10, 0, 10), 5, 5, 0.0],
    'point on rectangle edge has zero distance' => [$rect(0, 10, 0, 10), 10, 5, 0.0],
    'point left of rectangle' => [$rect(0, 10, 0, 10), -3, 5, 9.0],
    'point right of rectangle' => [$rect(0, 10, 0, 10), 13, 5, 9.0],
    'point below rectangle' => [$rect(0, 10, 0, 10), 5, -4, 16.0],
    'point above rectangle' => [$rect(0, 10, 0, 10), 5, 14, 16.0],
    'point diagonal from rectangle' => [$rect(0, 10, 0, 10), 13, 14, 25.0],
    'fractional point diagonal' => [$rect(1.5, 2.5, 3.5, 4.5), 0.5, 2.5, 2.0],
];

foreach ($distanceCases as $name => [$rectangle, $x, $y, $expected]) {
    $tests['rtree geometry point distance ' . $name] = static function (TestRunner $t) use ($rectangle, $x, $y, $expected): void {
        $t->same($expected, SQLiteRTreeGeometry::distanceSquaredToPoint($rectangle, $x, $y));
    };
}

$circleCases = [
    'circle center inside overlaps' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(0, 10, 0, 10), 5, 5, 1), true],
    'circle tangent to edge overlaps' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(0, 10, 0, 10), 12, 5, 2), true],
    'circle outside edge misses' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(0, 10, 0, 10), 12.1, 5, 2), false],
    'circle tangent to corner overlaps' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(0, 10, 0, 10), 13, 14, 5), true],
    'circle outside corner misses' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(0, 10, 0, 10), 13, 14, 4.99), false],
    'circle contains small rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::circleContains($rect(-1, 1, -1, 1), 0, 0, 2), true],
    'circle does not contain far corner' => [static fn (): mixed => SQLiteRTreeGeometry::circleContains($rect(-2, 2, -2, 2), 0, 0, 2), false],
    'zero radius contains point rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::circleContains($rect(3, 3, 4, 4), 3, 4, 0), true],
    'zero radius overlaps point rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(3, 3, 4, 4), 3, 4, 0), true],
    'zero radius misses different point rectangle' => [static fn (): mixed => SQLiteRTreeGeometry::circleOverlaps($rect(3, 3, 4, 4), 3, 5, 0), false],
];

foreach ($circleCases as $name => [$callback, $expected]) {
    $tests['rtree geometry circle ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['rtree geometry filters copied application event rectangles'] = static function (TestRunner $t) use ($rect): void {
    $rows = [
        ['id' => 'site-health', 'rectangle' => $rect(-74.01, -73.99, 40.70, 40.72)],
        ['id' => 'plugin-meetup', 'rectangle' => $rect(-73.98, -73.95, 40.72, 40.75)],
        ['id' => 'remote-webinar', 'rectangle' => $rect(-122.45, -122.40, 37.75, 37.80)],
        ['id' => 'edge-touch', 'rectangle' => $rect(-73.95, -73.94, 40.71, 40.73)],
    ];

    $matches = SQLiteRTreeGeometry::filterOverlapping($rows, $rect(-74.02, -73.95, 40.70, 40.73));
    $t->same(['site-health', 'plugin-meetup', 'edge-touch'], array_column($matches, 'id'));
};

$tests['rtree geometry rejects inverted x bounds'] = static function (TestRunner $t) use ($rect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::rectangle($rect(5, 4, 0, 1)));
};

$tests['rtree geometry rejects inverted y bounds'] = static function (TestRunner $t) use ($rect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::rectangle($rect(0, 1, 5, 4)));
};

$tests['rtree geometry rejects missing coordinate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::rectangle(['minX' => 0, 'maxX' => 1, 'minY' => 0]));
};

$tests['rtree geometry rejects non numeric coordinate'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::rectangle(['minX' => 0, 'maxX' => 1, 'minY' => 0, 'maxY' => 'north']));
};

$tests['rtree geometry rejects negative circle radius'] = static function (TestRunner $t) use ($rect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::circleOverlaps($rect(0, 1, 0, 1), 0, 0, -1));
};

$tests['rtree geometry rejects rows without rectangles'] = static function (TestRunner $t) use ($rect): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRTreeGeometry::filterOverlapping([['id' => 1]], $rect(0, 1, 0, 1)));
};

return $tests;
