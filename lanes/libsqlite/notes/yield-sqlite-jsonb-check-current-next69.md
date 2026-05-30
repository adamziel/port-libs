# JSONB CHECK Current Next69

2026-05-28 isolated slice `jsonb-check-current-next69`.

## Status

- Adds native PHP parsing/evaluation for SQLite JSONB CHECK terms using
  `NOT IN (...)` and `NOT BETWEEN ... AND ...`.
- Keeps the `AND` inside BETWEEN bounds intact while preserving existing
  top-level CHECK `AND` term splitting for earlier current-next64/67 behavior.
- Adds focused current/next admission coverage for copied `wp_options` JSONB
  plugin rows where denied channels, legacy families, and reserved rank/version
  ranges are rejected before storage admission.
- Adds a Application smoke for plugin-setting imports guarded by `NOT IN` and
  `NOT BETWEEN` CHECK constraints.

## Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 135 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-jsonb-check-current-next69.php
{
    "scenario": "application-jsonb-check-current-next69",
    "changes": 2,
    "rejectedChanges": 2,
    "acceptedRowids": [
        401,
        404
    ],
    "rejectedRowids": [
        402,
        403
    ],
    "failedChecks": [
        "CHECK(json_extract(option_value, '$.plugin.channel') NOT IN ('nightly','dev','blocked'))",
        "CHECK(json_extract(option_value, '$.plugin.min_wp') NOT BETWEEN 6.8 AND 7.9)"
    ],
    "applicationUse": "Preflight copied wp_options JSONB plugin settings with SQLite NOT IN and NOT BETWEEN CHECK guards before import rows are admitted."
}
```

New focused PASS-line delta: `+47` from
`SQLiteJsonbCheckCurrentNext69Test.php`. Lane-local `phpPass` moves from
`25516` to `25563`. Mapped upstream coverage is unchanged at `463 / 1589`.

## Non-Overlap

This avoids accepted JSONB CHECK current-next64 basic `json_valid`,
`json_type`, `IN`, range, and current/next row admission coverage, as well as
accepted current-next67 logical `OR`/`NOT` CHECK admission. It also avoids
queued JSONB delete/cascade surfaces, JSON table hidden/visible constraints,
JSON table SELECT source/cursor behavior, WAL/pager savepoint work, B-tree
freelist/pointer-map work, and SELECT SQL planner/executor clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local JSONB
encoding/decoding, JSON path extraction, JSON mutation, and CHECK current/next
planning primitives.
