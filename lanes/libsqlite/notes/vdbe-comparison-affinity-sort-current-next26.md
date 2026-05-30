# VDBE Comparison Affinity Sort Current/Next 26

## Scope

This slice adds bounded VDBE sorter comparison provenance for copied
`wp_options`-style rows. It does not repeat accepted VDBE aggregate ORDER BY
input cursors, VDBE sorter NULL/collation ordering, VDBE overflow comparison,
SELECT SQL expression ORDER BY, or batch23 surfaces.

The implementation adds:

- `SQLiteVdbeSortCompare::comparisonSteps()` for per-slot affinity, collation,
  storage-class, NULL placement, descending, result, and deciding-slot evidence.
- `SQLiteVdbeSortCompare::sortedRowTrace()` for sorted row order plus stable
  sequence tie evidence used by current/next-style sorter scans.
- A Application smoke for copied option rows sorted by autoload, numeric priority,
  RTRIM option names, NULLS LAST, and stable sequence diagnostics.

## Verification

Focused test:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeComparisonAffinitySortCurrentNext26Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-vdbe-comparison-affinity-sort-current-next26.php
{
    "scenario": "application-vdbe-comparison-affinity-sort-current-next26",
    "orderedOptionIds": [
        14,
        13,
        11,
        10,
        16,
        12,
        15
    ],
    "stableTieOptionIds": []
}
```

## Status Delta

- `phpPass`: `8739 -> 8800` from 61 newly verified focused PASS lines.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP behavior
  coverage over an existing VDBE comparison/sorter surface, not a newly
  hydrated upstream inventory unit.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
comparison, affinity, BLOB, and VDBE sorter cursor helpers.
