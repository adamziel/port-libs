# libsqlite encoding collation affinity LIKE current-source next261

## Scope

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Plan` for a composite Application `wp_options` cursor fence:
  UTF-16-bound `option_name LIKE ... ESCAPE ...` plus `option_value LIKE ...` after SQLite text-affinity coercion.
- The behavior decodes the bound UTF-16 pattern before LIKE tokenization, preserves escaped `_` as a literal prefix byte, applies ASCII-only NOCASE LIKE matching, treats BLOB/NULL values as unknown for LIKE, and records current-source invalidation when the next source changes the composite rowset or value-affinity residual truth.
- Adds `application-encoding-affinity-like-current-source-next261.php` as the Application smoke.

## Verification

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 82 assertions, 0 failures
```

- PASS lines: 76.
- `phpPass` delta: `142008 -> 142084`.

## Non-Overlap

This slice does not repeat accepted next240 numeric-only LIKE, next258 case-sensitive LIKE binary transition, Unicode GLOB ranges, UTF-16 malformed guards, JSON table planner/cursor work, B-tree page/freeblock work, WAL/pager/VFS transaction work, or suite countability rows.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode, LIKE tokenization, ASCII NOCASE matching, scalar text-affinity coercion, and current-source diagnostics already present in `lanes/libsqlite`.
