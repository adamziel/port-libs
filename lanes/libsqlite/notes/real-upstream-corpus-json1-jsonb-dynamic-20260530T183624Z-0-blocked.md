# real-upstream-corpus-json1-jsonb-dynamic-20260530T183624Z-0 blocked

Base accepted HEAD: `365df791b359e0dd925a461a6d36ddf8a8d0f5f1`

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

Blocker:

The current base already contains `lanes/libsqlite/tests/SQLiteRealUpstreamJsonInvariantDynamicTest.php`, which ports upstream `json106` and `json108` dynamic JSON/JSONB invariants and passes with `566` focused assertions. The remaining real upstream JSON1/JSONB corpus has enough material, but this micro-slice cannot honestly meet the active hard handoff floor without first adding a reusable Tcl-to-PHP JSON corpus extraction/admission tool or manually porting a much larger batch. A small hand-port of `json101`, `json102`, or `jsonb01` constructor/removal rows would be below the required gate and would mostly overlap accepted JSON constructor, path, JSONB, table cursor/source, visible/hidden constraint, aggregate/window, and malformed-edge coverage.

Focused evidence collected:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonInvariantDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS real upstream json106 scalar tree atoms match path extraction
PASS real upstream json106 remove then insert restores scalar leaves
PASS real upstream json106 merge patch preserves patch scalar leaves
PASS real upstream json108 pretty output canonicalizes to original json
PASS real upstream json106 jsonb patch and pretty round trip parity

1 test files, 566 assertions, 0 failures
```

Next larger batch to try:

Build a lane-local JSON corpus adapter that consumes real `do_execsql_test` and `foreach` rows from `json101.test`, `json102.test`, `json105.test`, and `jsonb01.test`, maps the SQL function calls to existing native helpers (`SQLiteJsonConstructor`, `SQLiteJsonCanonical`, `SQLiteJsonExtract`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonPatch`, `SQLiteJsonValidity`, `SQLiteJsonTree`, and `SQLiteJsonB`), and emits one self-contained PHP focused test with at least `5,000` distinct behavior assertions. The first non-overlapping target should emphasize `json102` documentation-generated scalar/path/mutation cases plus `jsonb01` JSONB removal path cases, because those can be tied directly to upstream scenario names and avoid JSON table planner/source surfaces already accepted.

Dependency-closure note:

No new external support component is needed. The missing blocker is a bounded lane-local corpus extraction/admission helper or a manually curated high-volume PHP port of the real upstream JSON files.
