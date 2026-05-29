# SQLite pager master-journal reader-cache current-source next783-798

Prepared next783-798 as the direct follow-on to completed next767-782 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- The slice preserves the next782 null-row branch handoff and publishes next798 real-affinity-value branch handoff state.
- Focused test: `SQLitePagerMasterJournalReaderCacheCurrentSourceNext783798Test.php`
- Matching example: `wordpress-pager-master-journal-reader-cache-current-source-next783-798.php`

Validation:

```bash
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext767782Test.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext783798Test.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next767-782.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next783-798.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext767782Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext783798Test.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next767-782.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next783-798.php
git diff --check
```
