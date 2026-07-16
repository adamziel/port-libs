# Transaction Journal Mode Corpus Next

Status: focused PHP corpus growth for rollback-journal transaction mode behavior.

Implemented:

- Added `SQLiteTransactionJournalModeCorpusTest.php` with 52 independent PASS cases covering DELETE/TRUNCATE/PERSIST rollback-journal open/close plans, rollback-journal commit ordering across sync and journal modes, temporary rollback-journal delete-on-commit semantics, `PRAGMA locking_mode` state isolation, and rollback commit sync-plan targets.
- Added `application-transaction-journal-mode-corpus.php` to smoke a copied `wp_options` import path that preflights locking mode, opens/closes a persistent rollback journal, commits dirty option pages, and forces temporary journals to delete-on-commit without ext/sqlite.
- No shared support component is needed; this reuses lane-local pager journal plans, rollback-journal commit plans, locking-mode PRAGMA state, and VFS sync planning.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionJournalModeCorpusTest.php` passed with 52 PASS lines / 52 assertions / 0 failures.
- `php -l lanes/libsqlite/tests/SQLiteTransactionJournalModeCorpusTest.php`
- `php -l lanes/libsqlite/examples/application-transaction-journal-mode-corpus.php`
- `php lanes/libsqlite/examples/application-transaction-journal-mode-corpus.php`
- `git diff --check -- lanes/libsqlite`

Dashboard/status:

- `phpPass`: `1336 -> 1388` (`+52` verified focused PASS lines).
- `benchmarkDenominator.mapped` unchanged; this is lane-scoped PHP corpus growth over already mapped pager/transaction behavior, not a newly mapped upstream inventory unit.

Non-overlap:

- Avoids accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal commit VFS application, super-journal commit, VFS sync apply, process lock, B-tree, JSON table, SELECT SQL, and Unicode GLOB clusters.
- This slice is bounded to transaction journal-mode corpus coverage and a Application copied-options smoke over existing native primitives.

Next:

- Continue with non-overlapping pager/VFS transaction application, SQL planner execution, JSON planner edges, or a distinct release/all-suite blocker on current accepted HEAD.
