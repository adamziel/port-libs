# PRAGMA Integrity Current Next29

This slice extends native PHP `PRAGMA integrity_check` for auto-vacuum
pointer-map/free-list consistency. It is intentionally narrower than the
accepted deep b-tree integrity walk: the new checks verify that pages marked
`free-page` in the pointer map are reachable from the freelist, and that
reachable freelist trunk/leaf pages have `free-page` pointer-map entries with
parent `0`.

Focused behavior:

- `PRAGMA integrity_check` reports pointer-map free pages that are not reachable
  from the freelist.
- `PRAGMA integrity_check` reports freelist trunk and leaf pages whose
  pointer-map type is not `free-page`.
- `PRAGMA integrity_check` reports freelist pointer-map parent values other
  than `0`.
- `PRAGMA quick_check` remains shallow and skips the pointer-map/free-list
  cross-check, while retaining header/freelist count checks.

Verification from this worktree:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityCurrentNext29Test.php`: `1 test files, 53 assertions, 0 failures` with 53 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaIntegrityDeepCurrentNext19Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityCurrentNext29Test.php`: `3 test files, 118 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: printed quick-check `ok`, limited header corruption, deep b-tree `ok`, and pointer-map/free-list mismatch rows for copied `wp_options`-style page images.
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityCheck.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityCurrentNext29Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: no syntax errors.
- `git diff --check -- lanes/libsqlite`: no whitespace errors.

Dashboard delta:

- Adds 53 new focused PASS lines in `SQLitePragmaIntegrityCurrentNext29Test.php`.
- `lane-status.json` `phpPass` moves from 10028 to 10081 in this isolated
  worktree.
- `benchmarkDenominator.mapped` is unchanged; this is focused behavior/test
  growth, not new upstream inventory admission.

Non-overlap:

- Avoids accepted deep b-tree page integrity, overflow-chain integrity,
  PRAGMA schema catalog/runtime metadata, VFS writer/sync/lock/rollback,
  WAL checkpoint/savepoint byte truncation, JSON table source/cursor/constraint
  clusters, Unicode GLOB, SELECT SQL text/subquery/GROUP/ORDER/LIMIT clusters,
  B-tree page move/root-collapse/overflow-freelist release, and batch23
  PRAGMA function/module/collation metadata.

Dependency closure:

- No new support component is needed. The implementation reuses existing
  native PHP SQLite database/header, freelist trunk, pointer-map, and b-tree
  page primitives.
