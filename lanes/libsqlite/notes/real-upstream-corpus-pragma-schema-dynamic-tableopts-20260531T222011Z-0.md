# real-upstream-corpus-pragma-schema-dynamic-tableopts-20260531T222011Z-0

Base accepted HEAD: `6cff27008844e2e4a3255962746465ff174dc963`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/tableopts.test`
- Ported sections:
  - `tableopt-1.1`: `CREATE TABLE ... WITHOUT rowid` without a primary key is rejected with `PRIMARY KEY missing on table ...`.
  - `tableopt-1.1b`: `AUTOINCREMENT` is rejected on `WITHOUT ROWID` tables.
  - `tableopt-1.2`: `WITHOUT unknown2` is rejected as an unknown table option.
  - `tableopt-2.1`: valid composite primary-key `WITHOUT ROWID` table schema is accepted and exposed through catalog metadata.
  - `tableopt-3.1`: `without` remains usable as an identifier.

## Behavior patch

- `SQLiteSchemaImportExecutor` now parses CREATE TABLE options after the column-list close paren.
- Unknown table options are rejected before schema records are allocated.
- `WITHOUT ROWID` now requires an explicit primary key and rejects column `AUTOINCREMENT`.
- Valid composite primary-key `WITHOUT ROWID` imports still create the primary-key autoindex and preserve `PRAGMA table_list`, `table_info`, and `index_list` metadata.

Red-first probe before the source edit:

```text
accepted: CREATE TABLE t1(a,b) WITHOUT rowid
accepted: CREATE TABLE t2(a INTEGER PRIMARY KEY AUTOINCREMENT,b) WITHOUT rowid
accepted: CREATE TABLE t3(a,b) WITHOUT unknown2
```

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTableOptions20260531Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 26007 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 55 assertions, 0 failures
```

`SQLiteRealUpstreamPragmaSchemaDynamicSchema6Test.php` was sampled as an adjacent catalog-family check and failed on a pre-existing catalog `origin` expectation (`expected 'u', actual 'pk'`). This patch does not edit `SQLitePragmaSchemaCatalog`; the accepted verification is scoped to the changed executor, the new real upstream corpus, and the no-domain source guard.

## Non-overlap

This slice is disjoint from the already accepted pragma/table-list, temp pager, `lock_proxy_file`, auto-vacuum parser, trusted schema, and schema6 metadata slices. It owns only upstream `tableopts.test` table-option parsing and executor-backed schema-import behavior.

## Dependency closure

No new support component is needed. The patch reuses the existing lane-local schema import executor and PRAGMA catalog surface.

## Follow-up

`tableopts.test` rowid-alias SELECT errors (`rowid`, `_rowid_`, `oid`) and VACUUM/reopen data persistence remain executor/storage follow-up work outside this schema-only micro-slice.
