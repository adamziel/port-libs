# Pager Master-Journal Reader Cache Current Source Next207

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext207Plan`, a narrow
pager reader-cache fence layered after next206. It covers a current-source edge
where the ordered master-journal member list, attached member file tokens,
attached member rollback-journal header digests, and the master-journal sidecar
file token still match, but the database file token changed before the next
reader-cache reuse. Clean cache pages with the current database token can still
be retained/refreshed; stale database-token rows and read tickets force reader
reopen before the next current-source read.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext207Test.php`
  - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next207.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next207 self-test passed`
- PHP lint for changed PHP files.
  - `No syntax errors detected` for the new plan, test, and example.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Expected dashboard delta: `phpPass` moves from `100087` to `100142` from 55
newly passing focused PASS lines. Mapped upstream coverage remains `621 / 1589`;
this is focused pager master-journal reader-cache current-source behavior over
the existing pager/master-journal inventory rather than a fresh upstream
denominator row.

## Non-Overlap

This slice does not repeat next206 master-journal sidecar file-token fencing,
next203 master-member order, next196 member journal header digests, next192
member journal token maps, rollback-journal apply/commit, VFS savepoint/locked
writer/sync paths, WAL checkpoint/savepoint byte truncation, or B-tree/JSON/SQL/
encoding clusters. The new behavior is specifically the database file-token
fence after all master-journal and member-level source fences still pass.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal recovery, member token/header/order source checks,
master-journal file-token fencing, and reader-cache current-source primitives.
