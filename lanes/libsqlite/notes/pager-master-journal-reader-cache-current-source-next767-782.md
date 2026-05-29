# SQLite pager master-journal reader-cache current-source next767-782

Prepared next767-782 as the direct follow-on to completed next751-766 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- The slice preserves the next766 init branch handoff and publishes next782 null-row branch handoff state.
- Focused test: `SQLitePagerMasterJournalReaderCacheCurrentSourceNext767782Test.php`
- Matching example: `wordpress-pager-master-journal-reader-cache-current-source-next767-782.php`

Validation:

```bash
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext767782Test.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next767-782.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext751766Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext767782Test.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next751-766.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next767-782.php
```
