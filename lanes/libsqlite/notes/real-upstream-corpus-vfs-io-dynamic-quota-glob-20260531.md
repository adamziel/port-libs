# Real Upstream Corpus VFS IO Dynamic Quota Glob

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T021447Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/quota-glob.test`
- Scenarios: `quota-glob-1` through `quota-glob-54`, including the upstream
  repeated `quota-glob-16` and `quota-glob-17` rows and the second pass that
  maps `/` to `\` in the tested path text.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::quotaGlobProfile()` for quota-VFS path glob
  matching over normalized path separators.
- The helper keeps general SQL GLOB behavior separate and only models the
  upstream quota shim's path separator semantics for quota glob matching.
- Added `SQLiteRealUpstreamCorpusVfsQuotaGlobDynamicTest.php` with 108 focused
  upstream row/variant PASS cases plus count and malformed-input guards.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsQuotaGlobDynamicTest.php`
  - `1 test files, 1083 assertions, 0 failures`
  - 110 focused PASS lines.

Non-overlap:

- This does not repeat accepted quota VFS limit accounting, Unicode GLOB range
  predicates, `io.test` device/sync matrices, appendvfs, cksumvfs, walvfs,
  ioerr/pagerfault, journal2 safe-delete, VFS writer/sync/lock, rollback
  journal, WAL checkpoint/savepoint, JSON table, B-tree, or SQL executor
  clusters.
- The owned upstream gap is the quota shim path-glob matrix from real upstream
  `quota-glob.test`.

Status delta:

- `phpPass`: `1638574 -> 1638684`
- `phpFail`: unchanged at `0`
- mapped coverage: unchanged at `1589 / 1589`

Dependency closure:

- No new support component is needed. The slice reuses the existing SQL GLOB
  matcher and adds a bounded quota-VFS path normalization profile under the
  existing VFS I/O dynamic planning surface.
