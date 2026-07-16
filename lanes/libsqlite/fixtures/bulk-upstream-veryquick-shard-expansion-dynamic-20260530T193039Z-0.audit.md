# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T193039Z-0

- UTC gate sample: `2026-05-30T19:31:27Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-20260530T193039Z-0`
- Log: `/home/claude/port-libs/.tmux-team/worktrees/port-dev-sqlite-yield-dyn-bulk-vq-20260530T193039Z-20260530T193040Z/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T193039Z-0.runner.log`

## Gates

- loadavg: `1.81 2.45 2.44`
- MemAvailable: `22806040 kB`
- root free: `421079864 KiB`
- /tmp use: `17%`
- /tmp inode use: `17%`
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
- Timeout seconds: `600`
- Patterns: `alter.test` `altertab.test` `analyze.test` `attach.test` `btree*.test` `cast.test` `conflict.test` `e_createtable.test` `fkey*.test` `index*.test` `pragma*.test` `savepoint.test` `trigger*.test` `wal*.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-20260530T193039Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `68`
- Parsed summary: `0 errors out of 10474 tests`
- Parsed errors: `0`
- Parsed tests: `10474`
- Runner time: `unknown`
- Stdout bytes: `3134`
- Stderr bytes: `0`

## Tail

```text
built testset in 44ms..
00:00 tcl(0/127) r1                                                            00:02 tcl(0/127) r1                                                            00:04 tcl(0/127) r1                                                            00:06 tcl(0/127) r1                                                            00:08 tcl(0/127) r1                                                            00:10 tcl(0/127) r1                                                            00:12 tcl(1/127) r1 ETC 01:13                                                  00:14 tcl(1/127) r1 ETC 01:25                                                  00:16 tcl(2/127) r1 ETC 01:04                                                  00:18 tcl(2/127) r1 ETC 01:12                                                  00:20 tcl(2/127) r1 ETC 01:20                                                  00:22 tcl(2/127) r1 ETC 01:28                                                  00:24 tcl(2/127) r1 ETC 01:36                                                  00:26 tcl(2/127) r1 ETC 01:44                                                  00:28 tcl(2/127) r1 ETC 01:53                                                  00:30 tcl(2/127) r1 ETC 02:01                                                  00:32 tcl(2/127) r1 ETC 02:09                                                  00:34 tcl(2/127) r1 ETC 02:17                                                  00:36 tcl(2/127) r1 ETC 02:25                                                  00:38 tcl(2/127) r1 ETC 02:33                                                  00:40 tcl(2/127) r1 ETC 02:41                                                  00:42 tcl(2/127) r1 ETC 02:49                                                  00:44 tcl(2/127) r1 ETC 02:57                                                  00:46 tcl(2/127) r1 ETC 03:05                                                  00:48 tcl(2/127) r1 ETC 03:13                                                  00:50 tcl(5/127) r1 ETC 00:24                                                  00:52 tcl(5/127) r1 ETC 00:25                                                  00:54 tcl(12/127) r1 ETC 00:19                                                 00:56 tcl(30/127) r1 ETC 00:16                                                 00:58 tcl(39/127) r1 ETC 00:14                                                 01:00 tcl(74/127) r1 ETC 00:08                                                 01:02 tcl(81/127) r1 ETC 00:05                                                 01:04 tcl(106/127) r1 ETC 00:02                                                01:06 tcl(116/127) r1 ETC 00:01                                                01:07 tcl(127/127) ETC 00:00
Test database is /tmp/libsqlite-bulk-vq-dyn-20260530T193039Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-20260530T193039Z-0/build/testrunner.log
0 errors out of 10474 tests in 01:07 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
