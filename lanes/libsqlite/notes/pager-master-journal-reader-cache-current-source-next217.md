# Pager Master Journal Reader Cache Current Source Next217

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next217`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Plan`. After master-journal recovery and the accepted next212 database file-token fence admit a cached reader page, next217 also requires the recovered database header digest to match the current source before reusing reader-cache pages or read tickets. This catches change-counter, version-valid-for, schema-cookie, and page-count header changes where the database file token and master-journal metadata still look current.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next217.php` models a copied `wp_options` import where schema and alloptions pages can be reused/refreshed, but an `active_plugins` page cached under the prior database header must reopen before plugin import continues.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next217.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next217.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext217Test.php`
  - `1 test files, 52 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next217.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next217 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard delta: `phpPass` moves from `105283` to `105335` from 52 newly passing focused PASS lines. Mapped upstream coverage remains `623 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next212 database file-token admission, next211 attached member recovered-page-set digests, next210 VFS read-source token, next209 raw master-journal bytes, next206 master file-token, next203 member order, next196 member header, next192 member token, next191 delete-sync, rollback/super-journal/VFS apply paths, WAL, B-tree, JSON, SELECT, PRAGMA, trigger, planner, and encoding surfaces. The new behavior is specifically the recovered database header digest fence after all lower master-journal and database file-token checks pass.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache current-source primitives and adds bounded native PHP metadata for the database header digest.

Next task: wire the database-header digest into a future pager transaction executor so reader-cache entries are owned by native pager/VFS reads rather than bounded planning fixtures.
