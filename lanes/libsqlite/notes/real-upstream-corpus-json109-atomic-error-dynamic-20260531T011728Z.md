# real-upstream-corpus-json109-atomic-error-dynamic-20260531T011728Z

- Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`.
- Upstream scenario: `json109-2.8`, sequential `json_array_insert()` path/value pairs where an earlier pair can mutate the working document but a later path is not an array element and the SQL call returns an error.
- Added PHP focused coverage: `lanes/libsqlite/tests/SQLiteRealUpstreamJson109AtomicErrorDynamicTest.php`.
- Focused movement: `721` distinct TestRunner PASS cases, `1203` assertions, `0` failures.
- Non-overlap: this does not repeat existing `json109` successful root/nested insert matrix coverage, JSON table cursor/source/constraint coverage, JSON object aggregate/window coverage, JSONB remove coverage, or accepted JSON visible/hidden constraint pushdown. It covers the mixed sequential-pair error path from `json109-2.8` across text, JSONB, and SELECT-expression dispatch.
- Dependency closure: no new support component needed; existing native `SQLiteJsonArrayInsert`, `SQLiteJsonB`, `SQLiteJson5Parser`, and `SQLiteSelectExpression` components are reused.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109AtomicErrorDynamicTest.php` -> `1 test files, 1203 assertions, 0 failures`.
