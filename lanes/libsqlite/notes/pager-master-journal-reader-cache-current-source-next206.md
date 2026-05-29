# Pager Master-Journal Reader Cache Current Source Next206

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a narrow
pager reader-cache fence layered after next203. It covers a current-source edge
where the ordered master-journal member list, attached member file tokens, and
attached member rollback-journal header digests still match, but the
master-journal sidecar itself was replaced or reread with a different file
token. Matching clean cache pages can still be retained/refreshed, while stale
master-journal file-token rows and read tickets force reader reopen before the
next current-source read.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext206Test.php`
  - `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next206.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next206 self-test passed`
- PHP lint for changed PHP files.
  - `No syntax errors detected` for the new plan, test, and example.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Expected dashboard delta: `phpPass` moves from `98594` to `98645` from 51
newly passing focused PASS lines. Mapped upstream coverage remains `620 / 1589`;
this is focused pager master-journal reader-cache current-source behavior over
the existing pager/master-journal inventory rather than a fresh upstream
denominator row.

## Non-Overlap

This slice does not repeat next203 master-member order, next196 member journal
header digests, next192 member journal token maps, next191 delete-directory
sync, rollback-journal apply/commit, VFS savepoint/locked writer/sync paths,
WAL checkpoint/savepoint byte truncation, or B-tree/JSON/SQL/encoding clusters.
The new behavior is specifically the master-journal sidecar file-token fence
after all member-level source fences still pass.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal recovery, member token/header/order source checks, and
reader-cache current-source primitives.
