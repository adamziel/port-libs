# Real Upstream Pager/WAL Dynamic Checksum Persist Slice

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T020252Z-0`
Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

## Upstream Source Files

- `walcksum.test`: native and non-native WAL checksum byte-order recovery, append, and checkpoint behavior.
- `waloverwrite.test`: repeated page overwrite transactions with savepoint rollback tail discarded during recovery.
- `walpersist.test`: persistent WAL sidecar behavior plus `noop`, `passive`, and `truncate` checkpoint policy.

## Handoff Delta

- Added `SQLiteRealUpstreamCorpusPagerWalDynamicChecksumPersistTest.php`.
- Adds `1,202` focused TestRunner PASS cases.
- Adds `10,817` focused assertions.
- Expected selected movement: `1603992 -> 1605194 pass / 0 fail`.
- Mapped denominator movement: none; coverage remains `1589 / 1589`.

## Non-Overlap

This slice avoids accepted WAL byte truncation, rollback-journal apply/commit,
VFS writer/sync/lock, pager late-WAL2, restart-noop, and app-WAL slices. It
covers checksum byte-order recovery, repeated WAL overwrite committed-prefix
recovery, and persistent-WAL checkpoint sidecar policy through existing generic
`SQLiteWal` and `SQLiteWalHeader` behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicChecksumPersistTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicChecksumPersistTest.php`
  - Result: `1 test files, 10817 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The tests reuse `SQLiteWal`,
`SQLiteWalHeader`, and the hydrated upstream SQLite checkout as source truth.
