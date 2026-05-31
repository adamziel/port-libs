2026-05-31 real-upstream-corpus-vfs-io-dynamic-20260531T074454Z-0

- Base accepted HEAD: 9c30c680e4b44fbeb2fc11612b28622bb7d8e322.
- Upstream source truth: /home/claude/port-libs/.upstream-cache/libsqlite/test/io.test.
- Added focused PHP coverage in SQLiteVfsIoDynamicUpstreamCorpusTest.php for io.test io-1.1 through io-1.5 quick-balance database write counts and io.test io-6.1/io-6.2 pager-cache retention after warm reads.
- Non-overlap: this extends the existing VFS I/O dynamic corpus file without touching accepted VFS file writer, lock state, sync apply, rollback journal apply, WAL byte truncation, JSON source/cursor, or B-tree page move clusters.
- Focused before/after for this file: 40 PASS cases / 588 assertions before; 58 PASS cases / 934 assertions after. Delta: +18 PASS cases and +346 behavior assertions.
- Verification:
  - php -l lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php
  - php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php
  - git diff --check -- lanes/libsqlite
- Dependency closure: no new support component is needed. The existing SQLiteVfsIoDynamicPlan helpers model the upstream pager/VFS behavior and the added tests reuse that bounded native PHP component.
