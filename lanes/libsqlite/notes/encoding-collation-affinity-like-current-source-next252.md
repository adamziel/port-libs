# encoding-collation-affinity-like-current-source-next252

## Behavior

SQLite text affinity for `LIKE`/`GLOB` converts real values to text before
range and residual matching. The current UTF-16 LIKE/GLOB source cursor was
trimming every trailing `0` from the formatted real string, so values such as
`100.0`, `1000.0`, and `1200.0` became `1`, `1`, and `12`. That made copied
WordPress numeric option values fall out of `LIKE '100%'` and `GLOB '100*'`
current-source scans.

This slice keeps integral real strings intact and only trims fractional zeros
when the formatted representation contains a decimal point.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext252Test.php`
- Result: `1 test files, 55 assertions, 0 failures`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-encoding-affinity-real-like-current-source-next252.php --self-test`
- Expected `phpPass` movement: `+55` focused PASS lines, `129612 -> 129667`
- Mapped upstream coverage: unchanged, no new manifest row claimed

## Non-overlap

Avoids accepted batch216 `next248` non-ASCII LIKE escape/cursor invalidation
coverage and the accepted Unicode GLOB range cluster. This patch is specifically
the real-number text-affinity coercion path inside the existing current-source
UTF-8/UTF-16 LIKE/GLOB cursor.

## Dependency Closure

No new support component is needed. The slice reuses existing native
text-affinity, UTF-8/UTF-16 encoding, NOCASE LIKE range, and GLOB residual
matching support.
