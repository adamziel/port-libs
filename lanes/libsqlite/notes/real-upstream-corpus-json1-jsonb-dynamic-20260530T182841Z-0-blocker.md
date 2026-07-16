# real-upstream-corpus-json1-jsonb-dynamic-20260530T182841Z-0 blocker

Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`.

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

Why this handoff is blocked:

The real upstream JSON dynamic corpus in the hydrated checkout has fewer than
1,000 distinct `do_execsql_test` / `do_catchsql_test` script cases in the
assigned domain. A static inventory found these test-command counts:

```text
json101.test 253
json102.test 203
json103.test 14
json104.test 33
json105.test 10
json106.test 9
json107.test 15
json108.test 5
json109.test 17
json501.test 64
json502.test 12
jsonb01.test 5
```

That totals 640 Tcl test-command blocks before expanding Tcl loops. The main
dynamic JSONB candidate, `jsonb01.test`, has one setup, 18 `jsonb_remove`
paths checked through both `json(jsonb_remove(...))` and `json_remove(...)`,
and one malformed-JSONB catch case. Porting only that section would add about
38 focused behavior checks, far below the hard handoff floor.

The obvious low-risk PHP helper surfaces are also already present in the
accepted tree: `SQLiteJsonRemove`, `SQLiteJsonPatch`, `SQLiteJsonPretty`,
`SQLiteJsonErrorPosition`, `SQLiteJsonArrayInsert`, JSON path operators, JSON
table cursor/source wiring, hidden/visible constraint pushdown, and existing
JSONB path/mutation tests. A convenience-sized direct-helper port would mostly
overlap accepted JSON helper coverage and would not satisfy any hard gate.

Required next larger batch:

Batch all real JSON corpus files listed above through a real upstream-SQL
corpus adapter or guarded runner artifact, not a generated metadata-only PHP
loop. The viable next target is a single `json101/json102/json104/json501`
plus `jsonb01` admission batch that maps each upstream `do_execsql_test` /
`do_catchsql_test` block to real SQL execution behavior and records the Tcl
script id/subtest id from the hydrated source. If that runner/adapter can
expand the Tcl `foreach` sections honestly and include `json106` table-cursor
loops, it may produce enough distinct PHP PASS lines or upstream-runner
evidence to cross the 1,000 PASS-case gate. Without that adapter, this slice
should remain blocked rather than emitting a small overlapping JSON helper
patch.

Dependency-closure note:

No new external support component is needed. The blocker is lane-local:
accepted JSON helpers exist, but this slice needs a real upstream JSON
SQL-corpus admission adapter that can execute or faithfully port the Tcl JSON
test blocks at scale while preserving real upstream filenames and subtest ids.
