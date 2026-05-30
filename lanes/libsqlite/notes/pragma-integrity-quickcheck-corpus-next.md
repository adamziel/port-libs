# PRAGMA Integrity/Quick Check Corpus Next

This slice adds bounded native PHP coverage for upstream-style `PRAGMA integrity_check` and `PRAGMA quick_check` behavior without repeating accepted schema PRAGMA catalog work.

Focused behavior:

- Parses `PRAGMA integrity_check`, `PRAGMA quick_check`, schema-qualified forms, parenthesized limits, equals limits, and trailing semicolons.
- Returns SQLite-style one-column row sets with `ok` for clean images and bounded error rows for corrupt images.
- Checks header/page-count invariants, read/write version bytes, text encoding, freelist trunk/leaf reachability, freelist count agreement, and auto-vacuum pointer-map entry validity.
- Preserves the quick-check distinction by skipping the deeper pointer-map pass while still reporting header and freelist corruption.

Verification from this worktree:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php`: `1 test files, 34 assertions, 0 failures` with 34 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests`: `14 test files, 11204 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: printed clean `quick_check` `ok` plus limited `integrity_check` invalid text encoding diagnostics.
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityCheck.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-integrity-quickcheck.php`: no syntax errors.
- `git diff --check -- lanes/libsqlite`: no whitespace errors.

Non-overlap: this avoids accepted schema PRAGMA/DDL catalog rows, VFS writer/sync/rollback apply, B-tree page relocation/root collapse/overflow freelist release, JSON table cursor/source/constraint pushdown, Unicode GLOB, SELECT SQL subqueries/grouping/comma LIMIT, and WAL checkpoint/savepoint byte truncation clusters.

Dependency closure: no new support component is needed; the checker reuses existing native PHP SQLite header, database page, freelist trunk, and pointer-map primitives.
