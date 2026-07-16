# Pager Master-Journal Reader Cache Current Source Next190

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext190Plan`,
which composes the accepted next187 complete master-journal membership fence
with a per-page source digest fence. The behavior covers a recovery edge where
a cached reader page has byte-identical content but came from the pre-recovery
database source instead of the current master-journal member source.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext190Test.php`
  passed: `1 test files, 57 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next190.php`
  passed.
- PHP lint passed for the new source, test, and example files.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `90822 -> 90879` (`+57`) after clean integration.
- mapped upstream coverage: unchanged at `617 / 1589`.

Non-overlap:

- Avoids accepted next187 complete master-journal byte-span membership ordinal
  and digest fencing.
- Avoids accepted next186 recovered-page-set sequence/digest fencing.
- Avoids accepted pager/WAL rollback, VFS writer, and checkpoint application
  clusters.

Dependency closure:

- No new support component is needed; this reuses lane-local master-journal
  member parsing, reader-cache source tracking, and page-image hashing.
