# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195535Z-0

- UTC gate sample: `2026-05-30T19:56:23Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-20260530T195535Z-0`
- Log: `/home/claude/port-libs/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195535Z-0.runner.log`

## Gates

- loadavg: `2.26 2.53 2.32`
- MemAvailable: `21943704 kB`
- root free: `420060124 KiB`
- /tmp use: `22%`
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
- Patterns: `date*.test` `func*.test` `json*.test` `jsonb*.test` `window*.test` `e_expr.test` `expr*.test` `select4.test` `select5.test` `select6.test` `select7.test` `select8.test` `select9.test` `selectA.test` `selectB.test` `selectC.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-20260530T195535Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `21`
- Parsed summary: `0 errors out of 113596 tests`
- Parsed errors: `0`
- Parsed tests: `113596`
- Runner time: `unknown`
- Stdout bytes: `1295`
- Stderr bytes: `0`

## Tail

```text
built testset in 42ms..
00:00 tcl(0/74) r1
00:02 tcl(34/74) r1 ETC 00:41
00:04 tcl(40/74) r1 ETC 00:14
00:06 tcl(43/74) r1 ETC 00:13
00:08 tcl(43/74) r1 ETC 00:17
00:10 tcl(43/74) r1 ETC 00:21
00:12 tcl(43/74) r1 ETC 00:25
00:14 tcl(53/74) r1 ETC 00:05
00:16 tcl(58/74) r1 ETC 00:02
00:18 tcl(59/74) r1 ETC 00:02
00:20 tcl(70/74) r1 ETC 00:01
00:21 tcl(74/74) ETC 00:00

Test database is /tmp/libsqlite-bulk-vq-dyn-20260530T195535Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-20260530T195535Z-0/build/testrunner.log
0 errors out of 113596 tests in 00:21 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
