# PRAGMA index_xinfo / foreign-key current-source next238

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` for the upstream SQLite parent-key rule that `PRAGMA index_xinfo` descending sort flags do not make an otherwise matching non-partial UNIQUE parent index invalid for `REFERENCES parent(column...)`.
- Uses `PRAGMA foreign_key_list` parent columns with `PRAGMA index_xinfo.desc` metadata to distinguish:
  - admissible descending UNIQUE parent indexes,
  - admissible ascending UNIQUE parent indexes,
  - permuted descending UNIQUE indexes that still do not match FK parent column order,
  - missing non-partial parent UNIQUE indexes.
- Keeps the slice disjoint from accepted parent collation, expression parent-key, partial-index, hidden constraint, child prefix, and missing-parent-table PRAGMA coverage.

## Application smoke

- `examples/application-pragma-index-xinfo-foreignkey-current-source-next238.php`
- Scenario: copied Application import schemas can admit `UNIQUE(site_id DESC, slug DESC)` parent indexes exposed by `PRAGMA index_xinfo(desc=1)` before schema replay, avoiding a false FK repair blocker.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 82 assertions, 0 failures`
  - `64` focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next238.php`
  - self-test passes
- `php -l` passed for the new source, test, and example files.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency closure

No new support component is needed. This reuses existing lane-local schema catalog parsing, `PRAGMA index_xinfo`, and `PRAGMA foreign_key_list` helpers.
