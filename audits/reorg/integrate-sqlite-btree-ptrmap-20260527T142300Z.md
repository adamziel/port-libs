# Integrate SQLite B-tree Pointer-map Apply

- Handoff DB id: `300`
- Slice: `btree-pointer-map-autovacuum-apply`
- Source commit: `f9f659591eccf2c0cdc06469b740fdebaeed7a6d`
- Patch: `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-dev-sqlite-btree-ptrmap-20260527T124524Z.patch`
- Patch sha256: `34aa95dc765145cec48a0fc78a209bf2b22ca89bab85f71a9d546b1ca761c7f0`

## Apply Decision

Ready marker hash was present and matched the patch. Plain `git apply --check`
reported drift in `lanes/libsqlite/lane-status.json` and
`lanes/libsqlite/notes/rework-closure.md`, so the patch was applied with
`git apply --3way`. The conflicts were limited to status/notes drift from newer
accepted libsqlite commits and were resolved additively. The submitted behavior
under `lanes/libsqlite/src`, `tests`, and `examples` applied cleanly.

Dependency decision: no new shared dependency component is needed. The slice
reuses the lane-local pointer-map planner, B-tree/page-image fixtures, SQLite
database reader, and pure PHP WordPress option rows.

## Focused Verification

```sh
php -l lanes/libsqlite/src/SQLiteAutoVacuumPointerMapApplyPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-autovacuum-pointer-map-apply.php
```

Result: all changed PHP files reported no syntax errors.

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result: `1 test files, 9201 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/wordpress-autovacuum-pointer-map-apply.php
```

Result: passed and reported `wordpress-autovacuum-pointer-map-apply` with
updated page numbers `[2, 105, 106]`, pointer-map pages `[2, 105]`, and
retargeted free, B-tree, first-overflow, and overflow pointer-map entries.

```sh
git diff --check -- lanes/libsqlite audits/reorg
```

Result: passed.

## Root Verification

Exact process-table check before root:

```sh
ps -eo pid=,args= | awk '$0 ~ /php tools\/run-tests\.php$/ { print }'
```

Result: no no-argument root harness was already running.

```sh
TMPDIR="$PWD/.tmp-root" php tools/run-tests.php
```

Result: `215 test files, 34481 assertions, 0 failures`.

## Files Changed

- `lanes/libsqlite/examples/wordpress-autovacuum-pointer-map-apply.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/rework-closure.md`
- `lanes/libsqlite/notes/wordpress-scenarios.md`
- `lanes/libsqlite/src/SQLiteAutoVacuumPointerMapApplyPlan.php`
- `lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `audits/reorg/integrate-sqlite-btree-ptrmap-20260527T142300Z.md`
