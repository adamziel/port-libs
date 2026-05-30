# Pager WAL savepoint hot-cache current-source next139

Status: focused PHP behavior growth for `pager-wal-savepoint-hot-cache-current-source-next139`.

This slice adds `SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan`. It models the pager boundary after hot recovery establishes the current database source, a WAL savepoint rollback discards newer frames, and the hot pager cache must be validated before the next read/write. Cache entries are retained only when source id, generation, frame prefix, and image match the recovered current source; clean stale pages can refresh, dirty/pinned stale pages are invalidated, and next writes journal before-images from the current source instead of stale cache bytes.

Application smoke: `application-pager-wal-savepoint-hot-cache-current-source-next139.php` covers copied `wp_options` plugin-import retry behavior where `active_plugins` refreshes from hot recovery, dirty plugin-setting cache is invalidated, and the next retry write captures the recovered current-source page.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerWalSavepointHotCacheCurrentSourceNext139Test.php`
- `php -l lanes/libsqlite/examples/application-pager-wal-savepoint-hot-cache-current-source-next139.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalSavepointHotCacheCurrentSourceNext139Test.php`
- `php lanes/libsqlite/examples/application-pager-wal-savepoint-hot-cache-current-source-next139.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `59517` to `59616` from 99 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager/WAL current-source cache behavior over existing pager/WAL/savepoint inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted master-journal hot-cache next136, WAL savepoint cache recovery next133, cache-spill WAL recovery next135, WAL checkpoint/restart/truncate reader slices, WAL byte truncation, VFS savepoint rollback/rollback-journal commit/application, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is the composition point where hot recovery and WAL savepoint rollback jointly rebase hot pager-cache entries before the next read/write.

Dependency closure: no new support component is needed. The slice reuses lane-local pager cache current-source, WAL frame-prefix, and savepoint rollback concepts.
