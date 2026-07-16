# real-upstream-corpus-json1-jsonb-dynamic-20260531T095201Z-0

Base accepted HEAD: `39bb58e3950abcc0370640338af645050eeb5116`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `json101-13.100`: planner/executor must know `json_each(t1.json,'$.items')` arguments inside a correlated `EXISTS` subquery for `t1 CROSS JOIN t2`.
- `json101-13.110`: same correlated table-valued function argument behavior with `t2 CROSS JOIN t1`.
- SQLite ticket cited by upstream comments: `80177f0c226ff54f6ddd41`.

Behavior implemented:

- `SQLiteSelectSql::sourcePlan()` now seeds no-join dynamic table sources with the qualified correlated outer row instead of an empty row.
- This lets dynamic `json_each()`/`json_tree()` sources inside correlated subqueries evaluate arguments such as `t1.json`, `t1.docb`, and compare generated rows against the current outer `t2.id`.
- The focused test expands the two upstream JSON1 scenarios into 1,000 dynamic corpus cases, covering both join orders and both JSON text and JSONB inputs.

Red-before evidence:

- Before the source change, a direct reproduction of `json101-13.100` failed with:
  `InvalidArgumentException: SQLite SELECT expression row is missing column t1.json`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101CorrelatedEachDynamicTest.php`
  - `1 test files, 16008 assertions, 0 failures`
  - Adds `1002` distinct TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101CorrelatedEachDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103SelectSqlDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 41466 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101CorrelatedEachDynamicTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - clean

Expected dashboard movement:

- `phpPass`: `+1002` focused PASS cases if accepted.
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth over already mapped upstream `json101.test`.

Non-overlap:

- Does not repeat accepted JSON table cursor/source wiring, JSON hidden or visible constraint pushdown, JSON109 array insertion SELECT SQL, JSON table null path, JSON subtype handoff, JSON aggregate/window, or JSON malformed planner diagnostics.
- This patch specifically covers correlated dynamic table-valued function arguments in `EXISTS` subqueries.

Dependency closure:

- No new support component is needed. The change reuses existing `SQLiteSelectSql` correlated-row qualification and `SQLiteJsonTablePlan` row materialization.
