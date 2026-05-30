# Real Upstream Corpus B-tree/Index Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-btree-20260530T172258Z`

Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
  - `index-1.1`, `index-1.1b`
  - `index-2.1`, `index-2.1b`, `index-2.2`
  - `index-3.1`
  - `index-4.1`
  - `index-6.1`, `index-6.1c`, `index-6.2`
  - `index-7.1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`
  - `index3-2.1`
  - `index3-2.4`

## Behavior Ported

`SQLiteSchemaImportExecutor` now validates simple `CREATE INDEX` column terms against the target table, including SQLite's legacy string-identifier behavior, while leaving expression indexes to the expression-index parser paths. It also rejects explicit index names that collide with existing table names, matching upstream `index-6.2`.

The new focused test file uses generic `test1`, `test2`, and `app_settings` scenarios. It does not add generated fake upstream IDs, metadata-only admissions, or domain-specific API names.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamIndexCreateCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamIndexCreateCorpusTest.php`
  - `1 test files, 39 assertions, 0 failures`
  - `21` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php lanes/libsqlite/tests/SQLiteUpstreamIndexCreateCorpusTest.php`
  - `2 test files, 93 assertions, 0 failures`

## Delta And Non-overlap

- Focused PASS-line delta: `+21` over the prior lane estimate of `208305`, for a pending local estimate of `208326`.
- Mapped coverage: unchanged at `958 / 1589`; this patch does not claim new manifest denominator rows.
- Non-overlap: this does not repeat prior B-tree freeblock/freelist/page-move/pointer-map diagnostics, expression-index range-cost ranking, JSON table source/constraint work, WAL/VFS write paths, or source-neutral cleanup. It targets `CREATE INDEX` schema admission/rejection behavior from real upstream `index.test` and `index3.test`.

## Dependency Closure

No new support component is needed. The patch reuses lane-local schema import/catalog primitives and only tightens native PHP `CREATE INDEX` validation.
