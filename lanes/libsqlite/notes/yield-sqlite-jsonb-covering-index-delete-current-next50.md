# yield-sqlite-jsonb-covering-index-delete-current-next50

Adds `SQLiteGeneratedJsonPathIndexPlan::coveringDeleteYieldPlan()`, a bounded
native-PHP current/next DELETE preview for JSONB generated covering indexes.

Behavior covered:

- evaluates current JSONB generated columns before DELETE;
- removes selected rowids from the next row image and reports missing rowids;
- emits only current covering-index delete records/cells, including covered
  payload columns before the row is removed;
- materializes current and next index leaf pages so covering-index page-image
  changes are visible;
- skips partial-index entries whose generated key is absent.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCoveringIndexDeleteCurrentNext50Test.php`
- `php lanes/libsqlite/examples/application-jsonb-covering-index-delete-current-next50.php`

Non-overlap: this does not repeat accepted JSONB generated UPDATE index
maintenance, JSONB generated-index B-tree yield updates, partial-index WHERE
planner proof, expression-index range costs, B-tree page relocation/root
collapse/overflow freelist release, VFS writer/sync/rollback application, or
JSON table cursor/source/constraint work. This slice is DELETE-only and keeps
the deleted row's current covering-index record payload available for B-tree
cell removal.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP JSONB codec, generated-column analysis, CREATE INDEX
parser, index predicate handling, record encoder, index cell encoder, and
index leaf page assembler.
