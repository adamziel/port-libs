# libsqlite jsonb-malformed-edge-dispatch integration

- Handoff DB id: `296`
- Slice: `jsonb-malformed-edge-dispatch`
- Source commit: `b97407b4acd40b0c06e4534790b8695b41690171`
- Patch: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-jsonb-20260527T124524Z.patch`
- Patch sha256: `685107dfc6eb01165fab1e00a59cecff98d7e3fd52cb3e06fc8612e658114f19`
- Apply decision: plain `git apply --check` failed only on libsqlite status/notes drift; `git apply --3way` applied code/tests/example cleanly and left trivial status/notes conflicts. Resolved by preserving current accepted status/notes and adding this slice's JSONB status text.
- Dependency decision: no new shared dependency component. The slice reuses `SQLiteBlobValue`, existing JSONB validation, `SQLiteJsonTablePlan`, and the SELECT SQL dispatcher.

## Files changed

- `lanes/libsqlite/src/SQLiteSelectSql.php`
- `lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `lanes/libsqlite/examples/wordpress-select-sql-json-malformed-jsonb-join.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/wordpress-scenarios.md`
- `audits/reorg/integrate-sqlite-jsonb-20260527T145500Z.md`

## Focused verification

- Ready marker hash: matched patch sha256 `685107dfc6eb01165fab1e00a59cecff98d7e3fd52cb3e06fc8612e658114f19`.
- Metadata/log inspection: worker evidence showed the slice added SELECT SQL `X'...'` BLOB literal parsing and routed SQL-literal JSONB BLOBs through `json_each()` / `json_tree()` validation; worker reported focused lint, focused test, WordPress smoke, JSON validation, and lane diff checks passing.
- `php -l lanes/libsqlite/examples/wordpress-select-sql-json-malformed-jsonb-join.php`: passed.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `1 test files, 9337 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-select-sql-json-malformed-jsonb-join.php`: passed; valid SQL-literal JSONB rows expanded and malformed literal JSONB hidden constraints returned an empty rowset.
- `git diff --check -- lanes/libsqlite audits/reorg`: passed before root.
- Exact process-table check for no-argument root harness: no matching `php tools/run-tests.php` process was running before root.

## Root verification

- Command: `TMPDIR="$PWD/.tmp-root" php tools/run-tests.php`
- Result: `215 test files, 34617 assertions, 0 failures`.
