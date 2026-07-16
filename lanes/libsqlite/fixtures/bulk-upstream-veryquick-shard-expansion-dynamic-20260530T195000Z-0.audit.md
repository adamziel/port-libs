# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195000Z-0

- UTC gate sample: `2026-05-30T19:51:21Z`
- Repository HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-20260530T195000Z-0`
- Log: `/home/claude/port-libs/.tmux-team/worktrees/port-dev-sqlite-yield-dyn-bulk-vq-20260530T195000Z-20260530T195000Z/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195000Z-0.runner.log`

## Gates

- loadavg: `1.97 2.05 2.09`
- MemAvailable: `22238912 kB`
- root free: `420372908 KiB`
- /tmp use: `20%`
- /tmp inode use: `18%`
- active SQLite testfixture runners: `0`

## Source

- Upstream root: `/home/claude/port-libs/.upstream-cache/libsqlite`
- Build cache: `/home/claude/port-libs/.upstream-cache/libsqlite-build-port-libsqlite`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`

## Requested Runner

- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `valuesfault.test` `varint.test` `veryquick.test` `view.test` `view2.test` `view3.test` `vtab1.test` `vtab2.test` `vtab3.test` `vtab4.test` `vtab5.test` `vtab6.test` `vtab7.test` `vtab8.test` `vtab9.test` `vtabA.test` `vtabB.test` `vtabC.test` `vtabD.test` `vtabE.test` `vtabF.test` `vtabH.test` `vtabI.test` `vtabJ.test` `vtabK.test` `vtabL.test` `vtab_alter.test` `vtab_err.test` `vtab_shared.test` `vtabdistinct.test` `vtabdrop.test` `vtabrhs1.test` `wal.test` `wal2.test` `wal3.test` `wal4.test` `wal5.test` `wal6.test` `wal64k.test` `wal7.test` `wal8.test` `wal9.test` `walbak.test` `walbig.test` `walblock.test` `walckptnoop.test` `walcksum.test` `walcrash.test` `walcrash2.test` `walcrash3.test` `walcrash4.test` `walfault.test` `walfault2.test` `walhook.test` `walmode.test` `walnoshm.test` `waloverwrite.test` `walpersist.test` `walprotocol.test` `walprotocol2.test` `walrestart.test` `walro.test` `walro2.test` `walrofault.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-20260530T195000Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `13`
- Parsed summary: `0 errors out of 4921 tests`
- Parsed errors: `0`
- Parsed tests: `4921`
- Runner time: `unknown`
- Stdout bytes: `973`
- Stderr bytes: `0`

## Tail

```text
built testset in 77ms..
00:00 tcl(0/61) r1
00:02 tcl(0/61) r1
00:04 tcl(0/61) r1
00:06 tcl(15/61) r1 ETC 00:09
00:08 tcl(29/61) r1 ETC 00:04
00:10 tcl(45/61) r1 ETC 00:02
00:12 tcl(54/61) r1 ETC 00:01
00:13 tcl(61/61) ETC 00:00

Test database is /tmp/libsqlite-bulk-vq-dyn-20260530T195000Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-20260530T195000Z-0/build/testrunner.log
0 errors out of 4921 tests in 00:13 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
