# real-upstream-corpus-select-core-dynamic-20260530T194252Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`

Focused upstream scenarios:

- `select2-1.1` nested driver query shape: `SELECT DISTINCT f1 FROM tbl1 ORDER BY f1`, followed by ordered `f2` lookups per `f1`.
- `select2-1.2` bounded DISTINCT query over `f1>3 AND f1<5`.
- `select2-4.1` through `select2-4.5` cross join predicate truthiness using `max(a,b)`, bare column truthiness, `NOT`, and `min(a,b)`.
- `select1.test` is cited as same SELECT core family source for basic projection/join coverage already exercised by `SQLiteSelectSql`.

New focused PHP test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedDynamicTest.php`

The batch is non-overlapping with the current accepted SELECT8 grouped LIMIT/OFFSET and SELECT9 compound LIMIT/OFFSET files. It targets ordinary core SELECT DISTINCT/range/ORDER/LIMIT behavior plus cross-table predicate truthiness from `select2.test`.

Expected dashboard movement:

- Countable as focused PHP PASS-line growth.
- No mapped denominator change; these are additional hydrated upstream behavior assertions for already mapped SELECT core corpus files.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, scalar expression, predicate, ORDER BY, LIMIT/OFFSET, and CROSS JOIN execution.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedDynamicTest.php` passed: `1 test files, 24883 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedDynamicTest.php | rg -c '^PASS '` returned `4979`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect2NestedDynamicTest.php` passed.
- `git diff --check -- lanes/libsqlite` passed.
- No no-domain API guard file exists in this worktree.
