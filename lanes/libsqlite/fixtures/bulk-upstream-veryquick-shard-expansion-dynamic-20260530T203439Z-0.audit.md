# SQLite Tcl Bounded Runner Evidence - bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0

- UTC gate sample: `2026-05-30T20:36:07Z`
- Repository HEAD: `df4b88a34f0edc616ba8ef077dc8a5172d60f37e`
- Scope: isolated upstream SQLite Tcl runner evidence only.
- Scratch: `/tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0`
- Log: `/home/claude/port-libs/.tmux-team/worktrees/port-dev-sqlite-yield-dyn-bulk-vq-20260530T203439Z-20260530T203439Z/lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0.runner.log`

## Gates

- loadavg: `2.25 2.44 2.43`
- MemAvailable: `20947720 kB`
- root free: `418777404 KiB`
- /tmp use: `28%`
- /tmp inode use: `20%`
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
- Patterns: `fts-9fd058691.test` `fuzz-oss1.test` `quota-glob.test` `tkt-02a8e81d44.test` `tkt-18458b1a.test` `tkt-26ff0c2d1e.test` `tkt-2a5629202f.test` `tkt-2d1a5c67d.test` `tkt-2ea2425d34.test` `tkt-31338dca7e.test` `tkt-313723c356.test` `tkt-385a5b56b9.test` `tkt-38cb5df375.test` `tkt-3998683a16.test` `tkt-3a77c9714e.test` `tkt-3fe897352e.test` `tkt-4a03edc4c8.test` `tkt-4c86b126f2.test` `tkt-4dd95f6943.test` `tkt-4ef7e3cfca.test` `tkt-54844eea3f.test` `tkt-5d863f876e.test` `tkt-5e10420e8d.test` `tkt-5ee23731f.test` `tkt-6bfb98dfc0.test` `tkt-752e1646fc.test` `tkt-78e04e52ea.test` `tkt-7a31705a7e6.test` `tkt-7bbfb7d442.test` `tkt-80ba201079.test` `tkt-80e031a00f.test` `tkt-8454a207b9.test` `tkt-868145d012.test` `tkt-8c63ff0ec.test` `tkt-91e2e8ba6f.test` `tkt-99378177930f87bd.test` `tkt-9a8b09f8e6.test` `tkt-9d68c883.test` `tkt-9f2eb3abac.test` `tkt-a7b7803e.test` `tkt-a7debbe0.test` `tkt-a8a0d2996a.test` `tkt-b1d3a2e531.test` `tkt-b351d95f9.test` `tkt-b72787b1.test` `tkt-b75a9ca6b0.test` `tkt-ba7cbfaedc.test` `tkt-bd484a090c.test` `tkt-bdc6bbbb38.test` `tkt-c48d99d690.test` `tkt-c694113d5.test` `tkt-cbd054fa6b.test` `tkt-d11f09d36e.test` `tkt-d635236375.test` `tkt-d82e3f3721.test` `tkt-f3e5abed55.test` `tkt-f67b41381a.test` `tkt-f777251dc7a.test` `tkt-f7b4edec.test` `tkt-f973c7ac31.test` `tkt-fa7bf5ec.test` `tkt-fc62af4523.test` `tkt-fc7bd6358f.test` `vacuum-into.test`

## Copy

- Source copy exit: `0`
- Build copy exit: `0`

## Results

- Command file: `/tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0/logs/command.txt`
- Exit: `0`
- Elapsed seconds: `2`
- Parsed summary: `0 errors out of 1721 tests`
- Parsed errors: `0`
- Parsed tests: `1721`
- Runner time: `unknown`
- Stdout bytes: `546`
- Stderr bytes: `0`

## Tail

```text
built testset in 101ms..
00:00 tcl(0/64) r1
00:02 tcl(64/64) ETC 00:00

Test database is /tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0/build/testrunner.db
Test log is /tmp/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0/build/testrunner.log
0 errors out of 1721 tests in 00:02 on sandbox-49185 Linux 64-bit
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

## Diff Checks

- `git diff --check`: exit `0`, output bytes `0`
- `git diff --cached --check`: exit `2`, output bytes `20960`

## Boundary

This is bounded local upstream SQLite Tcl runner evidence for the requested testset/patterns only. It does not claim native PHP parity, lane implementation completeness, dashboard progress, or unbounded full upstream release coverage.
