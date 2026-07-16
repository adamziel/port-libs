# Savepoint Statement-Journal Error Edge Next14

## Behavior

Added bounded statement-journal rollback state to `SQLiteSavepointStack` for a failed statement inside an open savepoint. The new behavior records the statement start WAL frame, captures the first before-image for pages dirtied by the statement, reports the discarded statement WAL frames, restores the failed statement's database page images, rolls pending WAL frame state back to the statement start, and leaves the enclosing savepoint/transaction active.

This is distinct from accepted savepoint page-image rollback and WAL byte truncation: it models SQLite's statement subjournal edge for statement-abort errors inside a savepoint, where the statement is undone but the surrounding savepoint remains usable.

## Application Smoke

`lanes/libsqlite/examples/application-savepoint-statement-journal-error.php` simulates a copied `wp_options` plugin import where a duplicate option insert fails inside `SAVEPOINT plugin-options`. The smoke reports restored statement table/index pages, discarded statement WAL frames, active savepoints, and cleared statement journals without requiring `ext/sqlite`.

## Verification

Focused new corpus:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointStatementJournalErrorCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
```

Savepoint regression focus:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointReleaseCorpusTest.php lanes/libsqlite/tests/SQLitePagerWalSavepointCorpusTest.php lanes/libsqlite/tests/SQLiteSavepointStatementJournalErrorCorpusTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 112 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLiteSavepointStatementJournalErrorCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteSavepointStatementJournalErrorCorpusTest.php
php -l lanes/libsqlite/examples/application-savepoint-statement-journal-error.php
No syntax errors detected in lanes/libsqlite/examples/application-savepoint-statement-journal-error.php
```

Example smoke:

```text
php lanes/libsqlite/examples/application-savepoint-statement-journal-error.php
"status": "statement_error_rolled_back"
"active_savepoints": ["wp-import", "plugin-options"]
"pending_wal_frames": [1, 2]
```

Whitespace check:

```text
git diff --check -- lanes/libsqlite
passed with no output
```

Dashboard delta from this clean worktree: `phpPass` 3796 -> 3848 (`+52` verified PASS lines). `benchmarkDenominator.mapped` unchanged; this maps no new upstream inventory unit.

## Non-Overlap

Avoided the accepted/queued clusters named in the launcher: savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/super-journal commit, VFS sync/apply, B-tree page/root/overflow movement, JSON table cursor/source/constraint work, SQL subquery/GROUP/ORDER/LIMIT text dispatch, and Unicode GLOB ranges.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLiteSavepointStack` page-image and WAL-frame bookkeeping; broader pager/VFS statement-journal byte persistence remains a follow-up.
