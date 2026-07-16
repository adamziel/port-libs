# real-upstream-corpus-expression-affinity-dynamic-20260531T230806Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported `e_expr-27.4`, `e_expr-28.1`, and `e_expr-33.1` encoding-sensitive TEXT/BLOB CAST behavior into a dynamic PHP corpus.

## Handoff Delta

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicEncodingCast20260531T230806ZTest.php`.
- The shard generates 1000 distinct focused TestRunner cases over UTF-8, UTF-16LE, and UTF-16BE database encodings.
- The oracle is the local `sqlite3` CLI with `PRAGMA encoding` per encoding; the PHP side exercises `SQLiteRealExpressionAffinityCorpusPlan::castTextBlobWithEncoding()`.

## Non-Overlap

- This owns `e_expr-33` encoding-sensitive non-BLOB-to-BLOB and BLOB-to-TEXT casts.
- It avoids accepted numeric CAST-prefix, `e_expr-29` through `e_expr-32` REAL/INTEGER/NUMERIC casts, UTF-16 malformed insert guards, Unicode GLOB ranges, named/unbound parameter syntax, CASE/iif, IN/BETWEEN, JSON, WAL, VFS, B-tree, trigger, and PRAGMA corpus clusters.

## Dependency Closure

- No new support component is needed. The test reuses native SQLite UTF-8, UTF-16LE, and UTF-16BE encode/decode helpers already present in libsqlite.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicEncodingCast20260531T230806ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicEncodingCast20260531T230806ZTest.php`
  - `1 test files, 4010 assertions, 0 failures`
  - 1001 focused TestRunner PASS cases
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
