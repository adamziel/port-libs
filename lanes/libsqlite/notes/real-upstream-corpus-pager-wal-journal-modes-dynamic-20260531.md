# Real upstream corpus pager/WAL journal modes dynamic

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T015341Z-0`

Base accepted HEAD: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`

Added `SQLiteRealUpstreamPagerWalJournalModesDynamicTest.php` with 1,000
distinct generated TestRunner cases plus one upstream-citation case. The cases
exercise existing native rollback-journal, savepoint, and journal-finalization
behavior across page sizes, sector sizes, rollback journal modes, and sync
modes.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - `pager1-3.1.3.*` synchronous-off hot journal recovery
  - `pager1-7.*` journal_mode=TRUNCATE finalization
  - `pager1-13.*` journal_mode=PERSIST header zeroing
  - `pager1-23.*` PERSIST to DELETE journal cleanup
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`
  - `savepoint-1.*` rollback-to excludes post-savepoint growth
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal2.test`
  - `journal2-1.*` rollback journal page images precede database writes

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalJournalModesDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalJournalModesDynamicTest.php`
  - `1 test files, 35001 assertions, 0 failures`
  - `1001` PASS lines

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLiteRollbackJournal`, `SQLiteRollbackJournalCommitPlan`, and
`SQLiteSavepointStack` support.

Non-overlap: this avoids the accepted pager/WAL dynamic files, WAL checkpoint
transactions, rollback-journal commit apply, savepoint byte truncation, VFS
writer/apply work, and older WordPress-shaped smokes. The new coverage is a
generic application corpus over rollback journal modes and savepoint rollback
semantics.
