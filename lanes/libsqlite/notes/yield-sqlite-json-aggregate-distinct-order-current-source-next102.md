# JSON Aggregate DISTINCT ORDER BY Current-Source Next102

## Behavior

This isolated lane adds composite ORDER BY key support to direct JSON aggregate helpers. `json_group_array()` / `jsonb_group_array()` and `json_group_object()` / `jsonb_group_object()` DISTINCT ORDER BY paths now compare multi-term aggregate order keys term-by-term with per-term ASC/DESC direction before DISTINCT admission, preserving the existing stable input-position tie break.

The slice deliberately avoids the accepted parser-level JSON aggregate expression ORDER BY and SQL SELECT paths from batch99. It covers the lower-level aggregate/state/window helper path used by native Application option-summary previews.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonAggregate.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateDistinctOrderCurrentSourceNext102Test.php`
- `php -l lanes/libsqlite/examples/application-json-aggregate-distinct-order-current-source-next102.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctOrderCurrentSourceNext102Test.php`
  - `1 test files, 18 assertions, 0 failures`
  - `16` focused PASS lines
- `php lanes/libsqlite/examples/application-json-aggregate-distinct-order-current-source-next102.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP JSON aggregate, JSONB, JSON constructor, and aggregate state helpers.

## Non-Overlap

Avoided accepted batch99 parser-level JSON aggregate expression ORDER BY support, accepted JSON table hidden/visible/source/cursor work, and accepted WAL/B-tree/VFS clusters. This patch is limited to composite order-key comparison in direct JSON aggregate helper execution.
