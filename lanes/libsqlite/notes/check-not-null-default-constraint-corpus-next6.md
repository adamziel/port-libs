# Check/NOT NULL/DEFAULT Constraint Corpus Next6

## Scope

This slice covers SQLite schema metadata behavior for `PRAGMA table_info` and
`PRAGMA table_xinfo` when `CREATE TABLE` column definitions combine `DEFAULT`,
`NOT NULL`, `CHECK`, generated columns, and table constraints.

The native change is intentionally narrow: `SQLitePragmaSchemaCatalog` now
extracts a column `DEFAULT` value by respecting quoted literals, bracketed
tokens, BLOB literals, and parenthesized expressions before looking for later
top-level column constraints. This prevents constraint keywords embedded inside
default strings or expressions from truncating `dflt_value`.

## Evidence

Focused new corpus:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCheckNotNullDefaultConstraintCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 50 assertions, 0 failures
```

Adjacent catalog regression:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteCheckNotNullDefaultConstraintCorpusTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 122 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-check-not-null-default-constraints.php
```

The smoke reports copied `wp_options` schema metadata preserving default
literals such as `'contains NOT NULL and CHECK text'` while still reporting
`autoload` as `NOT NULL` after a parenthesized default expression.

## Status Delta

- `phpPass`: `2017 -> 2067` from exactly 50 new focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP corpus growth
  and does not claim a newly mapped upstream inventory unit.
- Dependency closure: no new support component is needed. The slice reuses the
  existing schema catalog parser and test harness.

## Non-Overlap

This avoids accepted JSON table source/cursor/hidden/visible constraint work,
VFS writer/sync/lock/rollback clusters, WAL byte truncation/checkpoint and
rollback-journal commit clusters, B-tree page move/root-collapse/overflow
freelist clusters, Unicode GLOB, SELECT SQL subqueries/comma LIMIT/grouping/
expression ORDER BY, and batch5a schema PRAGMA catalog rows. It is limited to
`CREATE TABLE` column constraint metadata extraction for `dflt_value`,
`notnull`, and generated-column visibility.
