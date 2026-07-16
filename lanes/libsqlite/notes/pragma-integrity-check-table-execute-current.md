# PRAGMA integrity_check table execute current

Slice: `libsqlite-pragma-integrity-current`

## Behavior

- Wired table-scoped `PRAGMA integrity_check(table)` and
  `PRAGMA quick_check(table)` through `SQLitePragmaIntegrityCheck::execute()`.
- Numeric `PRAGMA integrity_check(N)` and `PRAGMA quick_check(N)` remain global
  error-limit calls.
- Quoted table targets (`"name"`, `'name'`, `` `name` ``, `[name]`) and
  schema-qualified scoped PRAGMA calls now route to the existing native
  table-scope integrity walker.
- Scoped checks report table/index rootpage and pointer-map errors for the
  target table while ignoring unrelated table and freelist/global errors.

## Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityCheckTableScopeExecuteTest.php
1 test files, 47 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaIntegrityTableScopeCurrentNext65Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityCheckTableScopeExecuteTest.php
3 test files, 182 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-pragma-integrity-check-table-execute.php --self-test
application-pragma-integrity-check-table-execute self-test passed
```

## Non-Overlap

This reuses the accepted table-scope integrity helper and adds parser/executor
routing at the main PRAGMA executor boundary. It does not add numbered
production classes and does not repeat accepted pointer-map/freelist integrity
pagination, rootpage analysis cursors, quick_check rootpage/index wrappers, or
index_xinfo/foreign-key current-source slices.

## Dependency Closure

No new support component is needed. The slice reuses native schema-record,
table-scope integrity, pointer-map, freelist, and b-tree page helpers already
under `lanes/libsqlite/src`.
