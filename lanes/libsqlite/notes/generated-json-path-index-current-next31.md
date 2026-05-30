# Generated JSON Path Index Current/Next 31

This slice adds `SQLiteGeneratedJsonPathIndexPlan`, a bounded native-PHP planner
for Application option rows whose generated columns are defined by
`json_extract()` or `jsonb_extract()` paths and indexed as ordinary generated
columns.

Focused behavior:

- Re-evaluates STORED/VIRTUAL generated JSON path columns after JSON mutations.
- Emits current/next index delete/insert decisions for indexes on those
  generated columns.
- Honors `IS NOT NULL` partial-index admission, collation, descending, root-page,
  and unique-index metadata.
- Rejects malformed generated JSON paths, uncovered generated columns, cyclic
  generated-column dependencies, invalid mutation paths, and unique next-key
  conflicts.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGeneratedJsonPathIndexCurrentNext31Test.php`
- `php lanes/libsqlite/examples/application-generated-json-path-index-current-next31.php`

Non-overlap:

This does not repeat accepted JSON table cursor/source/hidden/visible
constraint work, JSON path indexed update over direct expression indexes, B-tree
page relocation/root-collapse/overflow freelist work, VFS file writer/sync/lock
work, or SELECT SQL expression ORDER BY/subquery/grouped dispatch. The new
surface is generated-column re-evaluation feeding ordinary indexes after JSON
path mutations.

Dependency closure:

No new support component is required. The slice reuses existing native
`SQLiteGeneratedColumnDependencyPlan`, `SQLiteCreateIndex`, JSON extract,
JSONB, and JSON mutation helpers.
