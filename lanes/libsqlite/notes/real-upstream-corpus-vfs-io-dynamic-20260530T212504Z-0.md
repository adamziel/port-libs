# real-upstream-corpus-vfs-io-dynamic-20260530T212504Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T212504Z-0`

Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bigmmap.test`
  - `bigmmap-1.0` sparse database setup.
  - `bigmmap-1.1` through `bigmmap-1.7` table/index clusters near GiB boundaries.
  - `bigmmap-2.0.*` through `bigmmap-2.8.*` mmap-size read/probe matrix.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapwarm.test`
  - `mmapwarm-1.1` through `mmapwarm-1.4`, `mmapwarm-2.0`, and faultsim `mmapwarm-3`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapcorrupt.test`
  - `mmapcorrupt-1.0`, `mmapcorrupt-2.1`, and `mmapcorrupt-2.2`.

## Behavior Ported

- Added bounded native VFS/IO mmap profiles to `SQLiteVfsIoDynamicPlan`:
  - `bigMmapSparseBoundaryProfile()` models the upstream sparse 8GiB database
    layout, mmap-size boundaries, table/index clusters near each GiB boundary,
    covering-index scans, correlated rowid subquery lookups, and empty
    `NOT EXISTS` result checks.
  - `mmapWarmProfile()` models `sqlite3_mmap_warm()` success, transaction
    misuse, schema argument handling, and OOM fault behavior.
  - `mmapCorruptTailProfile()` models targeted mmap reads against the upstream
    corrupt WITHOUT ROWID tail case where schema and adjacent table reads still
    succeed.
- Added `SQLiteRealUpstreamCorpusVfsMmapSparseDynamicTest.php` with 1,202
  distinct focused PASS cases and 23,391 assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapSparseDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapSparseDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapSparseDynamicTest.php`
  - `1 test files, 23391 assertions, 0 failures`
  - 1,202 PASS lines.

## Non-overlap

This does not repeat accepted `io.test` traffic/default-page-size/safe-append
coverage, `ioerr.test` through `ioerr6.test`, backup IOERR, append VFS,
checksum/WAL VFS, WAL SHM fault, mmap read-count/syscall-failure, or mmap
unique-insert fault coverage. The new surface is specifically large sparse
`bigmmap.test` boundary reads, `mmapwarm.test` warm/misuse/OOM behavior, and
`mmapcorrupt.test` targeted corrupt-tail mmap reads.

## Dependency Closure

No new support component is required. The patch reuses the existing lane-local
VFS dynamic corpus planning surface and adds bounded native PHP mmap behavior
profiles for real upstream VFS/IO sections.
