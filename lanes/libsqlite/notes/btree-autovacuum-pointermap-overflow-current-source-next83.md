# B-tree Auto-vacuum Overflow Current Source Next83

Implemented `SQLiteBTreeOverflowCurrentSourcePlan` for the current-source
overflow read edge where a later auto-vacuum/append state reuses the same
overflow page numbers with different payload or pointer-map ownership. The
helper validates that the current database image owns the overflow chain as
`first-overflow-page` followed by `overflow-page` entries, reads payload bytes
from that current source, and reports when the next source differs.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeAutovacuumPointermapOverflowCurrentSourceNext83Test.php`
  - `1 test files, 140 assertions, 0 failures`
  - 50 PASS lines
- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeAutovacuumPointermapOverflowCurrentSourceNext83Test.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-current-source-next83.php`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-btree-overflow-current-source-next83.php`
  - Emits copied Application option overflow-chain evidence where current and
    next source payload hashes differ while the current pointer-map ownership
    still points at the opened B-tree page.

Status delta:

- `phpPass`: `31014 -> 31064` (`+50` focused PASS lines)
- `benchmarkDenominator.mapped`: unchanged; this slice adds lane-local
  focused behavior coverage rather than a new upstream inventory unit.

Non-overlap:

- Avoids accepted pointer-map vacuum append/apply/truncate, overflow freelist
  release, bulk overflow freeblock materialization, B-tree page relocation,
  root collapse, freeblock defragmentation, and mutation apply clusters. This
  slice covers current-source overflow chain selection after a next image has
  reused the same overflow page numbers.

Dependency closure:

- No new support component is needed. The patch reuses existing native PHP
  database-page, overflow-chain, and pointer-map primitives.
