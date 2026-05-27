# Integrate libsqlite JSON table disjunctive pushdown

- Handoff DB id: 299
- Slice: `json-table-constraint-pushdown-planner`
- Source commit: `f55ee1c3e732936efd3a956892263e05fb0faef8`
- Patch: `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-dev-sqlite-json-table-20260527T124524Z.patch`
- Patch sha256: `d58a59a1d7da8766416ae7f7967a286abefab1ad1d074f65b1e1b2e49c7b208a`

## Apply

- Ready marker hash verified against patch sha256.
- `git apply --check` failed on current accepted baseline due to drift in `UPSTREAM_TEST_MANIFEST.json`, `lane-status.json`, and `notes/wordpress-scenarios.md`.
- `git apply --3way` applied behavior files cleanly and conflicted only in lane status/manifest/notes drift.
- Resolution kept current accepted manifest/status counters, preserved the submitted scenario note, and did not rewrite the submitted behavior.

## Focused Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php`: passed.
- `php -l lanes/libsqlite/examples/wordpress-json-table-disjunctive-pushdown.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `1 test files, 9227 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-json-table-disjunctive-pushdown.php`: passed; reported 3 planned branches and 6 filtered rows.
- `git diff --check -- lanes/libsqlite audits/reorg`: passed.

## Root Verification

- Exact process-table check for an already-running no-argument root harness found no `php tools/run-tests.php` process.
- `TMPDIR="$PWD/.tmp-root" php tools/run-tests.php`: `215 test files, 34507 assertions, 0 failures`.

## Dependency Decision

No new shared dependency component is needed. The slice reuses lane-local JSON table planning, JSON parsing, residual predicate comparison, and pure PHP row arrays.

## Files Changed

- `lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `lanes/libsqlite/examples/wordpress-json-table-disjunctive-pushdown.php`
- `lanes/libsqlite/notes/wordpress-scenarios.md`
- `audits/reorg/integrate-sqlite-json-table-20260527T143344Z.md`
