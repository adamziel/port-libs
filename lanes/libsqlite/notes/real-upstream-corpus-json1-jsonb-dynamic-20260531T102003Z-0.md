# real-upstream-corpus-json1-jsonb-dynamic-20260531T102003Z-0

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `json102-1010`: scalarize one-element phone arrays with `json_extract(phone,'$[0]')`.
- `json102-1011`: combine scalar `phone LIKE '704-%'` with guarded dynamic `json_each(user.phone)` over remaining JSON arrays.

Implementation delta:

- `SQLiteSelectSql` now treats bare truthy `json_valid(column)` predicates as dynamic JSON table error boundaries, matching the existing `json_valid(column)=1` and `json_error_position(column)=0` boundary handling.
- This lets `json_each(user.phone)` skip scalar phone rows after the upstream-style `json102-1010` update instead of opening scalar text and throwing `SQLite JSON5 input has trailing content`.

Focused test delta:

- Added `SQLiteRealUpstreamJson102MixedPhoneGuardDynamicTest.php` with 1000 generated upstream-backed mixed phone cases plus citation and dependency checks.
- New focused PASS cases: +1002.
- Focused assertion count: 8007.

Red-before evidence:

- The upstream-style `json102-1011` query over a scalarized one-phone row and JSON-array multi-phone rows threw `InvalidArgumentException: SQLite JSON5 input has trailing content` before the guard recognized bare `json_valid(user.phone)`.

Non-overlap:

- This does not repeat the recent JSON101 correlated `json_each` EXISTS slice, JSON table cursor/source wiring, hidden/visible constraint extraction, JSON aggregate/window behavior, or JSON102 operator/mutation/lexical batches.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, dynamic JSON table sources, `SQLiteJsonExtract`, `SQLiteJsonValidity`, `LIKE`, `UNION`, and the existing JSON table error-boundary flow.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MixedPhoneGuardDynamicTest.php`
  - `1 test files, 8007 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102MixedPhoneGuardDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101CorrelatedEachDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `3 test files, 24018 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102MixedPhoneGuardDynamicTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - pass
