# real-upstream-corpus-json1-jsonb-dynamic-20260601T091120Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Upstream sections `json102-1600`, `json102-1610`, and `json102-1620`.

Behavior ported:
- Added SQLite `subtype()` scalar dispatch for SELECT SQL expression execution.
- `subtype()` returns SQLite's JSON subtype code `74` for JSON subtype values produced by JSON constructors and `->` operators, and `0` for SQL scalars, NULL, and JSONB blob values.
- `typeof()` now treats JSON subtype values as SQLite `text`, matching upstream JSON operator behavior.
- Added a 1000-case dynamic corpus that drives object-member, array-index, `if(json_valid(...), x->y)`, `->`, `->>`, `json_extract()`, and JSONB-input parity through `SQLiteSelectSql`.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102SubtypeSelectSqlDynamic20260601Test.php`
- Result: `1 test files, 226009 assertions, 0 failures`.
- PASS delta: 1002 distinct TestRunner PASS cases.

Non-overlap:
- This slice does not repeat JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window coverage, JSON path diagnostics, JSONB malformed operator diagnostics, or JSON102 tree scan/projection rows already present elsewhere.
- The owned gap is parser-level SELECT SQL `subtype()` and JSON-subtype/`typeof()` behavior for upstream json102 object/array operator sections.

Dependency closure:
- No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteCoreScalarFunction`, existing JSON operator dispatch, JSONB extraction, and row-array execution.
