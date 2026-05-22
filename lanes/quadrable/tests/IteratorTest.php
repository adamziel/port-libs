<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SparseTreeIterator;

return [
    'maps upstream iterator basic seek behavior' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $changes = $tree->change();
        for ($i = 2; $i < 20; $i += 2) {
            $changes->putKey(Key::fromInteger($i), (string) $i);
        }
        $changes->apply();

        $t->same('2', iteratorValue($tree->iterate(Key::fromInteger(1))));
        $t->same('18', iteratorValue($tree->iterate(Key::fromInteger(19), true)));
        $t->same(null, iteratorValue($tree->iterate(Key::fromInteger(19))));
        $t->same(null, iteratorValue($tree->iterate(Key::fromInteger(1), true)));
        $t->same('12', iteratorValue($tree->iterate(Key::fromInteger(11))));
        $t->same('10', iteratorValue($tree->iterate(Key::fromInteger(11), true)));
    },
    'maps upstream iterator full lower and upper bound windows' => static function (TestRunner $t): void {
        foreach ([[5, 20, 2], [10, 200, 15]] as [$start, $end, $skip]) {
            $tree = new SparseTree();
            $values = [];
            $changes = $tree->change();

            for ($i = $start; $i < $end; $i += $skip) {
                $values[] = $i;
                $changes->putKey(Key::fromInteger($i), (string) $i);
            }

            $changes->apply();

            for ($i = $start - 5; $i < $end + 5; $i++) {
                $expected = [];
                foreach ($values as $value) {
                    if ($value >= $i) {
                        $expected[] = (string) $value;
                    }
                }

                $t->same($expected, iteratorValues($tree->iterate(Key::fromInteger($i))), 'forward window from ' . $i);
            }

            for ($i = $end + 5; $i > $start - 5; $i--) {
                $expected = [];
                foreach (array_reverse($values) as $value) {
                    if ($value <= $i) {
                        $expected[] = (string) $value;
                    }
                }

                $t->same($expected, iteratorValues($tree->iterate(Key::fromInteger($i), true)), 'reverse window from ' . $i);
            }
        }
    },
    'wordpress ordered snapshot fixture can be windowed by integer keys' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        $tree = new SparseTree();
        $changes = $tree->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $window = [];
        $iterator = $tree->iterate(Key::fromInteger(2));
        while (!$iterator->atEnd() && count($window) < 3) {
            $entry = $iterator->get();
            $t->true($entry !== null);
            $window[] = $entry->value();
            $iterator->next();
        }

        $t->same([
            'wp_options:home=https://example.test',
            'wp_posts:1=Hello world',
            'wp_postmeta:1:_thumbnail_id=42',
        ], $window);

        $reverse = iteratorValues($tree->iterate(Key::fromInteger(4), true));
        $t->same('wp_postmeta:1:_thumbnail_id=42', $reverse[0]);
        $t->same('wp_options:siteurl=https://example.test', $reverse[3]);
    },
];

function iteratorValue(SparseTreeIterator $iterator): ?string
{
    $entry = $iterator->get();

    return $entry?->value();
}

/**
 * @return list<string>
 */
function iteratorValues(SparseTreeIterator $iterator): array
{
    $values = [];

    while (!$iterator->atEnd()) {
        $entry = $iterator->get();
        if ($entry === null) {
            break;
        }

        $values[] = $entry->value();
        $iterator->next();
    }

    return $values;
}
