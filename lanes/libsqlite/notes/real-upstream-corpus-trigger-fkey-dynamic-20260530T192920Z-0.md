# real-upstream-corpus-trigger-fkey-dynamic-20260530T192920Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T192920Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey4.test`
- Ported scenario ranges:
  - `fkey3-3.1.1..3.6.5`: self-referential foreign-key inserts, including a new row matching itself, `INTEGER PRIMARY KEY` NULL rowid assignment before FK checking, and composite parent-key order following the FK declaration.
  - `fkey4-1.1..1.4`: a deferred foreign-key violation in an autocommit statement rolls back that statement and does not leave a transaction open for the next prepared statement.
- Focused assertions added: `5,926` behavior assertions in `SQLiteRealUpstreamTriggerFkeyDynamicSelfReferenceCorpusTest.php`.
- Non-overlap: this does not repeat the accepted `fkey1`, `fkey2`, `e_fkey`, `fkey6`, `trigger1`, `trigger2`, `trigger3` RAISE, or `triggerC` action/savepoint/view/RAISE batches. The new surface is `fkey3.test` self-referential parent lookup timing plus `fkey4.test` autocommit deferred-FK statement cleanup.
- Dependency closure: no new support component is needed; this reuses the existing dynamic trigger/FK row-array behavior helper.
