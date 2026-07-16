## real-upstream-corpus-json1-jsonb-dynamic-20260531T034156Z-0

Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported scenario range: `json101-10.1` through `json101-10.95`, the strict JSON string backslash-escape validity matrix.

Implemented lane-local focused coverage:

- Added `SQLiteRealUpstreamJson101EscapeDynamicCorpusTest.php`.
- Covers all 99 upstream escape rows across direct `json_valid`, SQL-function argument dispatch, text BLOB compatibility, JSONB conversion/admission parity for valid strict escapes, JSON subtype boundaries, parser-level `SQLiteSelectSql` `json_valid(...)`, and canonical/extract/error-position checks.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101EscapeDynamicCorpusTest.php` => `1 test files, 1380 assertions, 0 failures`.

Non-overlap:

- Does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON host joins, JSON aggregate/window, json_patch/json_pretty bulk invariants, JSON105 negative-index mutation, or JSONB malformed planner clusters.
- This slice targets the upstream `json101.test` strict escape matrix specifically.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded native PHP JSON validity, canonicalization, JSONB, subtype, and SELECT-expression support.

Root harness:

- Not run - isolated micro-slice.
