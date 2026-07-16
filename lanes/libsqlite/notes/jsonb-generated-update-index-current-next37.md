# JSONB Generated UPDATE Index Current Next37

2026-05-27 isolated slice `yield-sqlite-jsonb-generated-update-index-current-next37`.

Behavior:

- Extended `SQLiteJsonbPatchGeneratedIndexPlan` from read-planning into UPDATE
  index maintenance for generated columns and direct expression indexes shaped
  like `json_extract(jsonb_patch(option_value, PATCH), PATH)`.
- `planUpdateIndexEntries()` now compares current and next row images, evaluates
  generated JSONB keys after UPDATE assignments, honors simple partial-index
  predicates, and emits current index delete entries plus next index insert
  entries.
- Covered JSONB, JSON text, JSON5 text, SQL NULL, missing generated paths,
  partial-index activation/deactivation, unchanged updates, skipped rows, and
  malformed input rejection.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedUpdateIndexCurrentNext37Test.php`
  - Result: `1 test files, 138 assertions, 0 failures`
  - PASS-line delta: `+51`
- `php lanes/libsqlite/examples/application-jsonb-generated-update-index-current-next37.php`
  - Result: emitted copied `wp_options` current delete entries for rowid `101`
    and next insert entries for rowids `101` and `102`.

Dependency closure: no new support component is needed; this reuses the native
JSONB codec, JSON5 parser, JSON merge-patch helper, JSON path extraction, and
existing lane-local generated/expression index metadata.

Non-overlap: avoids accepted JSON table source/cursor/hidden/visible
constraints, JSONB patch indexed generated read-planning next27, SQL expression
ORDER BY, expression-index range-cost, WAL/VFS rollback and sync application,
B-tree page move/root-collapse/overflow freelist, Unicode GLOB, and release
runner evidence clusters. This slice is limited to UPDATE current/next index
maintenance entries for JSONB-fed generated columns.
