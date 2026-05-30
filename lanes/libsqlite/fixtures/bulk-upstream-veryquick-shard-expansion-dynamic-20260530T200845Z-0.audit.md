# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0

- UTC gate sample: `2026-05-30T20:10:12Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Isolated worktree base accepted HEAD: `ab0d9bc9baa20e0418309c1ec67c0447e4a67962`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-20260530T200845Z-0`
- Log: `/home/claude/port-libs/.tmux-team/worktrees/port-dev-sqlite-yield-dyn-bulk-vq-20260530T200845Z-20260530T200845Z/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0.runner.log`

## Gates

- loadavg: `2.01 2.05 2.17`
- MemAvailable: `21683004 kB`
- root free: `419659516 KiB`
- /tmp use: `23%`
- /tmp inode use: `19%`
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
- Patterns: `walseh1.test` `walsetlk.test` `walsetlk2.test` `walsetlk3.test` `walsetlk_recover.test` `walsetlk_snapshot.test` `walshared.test` `walslow.test` `walthread.test` `walvfs.test` `where.test` `where2.test` `where3.test` `where4.test` `where5.test` `where6.test` `where7.test` `where8.test` `where9.test` `whereA.test` `whereB.test` `whereC.test` `whereD.test` `whereE.test` `whereF.test` `whereG.test` `whereH.test` `whereI.test` `whereJ.test` `whereK.test` `whereL.test` `whereM.test` `whereN.test` `wherefault.test` `wherelfault.test` `wherelimit.test` `wherelimit2.test` `wherelimit3.test` `widetab1.test` `win32heap.test` `win32lock.test` `win32longpath.test` `win32nolock.test` `window1.test` `window2.test` `window3.test` `window4.test` `window5.test` `window6.test` `window7.test` `window8.test` `window9.test` `windowA.test` `windowB.test` `windowC.test` `windowD.test` `windowE.test` `windowerr.test` `windowfault.test` `windowpushd.test` `with1.test` `with2.test` `with3.test` `with4.test` `with5.test` `with6.test` `withM.test` `without_rowid1.test` `without_rowid2.test` `without_rowid3.test` `without_rowid4.test` `without_rowid5.test` `without_rowid6.test` `without_rowid7.test` `writecrash.test` `zeroblob.test` `zeroblobfault.test` `zerodamage.test` `zipfile.test` `zipfile2.test` `zipfilefault.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-20260530T200845Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `58`
- Parsed summary: `0 errors out of 10627 tests`
- Parsed errors: `0`
- Parsed tests: `10627`
- Runner time: `unknown`
- Stdout bytes: `2734`
- Stderr bytes: `0`

## Tail

```text
built testset in 97ms..
00:00 tcl(0/73) r1
00:02 tcl(1/73) r1
00:04 tcl(1/73) r1
00:06 tcl(1/73) r1
00:08 tcl(1/73) r1
00:10 tcl(1/73) r1
00:12 tcl(2/73) r1 ETC 00:58
00:14 tcl(2/73) r1 ETC 01:08
00:16 tcl(2/73) r1 ETC 01:18
00:18 tcl(2/73) r1 ETC 01:28
00:20 tcl(2/73) r1 ETC 01:37
00:22 tcl(2/73) r1 ETC 01:47
00:24 tcl(2/73) r1 ETC 01:57
00:26 tcl(2/73) r1 ETC 02:07
00:28 tcl(2/73) r1 ETC 02:16
00:30 tcl(2/73) r1 ETC 02:26
00:32 tcl(2/73) r1 ETC 02:36
00:34 tcl(2/73) r1 ETC 02:45
00:36 tcl(2/73) r1 ETC 02:55
00:38 tcl(2/73) r1 ETC 03:05
00:40 tcl(2/73) r1 ETC 03:15
00:42 tcl(2/73) r1 ETC 03:24
00:44 tcl(5/73) r1 ETC 00:15
00:46 tcl(5/73) r1 ETC 00:16
00:48 tcl(6/73) r1 ETC 00:11
00:50 tcl(18/73) r1 ETC 00:08
00:52 tcl(18/73) r1 ETC 00:08
00:54 tcl(38/73) r1 ETC 00:04
00:56 tcl(58/73) r1 ETC 00:03
00:58 tcl(73/73) ETC 00:00

Test database is /tmp/libsqlite-bulk-vq-dyn-20260530T200845Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-20260530T200845Z-0/build/testrunner.log
0 errors out of 10627 tests in 00:58 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
