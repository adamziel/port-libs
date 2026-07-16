# real-upstream-corpus-pager-wal-dynamic-20260531T055751Z-0

Added `SQLiteRealUpstreamPagerWalDynamic20260531T055751ZTest.php` as a
non-overlapping real upstream pager/WAL corpus slice.

Upstream source sections:

- `wal5.test`: `wal5-1.1..1.12`, `wal5-2.1.*`, `wal5-2.2.*`,
  `wal5-2.3.*`, `wal5-3.*`, `wal5-4.*`, and `wal5-5.*`
- `wal6.test`: `wal6-2.1..2.5`, `wal6-3.1..3.3`, and `wal6-4.1..4.4`
- `wal8.test`: `wal8-1.0..1.1` and `wal8-3.0..3.1`

The PHP tests synthesize valid WAL byte streams from those scenarios and assert
committed-prefix recovery, busy-reader checkpoint preservation, attached-WAL
checkpoint semantics, partial checkpoint reader boundaries, and empty-file
page-size handoff behavior through existing `SQLiteWal` parser/recovery,
checkpoint, durable checkpoint, and reader snapshot APIs.

Expected focused movement: `1001` distinct TestRunner PASS cases. This is
test-growth only; mapped denominator coverage remains complete at `1589 / 1589`.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded WAL parser, checksum recovery, transaction recovery, checkpoint,
durable checkpoint, and reader snapshot primitives.

Non-overlap: this avoids the already accepted pager/WAL sections named in the
latest lane status by covering `wal5.test`, `wal6.test`, and `wal8.test`
checkpoint/page-size behavior rather than the prior `walblock`, `walprotocol`,
`walfault`, `pagerfault`, `pager2`, `wal9`, or `walshared` dynamic clusters.
