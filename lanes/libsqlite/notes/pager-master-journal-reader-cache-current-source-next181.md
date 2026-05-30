# Pager master-journal reader-cache current-source next181

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next181`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext181Plan`. It extends the accepted next170 rollback-journal source digest/page-set fence and next178 member generation/delete-state fence with a narrower current-source rule: a reader-cache entry keyed from a pending master-journal membership is rejected even when its page bytes and rollback-journal source digest still match the current recovered image.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next181.php` covers a copied `wp_options` recovery where `active_plugins` was cached under a pending master-journal source that includes a not-yet-current member journal. The next reader misses cache for that page and the next write journals from the current rollback source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext181Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 110 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next181.php
application-pager-master-journal-reader-cache-current-source-next181 self-test passed
```

Dashboard delta: `phpPass` moves from `85432` to `85542` for the 110 verified focused PASS lines. Mapped upstream coverage remains `614 / 1589`; this is fresh focused PHP pager behavior over already mapped master-journal reader-cache primitives rather than a new upstream inventory unit.

Non-overlap: avoids accepted batch166 next178 pager master-journal reader-cache member-generation/delete-state behavior, next170 rollback-journal source digest/page-count/page-set fencing, next164 header/change-counter/schema-cookie fencing, and accepted VFS/WAL rollback apply, savepoint byte truncation, checkpoint transaction, and batch166 WAL hot-journal/savepoint/checkpoint replay surfaces. The new behavior is specifically current master-journal membership rejecting future/pending source cache pages before the next source.

Dependency closure: no new support component is needed; this reuses existing lane-local rollback-journal parsing and pager reader-cache current-source planning.

Next task: wire this pending-source rejection into a broader pager open/recovery executor once the lane has a native pager transaction object that owns reader-cache entries directly.
