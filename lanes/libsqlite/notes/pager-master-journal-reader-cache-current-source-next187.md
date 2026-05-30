# Pager Master Journal Reader Cache Current Source Next187

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next187`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Plan`.
It extends the accepted next170/next184 reader-cache current-source fences
without repeating them: after a complete current master-journal read, cache
entries built from a prefix master-journal byte span are rejected when they lack
attached journal members or current member ordinals.

Application smoke:
`application-pager-master-journal-reader-cache-current-source-next187.php` covers
a copied `wp_options` recovery where `active_plugins` was cached from a prefix
master-journal read that saw only the main journal before the attached metadata
journal member became visible. The next read misses cache and reopens, while
unchanged schema pages can be retained and plugin settings can be refreshed
from the current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Test.php`
  - `1 test files, 86 assertions, 0 failures`

Expected dashboard delta: `phpPass` moves from `88817` to `88903` from 86 newly
passing focused PASS lines. Mapped upstream coverage remains `616 / 1589`; this
is focused pager reader-cache current-source behavior over existing
master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next170 rollback-journal source identity fences,
next184 file stat/read-token fencing, batch171 next184 pager reader-cache
coverage, and VFS/WAL rollback/apply/checkpoint clusters. The new behavior is
specifically complete master-journal byte-span membership ordinal fencing for
prefix-read reader cache entries before the next source.

Dependency closure: no new support component is needed. The slice reuses
lane-local pager reader-cache planning and master-journal membership parsing.

Next task: wire complete-read membership fences into the native pager open/read
path once a broader pager transaction executor owns reader cache entries
directly.
