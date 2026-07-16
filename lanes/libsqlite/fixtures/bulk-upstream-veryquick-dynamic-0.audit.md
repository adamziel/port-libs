# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-dynamic-0

- UTC gate sample: `2026-05-30T17:48:13Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/libsqlite-bulk-vq-dyn-0`
- Log: `/home/claude/port-libs/lanes/libsqlite/fixtures/bulk-upstream-veryquick-dynamic-0.runner.log`

## Gates

- loadavg: `1.97 1.95 1.65`
- MemAvailable: `24691876 kB`
- root free: `425574368 KiB`
- /tmp use: `3%`
- /tmp inode use: `14%`
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
- Patterns: `quick.test` `select1.test` `select2.test` `select3.test` `expr.test` `where.test` `join.test` `insert.test` `update.test` `delete.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/libsqlite-bulk-vq-dyn-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `5`
- Parsed summary: `0 errors out of 19541 tests`
- Parsed errors: `0`
- Parsed tests: `19541`
- Runner time: `unknown`
- Stdout bytes: `620`
- Stderr bytes: `0`

## Tail

```text
built testset in 28ms..
00:00 tcl(0/26) r1                                                             00:02 tcl(12/26) r1 ETC 00:19                                                  00:04 tcl(24/26) r1 ETC 00:00                                                  00:04 tcl(26/26) ETC 00:00
Test database is /tmp/libsqlite-bulk-vq-dyn-0/build/testrunner.db
Test log is /tmp/libsqlite-bulk-vq-dyn-0/build/testrunner.log
0 errors out of 19541 tests in 00:04 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
