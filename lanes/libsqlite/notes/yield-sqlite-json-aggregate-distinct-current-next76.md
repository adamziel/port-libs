# SQLite JSON aggregate DISTINCT current-next76

## Behavior

- Rebased the JSON aggregate text executor beyond accepted `jsonagg73` order/filter coverage.
- Parser-level `SQLiteSelectSql` now admits `json_group_array(DISTINCT column ...)` and `jsonb_group_array(DISTINCT column ...)` in grouped and implicit aggregate SELECT plans.
- `SQLiteGroupedAggregate` applies JSON aggregate `FILTER`, then aggregate-local `ORDER BY`, then DISTINCT de-duplication over scalar, NULL, JSON subtype, and JSONB BLOB values before final JSON/JSONB dispatch.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctCurrentNext76Test.php`
- `php lanes/libsqlite/examples/application-json-aggregate-distinct-current-next76.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateDistinctCurrentNext76Test.php`
- `php -l lanes/libsqlite/examples/application-json-aggregate-distinct-current-next76.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This avoids accepted `jsonagg73` aggregate-local `ORDER BY` plus `FILTER` execution by removing the current DISTINCT rejection and covering DISTINCT interaction with ORDER BY/FILTER and JSONB dispatch. It does not touch JSON table cursor/source/hidden/visible constraint work, B-tree, WAL, VFS, or suite evidence.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP SELECT SQL parser, grouped aggregate executor, JSON constructor, JSONB encoder/decoder, and Application smoke harness.
