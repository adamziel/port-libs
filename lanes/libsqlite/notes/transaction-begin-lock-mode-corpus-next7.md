# Transaction BEGIN Lock Mode Corpus Next7

Status: focused PHP corpus growth for SQLite `BEGIN` transaction lock modes.

Implemented:

- Added `SQLiteTransactionBeginLockPlan` for bounded `BEGIN`, `BEGIN DEFERRED`, `BEGIN IMMEDIATE`, and `BEGIN EXCLUSIVE` parsing plus lock-mode planning.
- Added `SQLiteTransactionBeginLockModeCorpusTest.php` with 30 independent PASS cases covering parser normalization, invalid BEGIN forms, deferred no-lock behavior, immediate reserved writer locks, exclusive rollback-journal locks, WAL exclusive/immediate parity, `PRAGMA locking_mode=exclusive`, temp-schema exclusivity, attached-schema isolation, read-only write-begin blocking, and dependency tags.
- Added `application-transaction-begin-lock-mode.php` to smoke a copied `wp_options` import transaction preflight under `PRAGMA locking_mode=exclusive` and WAL journal mode without requiring ext/sqlite.
- No new support component is needed; this reuses lane-local `SQLitePragmaLockingMode` state and bounded native PHP lock-mode planning.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTransactionBeginLockPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTransactionBeginLockModeCorpusTest.php`
- `php -l lanes/libsqlite/examples/application-transaction-begin-lock-mode.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionBeginLockModeCorpusTest.php` passed with 30 PASS lines / 30 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-transaction-begin-lock-mode.php` passed and reported planned BEGIN IMMEDIATE WAL preflight.
- `git diff --check -- lanes/libsqlite`

Dashboard/status:

- `phpPass`: `2017 -> 2047` (`+30` verified focused PASS lines).
- `benchmarkDenominator.mapped` unchanged; this is lane-scoped PHP corpus growth over already mapped transaction/locking behavior, not a fresh upstream inventory claim.

Non-overlap:

- Avoids accepted VFS lock-state, process file-lock, locked-writer, rollback-journal commit/apply, VFS sync/apply, super-journal commit, WAL byte truncation, SELECT SQL, JSON table, B-tree, and Unicode GLOB clusters.
- This slice is bounded to transaction `BEGIN` lock-mode semantics and a Application copied-options transaction preflight.

Next:

- Continue with non-overlapping SQL planner execution, JSON planner edges, pager/VFS durability, B-tree freelist/delete behavior, or a distinct release/all-suite blocker on current accepted HEAD.
