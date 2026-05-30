real-upstream-corpus-upsert-returning-dynamic-20260530T205550Z-0

Base accepted HEAD: f32e8deaca85f9598bd0eb6230903f7d3fab9f57

Attempted upstream scope:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test:
  generalized UPSERT arm priority sections 1.*.100 through 1.*.505.
- /home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test:
  DO UPDATE WHERE, WITHOUT ROWID parity, trigger firing, DO NOTHING, and failed
  WHERE sections 100, 110, 300, 310, 320, 400, 410, and 420.
- /home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test:
  UPSERT RETURNING mixed insert/update sections 4.2 and 4.5.

Result:
- The current accepted base already contains
  lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php
  and lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php covering this
  assigned dynamic UPSERT/RETURNING cluster.
- Focused verification passed with 1 selected test file, 2616 assertions, and
  0 failures.
- No new non-overlapping behavior was added because a duplicate patch for the
  same upstream upsert5/upsert2/returning1 dynamic sections would violate the
  real-upstream non-overlap rule.

Next larger batch to try:
- Move outside this already-owned dynamic UPSERT/RETURNING cluster. Candidate
  sources are RETURNING clause name-resolution/error behavior in
  returning1.test sections 6.* through 8.* or a distinct UPSERT target-analysis
  batch from upsert4.test sections 2.* through 5.*, provided the integrator
  first confirms those sections are not already covered by the latest accepted
  sweep.

Dependency closure:
- No new support component is needed. Existing bounded PHP row-array UPSERT
  and RETURNING helpers were reused for the focused verification.
