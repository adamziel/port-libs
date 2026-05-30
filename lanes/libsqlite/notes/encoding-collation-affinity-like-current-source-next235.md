# Encoding Collation Affinity LIKE Current Source Next235

## Scope

Adds a focused current-source behavior slice for `CAST(option_value AS TEXT) COLLATE NOCASE NOT LIKE ? ESCAPE ?` over Application-style `wp_options` rows.

This is intentionally the complement of the accepted positive malformed-byte LIKE next232 work. It covers NOT LIKE truth-complement behavior, NULL/BLOB unknown rows staying outside the complement, scalar text affinity for numeric/bool values, malformed UTF-8 byte tokenization, ASCII-only NOCASE folding, and current/next source invalidation when malformed bytes or predicate truth change.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext235Test.php`
  - `1 test files, 80 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next235.php`
  - emits `encoding-collation-affinity-like-current-source-next235` with changed predicate truth on row `3`

## Non-Overlap

Avoids accepted next232 positive LIKE malformed-byte matching, UTF-16 malformed insert guards, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM LIKE cursor fences, and unrelated VFS/WAL/B-tree/JSON/SQL executor clusters.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization, text affinity, ASCII NOCASE folding, three-valued predicate handling, and current-source cursor invalidation diagnostics.
