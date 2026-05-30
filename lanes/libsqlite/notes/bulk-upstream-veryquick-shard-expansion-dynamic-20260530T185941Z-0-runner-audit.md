# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T185941Z-0

- UTC gate sample: `2026-05-30T19:00:42Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-20260530T185941Z-0`
- Log: `/tmp/libsqlite-bulk-vq-20260530T185941Z-0.log`

## Gates

- loadavg: `1.22 1.68 1.79`
- MemAvailable: `23532984 kB`
- root free: `423062584 KiB`
- /tmp use: `12%`
- /tmp inode use: `16%`
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
- Timeout seconds: `300`
- Patterns: `select*.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-20260530T185941Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `2`
- Parsed summary: `0 errors out of 1944 tests`
- Parsed errors: `0`
- Parsed tests: `1944`
- Runner time: `unknown`
- Stdout bytes: `485`
- Stderr bytes: `0`

## Tail

```text
built testset in 19ms..
00:00 tcl(0/19) r1                                                             00:02 tcl(19/19) ETC 00:00
Test database is /tmp/libsqlite-bulk-vq-20260530T185941Z-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-20260530T185941Z-0/build/testrunner.log
0 errors out of 1944 tests in 00:02 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
