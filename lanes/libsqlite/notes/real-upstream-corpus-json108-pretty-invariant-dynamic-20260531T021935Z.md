# real-upstream-corpus-json108-pretty-invariant-dynamic-20260531T021935Z

## Source

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
- Upstream scenarios: `json108-1.1` through `json108-1.5`
- Ported behavior: `json(json_pretty(input, indent))` preserves canonical JSON identity for strict JSON, JSON5 text, and JSONB-backed values across dynamic nested inputs and the upstream indent classes `NULL`, empty string, tab, and comment-style indentation.

## Focused Delta

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson108PrettyInvariantDynamicTest.php`.
- Adds 1,001 distinct TestRunner cases: 1,000 dynamic invariant cases plus one source-citation case.
- Each dynamic case checks strict JSON, JSON5, JSONB, path extraction, JSONB round-trip text, `json_tree` shape, and canonical identity.
- Non-overlap: this does not repeat accepted JSON102/JSON106/JSON502 mutation/path/escaped-path work, JSON table source/cursor/constraint work, JSON107 BLOB text behavior, or JSON109 array-insert behavior. It targets the upstream `json108.test` pretty-invariant random JSON cluster.

## Dependency Closure

No new support component is needed. This reuses existing native JSON5 decoding, JSONB encoding/decoding, `json_pretty`, `json_tree`, and JSON path extraction primitives.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson108PrettyInvariantDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson108PrettyInvariantDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson108PrettyInvariantDynamicTest.php`
  - `1 test files, 12004 assertions, 0 failures`
  - 1,001 focused PASS lines: 1,000 dynamic cases plus the source-citation case.
- Generic API guard test
  - Not run: the guard file named by the supervisor is absent in this worktree (`Focused path does not exist in repository`).
- `python -m json.tool lanes/libsqlite/lane-status.json >/dev/null`
  - Passed.
- `git diff --check -- lanes/libsqlite`
  - Passed.

Root harness: not run - isolated micro-slice.
