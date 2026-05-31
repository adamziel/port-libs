# real-upstream-corpus-json1-jsonb-dynamic-20260531T150718Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported `json101-19.1`, `json101-19.2`, and `json101-19.3`.
- Upstream behavior: a transaction begins, a multi-row `INSERT INTO t1 VALUES(0), (json('not-valid-json'))` fails with `malformed JSON`, `COMMIT` still succeeds, and `SELECT * FROM t1` remains empty.

## Change

- `SQLitePDO` now evaluates `json(...)` and `jsonb(...)` scalar expressions inside `INSERT`/`UPDATE` values through the existing `SQLiteJsonCanonical` implementation.
- `SQLitePDO` now snapshots table/column/last-insert state around data-changing statements and restores that snapshot when expression evaluation or row mutation fails, preserving the outer transaction state.
- Added `SQLiteRealUpstreamJson101StatementAtomicityDynamic20260531Test.php` with 1000 dynamic `json101-19` statement-atomicity cases plus hydrated-source and dependency-closure checks.

## Evidence

- Pre-fix reproduction in this worktree failed as expected: `INSERT INTO app_json_queue VALUES(0), (json('not-valid-json'))` raised `SQLitePDO unsupported scalar expression: json('not-valid-json')` and left row `0` visible after `COMMIT`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101StatementAtomicityDynamic20260531Test.php`
  - `1 test files, 16007 assertions, 0 failures`
  - 1002 focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
  - `1 test files, 97 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePDO.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101StatementAtomicityDynamic20260531Test.php`
  - no syntax errors.
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Non-Overlap

This owns the residual upstream `json101-19` transaction-control edge exposed through `json()` malformed input. It does not repeat accepted JSON validity/canonicalization, JSON table cursor/source/constraint pushdown, JSONB mutation/path/operator coverage, WAL/pager savepoint or checkpoint application, B-tree page-move/freeblock work, SELECT text execution, or source-neutral API cleanup slices.

## Dependency Closure

No new support component is required. The patch reuses `SQLitePDO` transaction state, `SQLiteInsertValuesSql` tuple parsing, `SQLitePDOStatement` fetch behavior, and `SQLiteJsonCanonical` `json`/`jsonb` scalar evaluation.
