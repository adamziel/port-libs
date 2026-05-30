# Real Upstream JSON1/JSONB Dynamic Corpus

Session: `port-dev-sqlite-yield-dyn-real-json-20260530T161732Z`
Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

## Ported Behavior

- `json101-1.1.*`, `json101-2.*`, `json102-100` through `json102-180` constructor and canonical JSON/JSONB value-subtype behavior.
- `json102-250` through `json102-310` plus dynamic negative-index extract coverage for `json_extract()` and `jsonb_extract()`.
- `json101-4.1`, `json101-4.4`, `json102-190`, `json102-220`, and `json102-240` validity, type, and array-length behavior.
- `json101-3.*` and `json102-320` through `json102-410` mutation behavior for `json_insert()`, `json_replace()`, and `json_set()` with JSON subtype and JSONB values.
- `jsonb01-1.2.1` through `jsonb01-1.2.18` JSONB and text remove behavior, including `[#-N]` paths.
- `jsonb01-2.0` malformed JSONB operator rejection.

## Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
  - Result: `1 test files, 529 assertions, 0 failures`
  - PASS lines: `405`

## Non-Overlap

This batch does not add JSON table cursor/source/hidden/visible constraint planner rows, JSON aggregate window behavior, JSONB generated-index behavior, or suite denominator admission metadata. It exercises native JSON1/JSONB scalar behavior from real hydrated upstream Tcl scripts.

## Dependency Closure

No new support component is needed. The batch reuses existing native PHP `SQLiteJsonB`, `SQLiteJsonCanonical`, `SQLiteJsonConstructor`, `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, `SQLiteJsonValidity`, and `SQLiteSelectExpression` support.
