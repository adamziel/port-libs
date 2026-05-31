Real upstream corpus UPSERT RETURNING dynamic alias/default slice

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`.
- Ported sections: `upsert2-200`, `upsert2-201`, and `upsert2-202`.
- Behavior: `INSERT INTO main.t1 AS t2(a,b) SELECT ... ON CONFLICT(a) DO UPDATE`
  must evaluate `t2.c+1` and `t2.b<excluded.b` against the current target row
  image even when the incoming SELECT omits column `c`; the original table
  qualifier remains hidden after the target alias is introduced.
- Implementation: `SQLiteUpsertReturningSql` now completes incoming rows with
  stable omitted-column defaults observed in the current target image before
  dispatching UPSERT execution.
- Focused coverage: `SQLiteRealUpstreamCorpusUpsertReturningDynamicAliasDefaultTest.php`
  adds 1006 TestRunner PASS cases and 3007 assertions, including 1000 dynamic
  row-stream cases.
- Non-overlap: this does not repeat the accepted no-target rowid stream,
  composite tail, visible constraint, JSON, WAL, B-tree, or VFS clusters; it
  specifically covers schema-qualified target aliases plus omitted DEFAULT
  target columns in `upsert2.test`.
- Dependency closure: no new support component is needed; the existing bounded
  UPSERT RETURNING SQL executor is extended.
