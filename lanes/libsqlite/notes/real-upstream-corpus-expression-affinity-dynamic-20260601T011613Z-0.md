# Real upstream expression affinity raise-function dynamic corpus

Base accepted HEAD: `6025aa0c35dc17d20b1c6c068ec52bbef5bf715c`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Section `e_expr-12.4`: trigger-program `RAISE(IGNORE)`,
  `RAISE(ROLLBACK, 'error message')`, `RAISE(ABORT, 'error message')`, and
  `RAISE(FAIL, 'error message')` syntax diagram coverage.

Implementation:

- Added `SQLiteViewTriggerDdlCorpus` extraction of trigger-body `RAISE()`
  actions, preserving action, literal message, and raw expression text.
- Added
  `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRaiseFunction20260601Test.php`
  with 1 source-truth case, 1024 dynamic generic trigger DDL cases, and 1
  ownership/dependency-closure case.
- The dynamic corpus reuses `SQLiteSchemaCatalogDdlPlan` to create generic
  `app_settings_*` trigger rows, then verifies `SQLiteViewTriggerDdlCorpus`
  parses the upstream raise-function actions from stored trigger SQL.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRaiseFunction20260601Test.php`
- Result: `1 test files, 18444 assertions, 0 failures`
- Focused PASS growth: `+1026` TestRunner PASS cases from real upstream
  `e_expr.test` `e_expr-12.4`.

Non-overlap:

- Owns only `e_expr-12.4` trigger-program `RAISE()` syntax preservation and
  action extraction for generic trigger DDL records.
- Avoids trigger runtime behavior already covered by `trigger1.test`
  expression-message shards and `trigger3.test` action rollback/ignore shards.
- Avoids CASE/iif, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, suite
  evidence, and source-neutral cleanup batches.

Dependency closure:

- Reuses existing bounded native components: `SQLiteSchemaCatalogDdlPlan` and
  `SQLiteViewTriggerDdlCorpus`.
- No new support component is needed.

Root harness: not run - isolated micro-slice.
