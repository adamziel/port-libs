# Real Upstream Pager/WAL Dynamic Corpus

Session: `port-dev-sqlite-yield-dyn-real-pager-20260530T174541Z`
Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`

Implemented one upstream behavior cluster under pager/WAL dynamic behavior:

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
    - Sections `1.1` through `1.10`: `PRAGMA wal_checkpoint = noop` does not backfill, preserves the WAL, and reports checkpoint counters.

Behavior change:

- `SQLiteWal::checkpointModePlan()` now accepts checkpoint mode `noop`.
- `noop` reports the committed frame inventory without checkpointing frames, does not grow or rewrite the database image, never resets/truncates the WAL, and is durable as WAL-byte preservation.

Focused test movement:

- Extended the existing `SQLiteRealUpstreamPagerWalDynamicCorpusTest.php` without removing prior `wal.test`/`pager1.test` coverage.
- New selected PASS lines: `540`.
- Focused file result: `4341` assertions, including the preserved existing corpus.
- Expected `phpPass` movement: `218357 -> 218897`.
- Mapped denominator movement: none claimed; this is focused PHP corpus coverage, not a manifest denominator admission.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWal.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
  - `1 test files, 4341 assertions, 0 failures`
- No-domain API guard
  - Not run: guard file is not present in this accepted-base worktree.
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteWal`, `SQLiteWalHeader`, frame parsing, checkpoint, recovery, and durable sidecar primitives.

Non-overlap:

- Does not repeat accepted WAL byte truncation, rollback-journal commit/apply, super-journal commits, checkpoint transactions, VFS sync/apply, process locks, or file-writer behavior.
- The new behavior is specifically upstream `noop` checkpoint admission plus dynamic real-corpus checkpoint/read preservation coverage.
