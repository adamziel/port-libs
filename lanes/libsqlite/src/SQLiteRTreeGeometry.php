<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRTreeGeometry
{
    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     * @return array{minX:float,maxX:float,minY:float,maxY:float}
     */
    public static function rectangle(array $rectangle): array
    {
        foreach (['minX', 'maxX', 'minY', 'maxY'] as $key) {
            if (!array_key_exists($key, $rectangle) || !is_int($rectangle[$key]) && !is_float($rectangle[$key])) {
                throw new \InvalidArgumentException("SQLite RTREE rectangle {$key} must be numeric");
            }
        }

        $minX = (float) $rectangle['minX'];
        $maxX = (float) $rectangle['maxX'];
        $minY = (float) $rectangle['minY'];
        $maxY = (float) $rectangle['maxY'];

        if ($minX > $maxX || $minY > $maxY) {
            throw new \InvalidArgumentException('SQLite RTREE rectangle minimum coordinate exceeds maximum coordinate');
        }

        return ['minX' => $minX, 'maxX' => $maxX, 'minY' => $minY, 'maxY' => $maxY];
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $left
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $right
     */
    public static function overlaps(array $left, array $right): bool
    {
        $left = self::rectangle($left);
        $right = self::rectangle($right);

        return $left['minX'] <= $right['maxX']
            && $left['maxX'] >= $right['minX']
            && $left['minY'] <= $right['maxY']
            && $left['maxY'] >= $right['minY'];
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $outer
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $inner
     */
    public static function contains(array $outer, array $inner): bool
    {
        $outer = self::rectangle($outer);
        $inner = self::rectangle($inner);

        return $outer['minX'] <= $inner['minX']
            && $outer['maxX'] >= $inner['maxX']
            && $outer['minY'] <= $inner['minY']
            && $outer['maxY'] >= $inner['maxY'];
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $inner
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $outer
     */
    public static function within(array $inner, array $outer): bool
    {
        return self::contains($outer, $inner);
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     */
    public static function area(array $rectangle): float
    {
        $rectangle = self::rectangle($rectangle);

        return ($rectangle['maxX'] - $rectangle['minX']) * ($rectangle['maxY'] - $rectangle['minY']);
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     */
    public static function margin(array $rectangle): float
    {
        $rectangle = self::rectangle($rectangle);

        return 2.0 * (($rectangle['maxX'] - $rectangle['minX']) + ($rectangle['maxY'] - $rectangle['minY']));
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     * @return array{x:float,y:float}
     */
    public static function center(array $rectangle): array
    {
        $rectangle = self::rectangle($rectangle);

        return [
            'x' => ($rectangle['minX'] + $rectangle['maxX']) / 2.0,
            'y' => ($rectangle['minY'] + $rectangle['maxY']) / 2.0,
        ];
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     */
    public static function distanceSquaredToPoint(array $rectangle, int|float $x, int|float $y): float
    {
        $rectangle = self::rectangle($rectangle);
        $dx = self::axisDistance((float) $x, $rectangle['minX'], $rectangle['maxX']);
        $dy = self::axisDistance((float) $y, $rectangle['minY'], $rectangle['maxY']);

        return $dx * $dx + $dy * $dy;
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     */
    public static function circleOverlaps(array $rectangle, int|float $x, int|float $y, int|float $radius): bool
    {
        self::assertRadius($radius);

        return self::distanceSquaredToPoint($rectangle, $x, $y) <= (float) $radius * (float) $radius;
    }

    /**
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $rectangle
     */
    public static function circleContains(array $rectangle, int|float $x, int|float $y, int|float $radius): bool
    {
        self::assertRadius($radius);
        $rectangle = self::rectangle($rectangle);
        $radiusSquared = (float) $radius * (float) $radius;

        foreach ([[$rectangle['minX'], $rectangle['minY']], [$rectangle['minX'], $rectangle['maxY']], [$rectangle['maxX'], $rectangle['minY']], [$rectangle['maxX'], $rectangle['maxY']]] as [$cornerX, $cornerY]) {
            $dx = $cornerX - (float) $x;
            $dy = $cornerY - (float) $y;
            if ($dx * $dx + $dy * $dy > $radiusSquared) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{id:mixed,rectangle:array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float}}> $rows
     * @param array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float} $query
     * @return list<array{id:mixed,rectangle:array{minX:int|float,maxX:int|float,minY:int|float,maxY:int|float}}>
     */
    public static function filterOverlapping(array $rows, array $query): array
    {
        $matches = [];
        foreach ($rows as $row) {
            if (!array_key_exists('rectangle', $row) || !is_array($row['rectangle'])) {
                throw new \InvalidArgumentException('SQLite RTREE row must include a rectangle');
            }
            if (self::overlaps($row['rectangle'], $query)) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    private static function axisDistance(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min - $value;
        }
        if ($value > $max) {
            return $value - $max;
        }

        return 0.0;
    }

    private static function assertRadius(int|float $radius): void
    {
        if ((float) $radius < 0.0) {
            throw new \InvalidArgumentException('SQLite RTREE geometry radius must be non-negative');
        }
    }
}
