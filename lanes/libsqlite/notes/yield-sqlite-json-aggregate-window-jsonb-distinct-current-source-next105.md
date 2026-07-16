# SQLite JSON aggregate window JSONB DISTINCT current-source next105

## Behavior

- Added current-source `json_group_array(DISTINCT ...)` window-frame helpers to `SQLiteJsonAggregate`, including JSONB SQL-function dispatch.
- Covered parser-level `jsonb_group_array(DISTINCT payload) OVER (...)` frames where the aggregate value is JSON subtype or JSONB BLOB data, with FILTER, ROWS, GROUPS, EXCLUDE, NULL tails, and duplicate JSONB payloads.
- Added a Application `wp_options` smoke for plugin payload import summaries that need current-row JSONB windows without ext/sqlite.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowJsonbDistinctCurrentSourceNext105Test.php`
  - `1 test files, 43 assertions, 0 failures`
  - `40` PASS lines
- `php lanes/libsqlite/examples/application-json-aggregate-window-jsonb-distinct-current-source-next105.php`
- `php -l lanes/libsqlite/src/SQLiteJsonAggregate.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateWindowJsonbDistinctCurrentSourceNext105Test.php`
- `php -l lanes/libsqlite/examples/application-json-aggregate-window-jsonb-distinct-current-source-next105.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This avoids accepted batch100 default JSON aggregate windows, accepted batch99 expression ORDER BY JSON aggregate behavior, accepted JSON table cursor/source/constraint work, and accepted B-tree/WAL/VFS batches. The slice is specifically the missing current-source DISTINCT window helper plus JSONB payload tests.

## Dependency closure

No new support component is needed. The slice reuses the native PHP SELECT SQL window executor, JSON constructor, JSONB encoder/decoder, and Application smoke harness.
