# Pager Master-Journal Reader Cache Current Source Next197

## Behavior

Adds a bounded pager/master-journal reader-cache plan for active master-journal
member changes. Cached page images are reusable only when they carry the
current master-journal member digest, source nonce, source id, and epoch. Clean
stale images for the same source are refreshed, while dirty, pinned-stale,
member-digest-stale, nonce-stale, source-stale, and epoch-stale cache entries
force the next reader to reopen.

The WordPress smoke models copied `wp_options` pages while a master journal
references both the main database journal and an attached users database
journal. It retains a schema page, refreshes the `wp_options` root page, and
invalidates an `active_plugins` reader whose cache was built against a
previous attached-member set.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext197Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 97 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +97, from 95013 to 95110. Mapped
upstream coverage is unchanged at 618 / 1589 because this is lane-local
behavior coverage, not a newly mapped upstream inventory row.

## Non-Overlap

This slice does not repeat accepted next191 master-journal delete and
directory-sync fencing, next183 publication-generation fencing, rollback
journal commit/apply, super-journal commit application, VFS sync/file-writer
paths, WAL checkpoint transactions, or WAL savepoint byte truncation. The
new behavior is the active master-journal member-digest plus source-nonce
admission fence for reader-cache reuse.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager byte
images and digest planning only; activation gate is the focused PHP test plus
the WordPress smoke.

## Next

Continue with pager/VFS transaction application or WAL durability that moves
from cache/source planning into real write ordering, avoiding duplicate
reader-cache admission variants unless they add a distinct upstream behavior.
