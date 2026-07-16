# JSON aggregate distinct object window current-next81

Status: focused PHP behavior growth for `json_group_object()` and
`jsonb_group_object()` DISTINCT ORDER BY window frames.

This slice adds object-side DISTINCT ORDER BY window-frame aggregation to match
the already accepted array-side behavior without repeating it:

- sorts each ROWS/GROUPS/RANGE frame before DISTINCT admission;
- deduplicates by SQLite object aggregate argument pair (`label`, `value`) so
  repeated labels with different values remain visible as SQLite JSON object
  text can contain duplicate names;
- preserves FILTER truthiness, EXCLUDE modes, JSON subtype values, JSONB blob
  values, and JSONB aggregate dispatch;
- exposes state methods for current/next object frame use.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonAggregateState.php
php -l lanes/libsqlite/tests/SQLiteJsonAggregateDistinctObjectWindowCurrentNext81Test.php
php -l lanes/libsqlite/examples/application-json-aggregate-distinct-object-window-current-next81.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctObjectWindowCurrentNext81Test.php
php lanes/libsqlite/examples/application-json-aggregate-distinct-object-window-current-next81.php --self-test
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The patch reuses native
PHP JSON constructor, JSONB encoder/decoder, aggregate state, and existing
window-frame row helpers.

Non-overlap: avoids accepted batch75 array DISTINCT/ORDER/window frames,
batch73 JSON aggregate FILTER/ORDER SELECT SQL coverage, accepted object
aggregate/window coverage, JSON table source/cursor/constraint work, and WAL,
B-tree, VFS, SELECT SQL, encoding, and suite surfaces.
