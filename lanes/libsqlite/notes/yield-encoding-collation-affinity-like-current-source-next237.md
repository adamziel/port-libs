# encoding-collation-affinity-like-current-source-next237

## Behavior

Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext237Plan` for a current/next Application `wp_options.option_value` scan using:

- `LIKE ... ESCAPE` prefix planning where escaped `_` and `%` are treated as literal characters before the trailing wildcard.
- SQLite-style text affinity before LIKE residual evaluation for integer, float, bool, text, NULL, and BLOB-like values.
- ASCII-only `NOCASE` collation keys for range membership while residual LIKE still applies the escaped pattern.
- Current/next invalidation reasons for source/schema, storage class, affinity text, collation key, range membership, residual result, matched rowset, and malformed text.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext237Test.php`
  - `1 test files, 78 assertions, 0 failures`
  - `69` PASS lines
- `php lanes/libsqlite/examples/application-option-value-like-escape-affinity-current-source-next237.php --self-test`
  - `application-option-value-like-escape-affinity-current-source-next237 self-test passed`

## Status Delta

- Expected `phpPass` delta: `+69` focused PASS lines (`117718 -> 117787` from this worktree's lane status).
- Mapped upstream denominator unchanged; this is focused PHP behavior coverage, not a new manifest-backed upstream row.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE ESCAPE prefix planning, scalar text-affinity conversion, ASCII NOCASE collation keys, `SQLiteBlobValue` storage classification, and current-source invalidation diagnostics.

## Non-Overlap

This slice covers escaped wildcard literals after text affinity under LIKE/NOCASE current-source scans. It avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, UTF-16 NOCASE/RTRIM canonical-equivalent scans, blob LIKE/GLOB affinity next234, SQL expression ORDER BY, JSON table planner/source work, WAL, VFS, and B-tree clusters.
