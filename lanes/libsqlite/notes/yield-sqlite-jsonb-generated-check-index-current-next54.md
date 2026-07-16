# JSONB Generated CHECK Index Current Next54

2026-05-27 isolated slice `yield-sqlite-jsonb-generated-check-index-current-next54`.

Behavior:

- Added `SQLiteJsonbGeneratedCheckIndexPlan` for copied `wp_options` JSONB
  updates whose `option_value` feeds generated columns guarded by CHECK
  constraints and partial/generated indexes.
- The plan applies current/next JSONB mutations one row at a time, evaluates
  generated-column CHECK constraints before admitting the row, skips rejected
  rows from final images, and emits B-tree index delete/insert actions only for
  admitted rows.
- Covers generated `jsonb_extract()` STORED/VIRTUAL columns, `<>`, `>=`, and
  `BETWEEN` CHECK constraints, partial index deactivation, unique generated
  slug index updates, descending generated rank index ordering, rejected row
  diagnostics, and unique-conflict propagation after CHECK admission.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS jsonb generated check index current next54 table and generated metadata
PASS jsonb generated check index current next54 extracts check constraints
PASS jsonb generated check index current next54 admits only check-clean updates
PASS jsonb generated check index current next54 keeps rejected row images out of final rows
PASS jsonb generated check index current next54 reports failed check details
PASS jsonb generated check index current next54 decodes accepted JSONB payloads
PASS jsonb generated check index current next54 emits admitted index actions only
PASS jsonb generated check index current next54 updates partial enabled membership
PASS jsonb generated check index current next54 updates unique slug index image
PASS jsonb generated check index current next54 preserves descending rank order
PASS jsonb generated check index current next54 validates input guards
PASS jsonb generated check index current next54 propagates unique conflicts after check pass

1 test files, 58 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-jsonb-generated-check-index-current-next54.php
{
    "scenario": "application-jsonb-generated-check-index-current-next54",
    "changes": 2,
    "acceptedRowids": [
        101,
        103
    ],
    "rejectedRowids": [
        102
    ],
    "indexActions": 5,
    "finalSlugs": [
        "alpha",
        "beta",
        "epsilon"
    ],
    "applicationUse": "Preflight copied wp_options JSONB setting imports so generated-column CHECK constraints reject bad current\/next rows before partial generated indexes are rewritten."
}
```

Dependency closure: no new support component is needed; this reuses the native
JSONB codec, JSON mutation/extract helpers, generated-column dependency
analysis, index-cell/page encoding, and partial-index predicate metadata.

Non-overlap: avoids accepted JSONB generated partial UPSERT next49, JSONB
generated update/index current-next37/38, JSON table source/cursor/hidden/
visible constraints, SQL SELECT text/subquery/ORDER/GROUP clusters, VFS/WAL
apply clusters, B-tree page move/root-collapse/overflow freelist clusters, and
Unicode GLOB. This slice is limited to CHECK-gated current/next generated
JSONB index admission.
