# real-upstream-corpus-window-functions-dynamic-20260531T072006Z-0

Ported a non-overlapping dynamic window-function corpus from the hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
- Covered upstream scenarios: `window6.test` sections `1.*`, `2.*`, `5.*`, `8.*`, `9.0`, `10.1`, `10.2`, and `11.*`.
- Focus: keyword-as-identifier window syntax behavior, custom window aggregate median/sorted frame values, inverse rows frames, recursive CTE `group_concat()` frames, `nth_value()` positive-integer coercion/guards, and scalar subquery rows beside ordered window sums.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteWindow6DynamicRealCorpusTest.php`.
- The test contributes `1007` focused TestRunner PASS cases: 7 fixed upstream-shape cases plus 1000 generated dynamic cases over the same upstream behavior families.
- Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth over already mapped upstream inventory, not new denominator admission.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php && php -l lanes/libsqlite/tests/SQLiteWindow6DynamicRealCorpusTest.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindow6DynamicRealCorpusTest.php` - `1 test files, 12428 assertions, 0 failures`, with `1007` focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` - not run because the guard file is absent in this accepted worktree.
- `git diff --check -- lanes/libsqlite` - passed.

Dependency closure: no new support component is needed. This reuses the existing native PHP window frame helpers and TestRunner infrastructure; no ext/sqlite, Tcl runner, or external service is required.

Non-overlap: this does not repeat accepted `window7.test` GROUPS/RANGE aggregate frame coverage, `window4` ntile/lead/lag/nth/following-frame coverage, `windowpushd` pushdown coverage, `windowE` custom collation coverage, or JSON/window aggregate batches. It owns the `window6.test` dynamic custom-window and keyword/recursive/nth-value behavior cluster for this session.
