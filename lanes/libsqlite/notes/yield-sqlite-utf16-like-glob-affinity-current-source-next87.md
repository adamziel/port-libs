# SQLite UTF-16 LIKE/GLOB Affinity Current-Source Next87

## Behavior

Added `SQLiteUtf16LikeGlobAffinityCurrentSourceCursor`, a bounded current/next
cursor for SQLite LIKE/GLOB scans after TEXT affinity coercion and UTF-16
encoding. The slice is distinct from the accepted raw UTF-16 source cursor and
Unicode GLOB range work: it covers mixed storage-class option values where
integers, reals, booleans, text, NULL, and BLOB inputs must be classified before
LIKE/GLOB residual matching over a UTF-16 current source.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityCurrentSourceNext87Test.php`
- Result: `1 test files, 66 assertions, 0 failures`
- PASS-line delta: `+66`

## Application Smoke

- `php lanes/libsqlite/examples/application-utf16-like-glob-affinity-current-source-next87.php --self-test`
- Covers copied `wp_options` values where numeric settings match `LIKE '1%'`,
  Unicode plugin payloads match GLOB ranges after UTF-16BE encoding, and emoji
  option payloads expose UTF-16LE surrogate bytes.

## Non-Overlap

Avoids accepted batch83-84 UTF affinity/collation malformed LIKE/GLOB cursors,
accepted Unicode GLOB ranges, accepted LIKE current/next cursor ranges, and the
accepted UTF-16 malformed record guard. This slice adds TEXT-affinity
coercion-before-pattern behavior for UTF-16-backed current-source scans.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded
`SQLiteAffinityComparison`, `SQLiteLikeCollationPlan`, `SQLiteDatabase`
LIKE/GLOB matching, and `SQLiteEncodingCollationSourceCursor` UTF-16 encoder
helpers.
