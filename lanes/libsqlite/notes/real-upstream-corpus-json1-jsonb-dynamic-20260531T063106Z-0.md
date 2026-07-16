# real-upstream-corpus-json1-jsonb-dynamic-20260531T063106Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`

Ported behavior:

- `json102-250` through `json102-310`: multi-path `json_extract()` returns a
  JSON array result for mixed scalar, object, missing, and nested paths.
- `json105-1`: reverse `#-N` array-index paths are accepted in JSON path
  extraction.

This slice adds `SQLiteRealUpstreamJson102105SelectExpressionDynamicTest.php`,
which routes those upstream behaviors through `SQLiteSelectExpression` instead
of the direct JSON helper path. Each generated case verifies text input,
JSONB input, `jsonb_extract()` output, JSON subtype preservation for
multi-path `json_extract()`, and canonical text/JSONB parity.

Focused evidence:

- First red run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102105SelectExpressionDynamicTest.php`
  failed because the SELECT-expression dispatcher correctly preserves
  `SQLiteJsonSubtypeValue` wrappers for multi-path `json_extract()` results.
- Final run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102105SelectExpressionDynamicTest.php`
  passed with `1 test files, 8004 assertions, 0 failures` and `1001` PASS
  lines.
- Syntax: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102105SelectExpressionDynamicTest.php`
  reported no syntax errors.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible
  constraint work, JSON aggregate/window behavior, JSONB malformed/remove
  corpus coverage, or direct helper-only JSON102/JSON105 extraction tests.
- The new selected PASS growth is from SELECT-expression dispatcher coverage
  over real upstream JSON102/JSON105 path semantics.

Dependency closure:

- No new support component is needed. The slice reuses existing native
  `SQLiteSelectExpression`, `SQLiteJsonExtract`, JSON subtype, and JSONB
  components.
