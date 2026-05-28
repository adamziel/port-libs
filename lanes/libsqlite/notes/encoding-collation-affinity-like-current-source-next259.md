# encoding-collation-affinity-like-current-source-next259

## Behavior

Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Plan` for the
SQLite rule that default `LIKE` ASCII-folding is independent of a `COLLATE
BINARY` expression. A copied WordPress `wp_options.option_name COLLATE BINARY
LIKE 'Plugin%'` scan cannot safely reuse a BINARY prefix cursor unless
`case_sensitive_like` is enabled, because lowercase and uppercase plugin option
names still satisfy the residual `LIKE`.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Test.php`
- Result: `1 test files, 71 assertions, 0 failures`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-encoding-binary-like-current-source-next259.php --self-test`
- Result: `wordpress-encoding-binary-like-current-source-next259 self-test passed`
- Expected `phpPass` movement: `+71` focused PASS lines, `137964 -> 138035`
- Mapped upstream coverage: unchanged, no new manifest row claimed

## Non-Overlap

Avoids accepted next255 GLOB bracket-class residual fallback, next256 dynamic
LIKE pattern affinity, next250 RTRIM residual peers, Unicode GLOB range
handling, UTF-16 malformed guards, and JSON/VFS/WAL/B-tree/SQL planner slices.
The new surface is specifically default `LIKE` residual folding over a
BINARY-collated expression and the current-source cursor fence it requires.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE matching,
BINARY byte collation keys, mixed UTF decoding, scalar text-affinity coercion,
and current-source diagnostics.
