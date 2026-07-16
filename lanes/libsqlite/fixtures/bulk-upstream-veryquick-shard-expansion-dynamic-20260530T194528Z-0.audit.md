# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T194528Z-0

- UTC gate sample: `2026-05-30T19:46:33Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-20260530T194528Z-0`
- Log: `/home/claude/port-libs/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T194528Z-0.runner.log`

## Gates

- loadavg: `1.41 1.62 1.99`
- MemAvailable: `22402724 kB`
- root free: `420498460 KiB`
- /tmp use: `19%`
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
- Timeout seconds: `600`
- Patterns: `date*.test` `e_expr.test` `e_select*.test` `func*.test` `json*.test` `limit.test` `misc*.test` `select4.test` `select5.test` `select6.test` `select7.test` `select8.test` `select9.test` `sort*.test` `union*.test` `where*.test` `window*.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-20260530T194528Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `34`
- Parsed summary: `0 errors out of 116195 tests`
- Parsed errors: `0`
- Parsed tests: `116195`
- Runner time: `unknown`
- Stdout bytes: `1775`
- Stderr bytes: `0`

## Tail

```text
built testset in 58ms..
00:00 tcl(0/101) r1
00:02 tcl(15/101) r1 ETC 00:23
00:04 tcl(21/101) r1 ETC 00:25
00:06 tcl(43/101) r1 ETC 00:15
00:08 tcl(62/101) r1 ETC 00:14
00:10 tcl(62/101) r1 ETC 00:18
00:12 tcl(62/101) r1 ETC 00:22
00:14 tcl(62/101) r1 ETC 00:25
00:16 tcl(72/101) r1 ETC 00:08
00:18 tcl(78/101) r1 ETC 00:08
00:20 tcl(78/101) r1 ETC 00:08
00:22 tcl(78/101) r1 ETC 00:09
00:24 tcl(78/101) r1 ETC 00:10
00:26 tcl(78/101) r1 ETC 00:11
00:28 tcl(78/101) r1 ETC 00:12
00:30 tcl(80/101) r1 ETC 00:02
00:32 tcl(81/101) r1 ETC 00:02
00:33 tcl(101/101) ETC 00:00

Test database is /tmp/libsqlite-bulk-vq-dyn-20260530T194528Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-20260530T194528Z-0/build/testrunner.log
0 errors out of 116195 tests in 00:33 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
