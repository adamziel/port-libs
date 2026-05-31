# real-upstream-corpus-upsert-returning-dynamic-20260531T063623Z-0

Status: blocked as overlap on accepted base `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`.

The assigned upstream behavior cluster is already present in
`lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php`.
That focused file cites and ports the relevant dynamic `upsert2.test` sections:

- `upsert2.test` `upsert2-100`: VALUES source `ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b`.
- `upsert2.test` `upsert2-200`: SELECT source repeated conflicts over the current target row image.
- `upsert2.test` `upsert2-320`: failed `DO UPDATE WHERE` emits no changed row and no `RETURNING` row.

Focused verification on this worktree:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php
1 test files, 1121 assertions, 0 failures
```

I did not add another dynamic UPSERT/RETURNING test batch because the current
tree already has extensive adjacent upstream coverage under
`lanes/libsqlite/tests/SQLiteRealUpstream*Upsert*Returning*Dynamic*.php`, and
recent progress notes record parked UPSERT candidates for notes-only overlap
and adjacent guard failures. Adding a tiny or duplicated PASS batch here would
violate the real-upstream corpus hard handoff floor.

Next larger non-overlapping batch to try: select a different upstream domain or
a verified unowned UPSERT section after checking the current accepted tests by
filename, then target at least 1,000 distinct TestRunner PASS cases or a named
behavior fix that unlocks at least 2,000 cases. Candidate UPSERT work should
avoid `upsert2.test` 100/200/320 and the already covered `upsert5.test`
conflict-arm matrices unless it proves a new failing behavior rather than more
static matrix expansion.

Dependency closure: no new support component is needed; the existing
`SQLiteUpsertReturningDynamicPlan`/UPSERT helper coverage is reused.
