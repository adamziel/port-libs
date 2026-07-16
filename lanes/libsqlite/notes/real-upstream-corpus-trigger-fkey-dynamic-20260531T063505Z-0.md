# real-upstream-corpus-trigger-fkey-dynamic-20260531T063505Z-0

Base accepted HEAD: `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`
- Ported sections: `fkey5-1.1..1.6`, `fkey5-2.0..2.3`, `fkey5-3.0..3.2`, and `fkey5-4.0..4.5`.

## Behavior

- `PRAGMA foreign_key_check` returns four-column violation rows containing the child table, child rowid, parent table, and FK id.
- Unfiltered checks report violations across multiple child tables.
- `PRAGMA foreign_key_check(table)` narrows results to one child table.
- Schema-qualified checks do not report main-schema tables when the requested schema is `temp`.
- Child rows with a NULL FK value are suppressed rather than reported as violations.

## Changed Lane Files

- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey5BasicCheck20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T063505Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey5BasicCheck20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey5BasicCheck20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey5BasicCheck20260531Test.php`
  - `1 test files, 6605 assertions, 0 failures`

## Countability

- Focused selected movement: `+6605` behavior assertions from real upstream `fkey5.test` cases.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth over an already mapped upstream file, not new denominator mapping.

## Non-Overlap

This does not repeat accepted fkey2 DDL/counter/count_changes, fkey5 collation matrix, fkey5 WITHOUT ROWID rowid-null, fkey6 defer pragma, fkey7 authorizer/conflict, trigger RAISE, triggerC, triggerG, recursive view trigger, UPSERT/RETURNING, WAL, VFS, B-tree, JSON, PRAGMA schema, or source-neutral cleanup batches. This slice covers the earlier fkey5 basic `foreign_key_check` row-shape/table-filter/schema-filter behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP foreign-key check corpus helper and the hydrated upstream SQLite checkout as source truth.
