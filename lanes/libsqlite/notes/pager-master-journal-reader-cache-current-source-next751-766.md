# SQLite pager master-journal reader-cache current-source next751-766

Prepared next751-766 as the direct follow-on to completed next735-750 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- The slice preserves the next750 incremental-vacuum branch handoff and publishes next766 init branch handoff state.
- Focused test: `SQLitePagerMasterJournalReaderCacheCurrentSourceNext751766Test.php`
- Matching example: `application-pager-master-journal-reader-cache-current-source-next751-766.php`

Validation:

```bash
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext751766Test.php
php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next751-766.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext750Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext751766Test.php
php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next735-750.php
php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next751-766.php
```
