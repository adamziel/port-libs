# PRAGMA Synchronous/Journal Corpus Next7

This slice adds bounded native PHP coverage for upstream-style `PRAGMA synchronous` and `PRAGMA journal_mode` state handling without repeating accepted VFS writer, rollback-journal commit/apply, WAL checkpoint, super-journal, or pager file-sync clusters.

Focused behavior:

- Parses `PRAGMA synchronous` and `PRAGMA journal_mode` query and assignment forms, including schema-qualified, equals, parenthesized, uppercase, numeric synchronous, and trailing-semicolon SQL.
- Normalizes SQLite synchronous modes `OFF/NORMAL/FULL/EXTRA` to `0/1/2/3`.
- Tracks journal mode state for `DELETE`, `TRUNCATE`, `PERSIST`, `MEMORY`, `WAL`, and `OFF`.
- Keeps main, temp, and attached schema state isolated.
- Preserves SQLite-style temp/memory/WAL constraints and WAL default synchronous downgrade from FULL to NORMAL.
- Returns SQLite-shaped one-column rows for PRAGMA query/assignment output.

Verification from this worktree:

- `php -l lanes/libsqlite/src/SQLitePragmaJournalState.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaSynchronousJournalCorpusTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-synchronous-journal.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSynchronousJournalCorpusTest.php`: `1 test files, 54 assertions, 0 failures` with 54 PASS lines.
- `php lanes/libsqlite/examples/application-pragma-synchronous-journal.php`: printed WAL main journal mode, NORMAL synchronous, temp journal-mode fallback to DELETE, and pragma-state dependency tags.
- `git diff --check -- lanes/libsqlite`: no whitespace errors.

Dashboard delta:

- `phpPass`: `2017 -> 2071` (`+54` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this adds lane-scoped PHP corpus coverage only.

Non-overlap: avoids accepted PRAGMA locking-mode, schema PRAGMA catalog, integrity/quick_check, VFS writer/sync/lock, rollback-journal commit/apply, WAL byte truncation/checkpoint transaction, super-journal, grouped SELECT, JSON table, Unicode GLOB, B-tree page move/root-collapse/overflow freelist, and upstream batch5a corpus clusters.

Dependency closure: no new support component is needed; the work reuses lane-local PRAGMA parsing/state conventions and does not require ext/sqlite or live-service provider tests.
