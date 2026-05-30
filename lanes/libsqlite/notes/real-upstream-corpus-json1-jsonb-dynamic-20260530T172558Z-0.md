# real-upstream-corpus-json1-jsonb-dynamic-20260530T172558Z-0

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T172558Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`

Ported behavior:

- `json106` invariant family: scalar `json_tree()` atoms match dynamic
  `json_extract()` path lookups for strict JSON and JSON5 input.
- `json106` remove/insert invariant: removing a non-array scalar leaf and
  reinserting the atom at the same path restores the leaf value.
- `json106` merge-patch invariant: non-null scalar leaves from the patch
  document are visible at the same dynamic paths after `json_patch()` /
  `jsonb_patch()`.
- `json108-1.1` through `json108-1.5`: `json_pretty(JSON, INDENT)` output
  canonicalizes back to the original JSON for `NULL`, empty, tab, and custom
  comment-like indentation strings, including JSONB input.

Focused native coverage:

- Added `SQLiteRealUpstreamJsonInvariantDynamicTest.php`.
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonInvariantDynamicTest.php`
- Result: `1 test files, 566 assertions, 0 failures`.

Non-overlap:

This slice does not add JSON table cursor/source/hidden/visible constraint
coverage, JSON aggregate/window coverage, `json105.test` reverse/append path
coverage, `json109.test` array-insert coverage, source-neutral cleanup, or
metadata-only runner rows. It focuses only on upstream `json106.test` and
`json108.test` dynamic invariant behavior over existing native JSON helpers.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP JSON
helpers: `SQLiteJsonTree`, `SQLiteJsonExtract`, `SQLiteJsonRemove`,
`SQLiteJsonMutation`, `SQLiteJsonPatch`, `SQLiteJsonPretty`, `SQLiteJsonB`,
and `SQLiteJsonValidity`.
