# UTF-16 pattern LIKE/GLOB affinity current-source next114

Status: focused PHP behavior growth for UTF-16 decoded LIKE/GLOB patterns and ESCAPE values over Application `wp_options.option_value` scans, including text-affinity scalar matching and current/next source invalidation diagnostics.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNext114Test.php`
- `php lanes/libsqlite/examples/application-utf16-pattern-like-glob-affinity-current-source-next114.php --self-test`

Dashboard delta:

- `phpPass`: +64 focused PASS lines from the new next114 test file after local verification.
- `benchmarkDenominator.mapped`: unchanged; this reuses already mapped UTF-16 decoding, LIKE/GLOB matching, affinity, and current-source cursor behavior rather than claiming a fresh upstream inventory unit.

Non-overlap:

This avoids accepted Unicode GLOB range handling, malformed UTF-8 before UTF-16 record serialization, encoding numeric affinity, VDBE sorter affinity/collation, next92 UTF-16 option-value LIKE/GLOB scans, LIKE current/next cursor ranges, JSON table/source/constraint work, SELECT SQL text/order/group/subquery clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint clusters, and B-tree page/freelist/overflow clusters. The new surface is UTF-16 encoded pattern and ESCAPE decoding before LIKE/GLOB residual matching, including malformed pattern/escape rejection and current/next invalidation when matched rows change encoding, bytes, storage class, rowset, or malformed text status.

Dependency closure:

No new support component is needed. The slice reuses lane-local UTF-16 text encode/decode, SQLite LIKE/GLOB matchers, affinity storage-class helpers, and current/next source diagnostics.
