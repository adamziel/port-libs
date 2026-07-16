# real-upstream-corpus-pager-wal-checksum-dynamic-20260531T001333Z

Base accepted HEAD: `a90bd8ebc7d2ac86175490c2392e0f42be214ce6`.

Implemented a high-yield pager/WAL dynamic checksum corpus in `SQLiteRealUpstreamPagerWalChecksumDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`
- `walcksum-1.big/little.2.*`: native and non-native checksum WAL files validate frame checksums.
- `walcksum-1.big/little.5.*` and `.7.*`: appends after recovery preserve the existing WAL checksum byte order.
- `walcksum-1.big/little.8.*`: checkpoint restart writes the next WAL header/checksums in native byte order.
- `walcksum-2.1`: statement rollback/corrupt tail preserves a recoverable committed WAL prefix.

Focused movement:

- New focused TestRunner PASS cases: `1001`.
- New behavior assertions: `5001`.
- Expected selected `phpPass` movement: `1298986 -> 1299987`.
- Mapped denominator movement: none; upstream manifest already reports `1589 / 1589`.

Non-overlap:

- This batch does not repeat existing `wal2`, `walckptnoop`, `walpersist`, `waloverwrite`, or `pagerfault` dynamic apply coverage.
- It exercises existing `SQLiteWal` checksum validation and recovery behavior rather than adding metadata-only rows.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP WAL parser/checksum/recovery/checkpoint primitives.
