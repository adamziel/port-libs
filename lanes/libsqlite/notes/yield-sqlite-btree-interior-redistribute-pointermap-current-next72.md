# B-tree Interior Redistribution Pointer Map Current/Next72

## Behavior

- Adds parent-driven current/next sibling resolution for table and index interior redistribution.
- Reads the parent separator for the current child, selects the immediate next sibling, applies existing redistribution page assembly, rewrites the parent divider, and materializes auto-vacuum pointer-map ownership for moved child pages.
- Keeps page count stable and leaves the next-next sibling untouched.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorRedistributePointerMapCurrentNext72Test.php`
- Result: `1 test files, 66 assertions, 0 failures`
- New focused PASS-line delta: `+66`
- Application smoke: `php lanes/libsqlite/examples/application-btree-interior-redistribute-current-next72.php`
- PHP lint: changed PHP files pass `php -l`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

Avoids accepted pointer-map vacuum, page relocation, root collapse, index-interior merge, overflow freelist/freeblock release, and batch68/69 B-tree surfaces. This slice only adds the missing current/next parent resolver for interior redistribution apply.

## Dependency Closure

No new support component is needed. The patch composes existing b-tree page assembly, record/cell helpers, pointer-map updates, and native SQLite database image helpers.
