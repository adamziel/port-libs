# real-upstream-corpus-window-functions-dynamic-20260601T174721Z-0

Source truth:

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Upstream scenarios: `window1.test` `do_execsql_test 76.0` through `76.5`
- Forum reference embedded upstream: `https://sqlite.org/forum/forumpost/0d48347967`

Behavior admitted:

- `76.1`: LEFT JOIN against a derived `SELECT +a AS c` source, `GROUP BY c`, and a correlated scalar subquery containing `sum(1) OVER ()`.
- `76.3`: LEFT JOIN NULL-extension remains visible inside scalar subquery `y+sum(0) OVER ()`.
- `76.4`: the same NULL-extension behavior remains stable after `GROUP BY x`.
- `76.5`: `max(y)+sum(0) OVER ()` preserves the unmatched-left-row NULL result after grouping.

Focused test movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeftJoinScalarDynamic20260601Test.php`
- Adds 1,004 focused PASS cases:
  - 1 hydrated upstream source-truth guard.
  - 2 exact upstream section tests.
  - 1,000 dynamic LEFT JOIN/scalar-window variants.
  - 1 non-overlap/dependency-closure guard.
- Adds 10,021 focused assertions from real upstream `window1.test` section 76 behavior.
- Expected `phpPass` movement: `6156557 -> 6157561` (`+1004` focused PASS cases).
- Mapped denominator coverage is unchanged because the accepted dashboard already reports `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeftJoinScalarDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeftJoinScalarDynamic20260601Test.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1LeftJoinScalarDynamic20260601Test.php`
  - `1 test files, 10021 assertions, 0 failures`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

Non-overlap:

- This slice avoids existing window1 sections 57, 58, 61, 66, 78, and 79 coverage, window3 value-navigation coverage, windowB JSON inverse coverage, windowC separator coverage, windowD boolean-view coverage, windowE collation/range coverage, and windowpushd planner coverage.
- No generated fake upstream script ids or metadata-only rows were added.

Dependency closure:

- No new support component is needed.
- The slice reuses existing `SQLiteSelectSql` LEFT JOIN, derived source, scalar subquery, `GROUP BY`, and window aggregate execution.
