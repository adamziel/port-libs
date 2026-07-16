# pragma-index-xinfo-tableinfo-analysis-current-source-next108

Adds `SQLitePragmaIndexTableInfoAnalysis`, a current-source analysis layer over
the already accepted PRAGMA row emitters for:

- `PRAGMA table_info(...)`
- `PRAGMA table_xinfo(...)`
- `PRAGMA index_info(...)`
- `PRAGMA index_xinfo(...)`
- table-valued `pragma_table_info(...)` / `pragma_index_xinfo(...)`

The helper records stable source IDs from resolved temp/main/attached PRAGMA
rows, paginates analysis entries, resumes from a cursor, and rejects stale
source IDs or offsets. The summary includes visible/generated/default/not-null
table counts plus key/auxiliary/expression/rowid/collation index counts.

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-index-xinfo-tableinfo-analysis-current-source-next108.php --self-test
application-pragma-index-xinfo-tableinfo-analysis-current-source-next108 self-test passed
```

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexTableInfoAnalysisCurrentSourceNext108Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

PASS-line delta: 50 focused PASS lines.

Additional checks:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexTableInfoAnalysis.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexTableInfoAnalysisCurrentSourceNext108Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-tableinfo-analysis-current-source-next108.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed; this reuses
`SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and existing schema
record parsing.

Non-overlap: avoids accepted PRAGMA `index_xinfo` expression metadata,
`table_info`/`table_xinfo` row cursors, `index_xinfo` integrity/quick_check root
diagnostics, foreign-key integrity pointer-map checks, and batch104/105
current-source PRAGMA/FK/integrity surfaces. This slice only adds resumable
analysis summaries over the current resolved PRAGMA rowsets.
