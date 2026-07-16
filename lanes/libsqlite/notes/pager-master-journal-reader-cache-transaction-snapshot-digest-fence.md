# Pager Master-Journal Reader Cache Current Source Next194

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext194Plan`,
which composes accepted next190 per-page current-source digest fencing with a
transaction-wide reader snapshot fence. The behavior prevents a multi-page
reader from reusing a mixed cache after master-journal recovery, where one page
is refreshed and another byte-identical page still carries a stale transaction
snapshot.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheTransactionSnapshotDigestFenceTest.php`
  passed: `1 test files, 57 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-transaction-snapshot-digest-fence.php`
  passed.
- PHP lint passed for the new source, test, and example files.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `92937 -> 92994` (`+57`) after clean integration.
- mapped upstream coverage: unchanged at `618 / 1589`.

Non-overlap:

- Avoids accepted next190 per-page source digest fencing, next189 per-member
  rollback-journal digest fencing, next187 complete master-journal membership,
  and accepted VFS/WAL rollback/checkpoint application clusters.
- The new surface is transaction-wide snapshot cohesion for reader-cache reuse
  after the per-page current-source fences have already run.

Dependency closure:

- No new support component is needed; this reuses lane-local master-journal
  member parsing, current-source page digests, and reader-cache ticket hashing.

Next task: continue pager/WAL work only on a non-overlapping checkpoint,
savepoint, reader-cache, or durable VFS application edge with focused tests.
