# libsqlite scalar subquery expression integration

- Handoff: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-cor-exists-20260527T150110Z.ready`
- Slice label: `select-correlated-exists-current`
- Integrated behavior: scalar SELECT subquery expressions in projection, predicate operands, composed expressions, and expression ORDER BY.
- Source commit: `8edae61d3cb302d368799ca8f16e1adda1420341`
- Patch: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-cor-exists-20260527T150110Z.patch`
- Patch sha256: `91b42792349244a1320c979163907b012e6eef9bcad61b276d10a88c444173c4`
- Apply decision: clean `git apply --check` from current source.
- Dependency decision: no new shared dependency component. The slice reuses the SELECT SQL parser, correlated row execution, row-array expression evaluation, and WordPress option fixtures.

## Files changed

- `lanes/libsqlite/src/SQLiteSelectExpression.php`
- `lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `lanes/libsqlite/src/SQLiteSelectProjection.php`
- `lanes/libsqlite/src/SQLiteSelectSql.php`
- `lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `lanes/libsqlite/examples/wordpress-select-sql-scalar-subquery.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/wordpress-scenarios.md`

## Verification

- Patch hash matched the handoff metadata.
- `php -l` passed for every changed PHP file.
- `php -r` JSON validation for `lanes/libsqlite/lane-status.json`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `1 test files, 9377 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-select-sql-scalar-subquery.php`: passed and emitted copied `wp_options` scalar-subquery diagnostics.
- `git diff --check -- lanes/libsqlite`: passed.
- Exact process-table check found no active no-argument `php tools/run-tests.php` before root.
- Root command: `TMPDIR="$PWD/.tmp-root" php tools/run-tests.php`
- Root result: `215 test files, 34657 assertions, 0 failures`.
