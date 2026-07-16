# UTF-16 LIKE/GLOB affinity current-source next92

Status: focused PHP behavior growth for UTF-8/UTF-16 option-value LIKE/GLOB scans with SQLite text-affinity coercion and current/next source invalidation diagnostics.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityCurrentSourceNext92Test.php`
- Result: focused test run passed with 1 file / 61 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-utf16-like-glob-affinity-current-source-next92.php --self-test`
- Result: `application-utf16-like-glob-affinity-current-source-next92 self-test passed`

Dashboard delta:

- `phpPass`: +61 focused PASS lines, from 35916 to 35977 in this isolated lane status.
- `benchmarkDenominator.mapped`: unchanged; this reuses already mapped UTF-16 decoding, LIKE/GLOB, affinity, and current-source cursor behavior rather than adding a newly hydrated upstream inventory row.

Non-overlap:

This avoids accepted Unicode GLOB range handling, malformed UTF-8 before UTF-16 record serialization, encoding collation index LIKE/GLOB planning, LIKE current/next cursor ranges, JSON table/source/constraint work, SELECT SQL text/order/group/subquery clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint clusters, and B-tree page/freelist/overflow clusters. The new surface is option-value LIKE/GLOB row admission after UTF-16 decode plus scalar text-affinity coercion and current/next invalidation when matched rows change bytes, encoding, storage class, or malformed text status.

Dependency closure:

No new support component is needed. The slice reuses lane-local UTF-16 text encode/decode, SQLite LIKE/GLOB matchers, affinity storage-class helpers, and current/next source diagnostics.
