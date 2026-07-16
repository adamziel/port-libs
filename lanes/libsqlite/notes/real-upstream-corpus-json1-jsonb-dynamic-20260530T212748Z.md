Real upstream corpus JSON1/JSONB dynamic slice, 2026-05-30

Base accepted HEAD: 551608c47b9b5c9b4c74afdd6349b99f03720fcd
Micro-slice: real-upstream-corpus-json1-jsonb-dynamic-20260530T212748Z-0

Upstream source truth:

- /home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test
- Ported sections: json101-14.100 through json101-14.170, json101-15.100,
  json101-15.120, and json101-18.1 through json101-18.5.

Behavior covered:

- json_each/json_tree scalar-root rows report fullkey "$" for integer, real,
  text, and null scalar JSON roots.
- Uppercase JSON_EACH and parenthesized table-valued-function source shapes
  preserve object member keys, values, fullkey, and path metadata.
- Empty object keys are valid JSON object labels and are reachable through
  quoted path labels, including nested empty-label paths.
- A bare "$." path remains malformed and raises the existing JSON path error.
- The pre-existing direct SQLiteSelectExpression JSON mutation assertions now
  normalize SQLiteJsonSubtypeValue at the assertion boundary, preserving
  internal JSON subtype propagation while checking SQL-visible text.

Focused evidence:

- Before local fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  reported `1 test files, 561 assertions, 47 failures`.
- After local fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  reported `1 test files, 721 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This slice reuses existing
  SQLiteJsonEach, SQLiteJsonTree, SQLiteJsonExtract, SQLiteJsonValidity, and
  SQLiteSelectExpression JSON subtype handling.

Non-overlap:

- This does not repeat accepted json501/json502 dynamic bulk coverage,
  json104/json105/json107/json109 mutation coverage, JSON visible/hidden
  constraint pushdown, JSON table cursor/source wiring, or JSON aggregate/window
  coverage. It is limited to json101 scalar-root fullkey, table-valued function
  source-shape, and empty-key path behavior plus the local focused blocker.
