# real-upstream-corpus-expression-affinity-dynamic-20260601T185356Z-0

Lane: libsqlite
Base accepted HEAD: `c859080668b777cf0d9cae94dd5722278b285776`
Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/func2.test`

## Behavior

- Ported a real upstream `func2.test` expression-affinity cluster for `SUBSTR()` / `substr()` semantics.
- Covered upstream scenario families:
  - `func2-1.*`: ASCII `substr()` windows over `Supercalifragilisticexpialidocious`.
  - `func2-2.*`: UTF-8 `substr()` windows over `hi\u1234ho` and the single-codepoint `\u1234` value.
  - `func2-3.*`: BLOB `substr()` windows over `x'1234'`.
- Added `1,260` dynamic sqlite3-oracle cases plus source-truth and dependency-closure checks: `+1262` focused TestRunner PASS cases.
- Fixed `SQLiteCoreScalarFunction::substringWindow()` for negative-length `substr()` calls whose raw start position is beyond the end of the input. SQLite clips the raw interval `[start + length, start)`, so far-right negative windows can be empty instead of returning the clipped tail byte/character.

## Evidence

- Red-first focused run before the source fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicFunc2Substr20260601T185356ZTest.php`
  - Result: `1 test files, 7345 assertions, 56 failures`.
- Passing focused run after the source fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicFunc2Substr20260601T185356ZTest.php`
  - Result: `1 test files, 7569 assertions, 0 failures`.
- Lint run:
  - `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicFunc2Substr20260601T185356ZTest.php`
- Guard and hygiene:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`.
  - `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`.
  - `git diff --check -- lanes/libsqlite`
  - Result: clean.

## Non-Overlap

This slice owns `func2.test` substring scalar-expression behavior only. It avoids the accepted math-function, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, row-value, and LIMIT/OFFSET scalar wrapper batches.

## Dependency Closure

No new support component is needed. The patch reuses `SQLiteSelectSql` expression dispatch and `SQLiteCoreScalarFunction` `substr`, `quote`, `length`, and `octet_length` behavior against a local `sqlite3` oracle.
