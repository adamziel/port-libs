# Real Upstream B-tree Index Dynamic Slice

- Base accepted HEAD: `3c71f3e7ae505629a27d91487b87ceab9ac9eac4`.
- Owned non-overlapping range: additional focused behavior in existing `SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php`, beyond the accepted `index.test`/`index2.test` dynamic index rows.
- Upstream source files: `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree02.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`.
- Upstream scenarios ported: `btree02-100`, `btree02-110`, and `index4.test` `1.1` through `2.2`.
- Focused growth: previous file shape had 125 PASS lines; focused run now has 225 PASS lines, so the handoff adds +100 focused PASS lines and 334 net assertions.
- Count type: PASS-line growth only. No mapped denominator rows are claimed.
- Dependency closure: no new support component needed; this reuses the existing real upstream B-tree/index dynamic corpus helper and pure PHP index leaf/record behavior.
- Non-overlap: this does not repeat accepted page relocation, overflow freelist release, root collapse, JSON, WAL, VFS, or source-neutral cleanup slices.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRealUpstreamBTreeIndexDynamicCorpus.php
php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexDynamicTest.php
```

Focused result:

```text
1 test files, 1007 assertions, 0 failures
225 selected PASS lines
```
