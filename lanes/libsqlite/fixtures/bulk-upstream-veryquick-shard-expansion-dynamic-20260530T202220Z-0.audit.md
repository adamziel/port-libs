# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0

- UTC gate sample: `2026-05-30T20:24:16Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0-scratch`
- Log: `/home/claude/port-libs/.tmux-team/worktrees/port-dev-sqlite-yield-dyn-bulk-vq-20260530T202220Z-20260530T202221Z/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0.runner.log`

## Gates

- loadavg: `3.76 2.88 2.58`
- MemAvailable: `21263844 kB`
- root free: `419135448 KiB`
- /tmp use: `27%`
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
- Patterns: `auth*.test` `auto*.test` `backup*.test` `badutf*.test` `bind*.test` `blob*.test` `boundary*.test` `cache*.test` `capi*.test` `collate*.test` `corrupt*.test` `cse*.test` `ctime*.test` `dbpage.test` `dbstatus*.test` `decimal.test` `descidx*.test` `distinct*.test` `enc*.test` `exclusive*.test` `exists.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0-scratch/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `8`
- Parsed summary: `0 errors out of 13480 tests`
- Parsed errors: `0`
- Parsed tests: `13480`
- Runner time: `unknown`
- Stdout bytes: `802`
- Stderr bytes: `0`

## Tail

```text
built testset in 50ms..
00:00 tcl(0/119) r1                                                            00:02 tcl(32/119) r1 ETC 00:17                                                 00:04 tcl(46/119) r1 ETC 00:10                                                 00:06 tcl(85/119) r1 ETC 00:03                                                 00:08 tcl(119/119) ETC 00:00
Test database is /tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0-scratch/build/testrunner.db
Test log is /tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T202220Z-0-scratch/build/testrunner.log
0 errors out of 13480 tests in 00:08 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
