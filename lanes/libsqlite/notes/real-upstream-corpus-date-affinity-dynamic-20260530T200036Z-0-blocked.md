# real-upstream-corpus-date-affinity-dynamic-20260530T200036Z-0

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

## Blocker

This slice was scoped to real upstream SQLite date/affinity behavior, but the
current accepted tree already contains the high-volume date-affinity corpus:

- `lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
  covers upstream `date.test`, `date4.test`, and `date5.test`, including the
  1000-row fractional-unixepoch loop and the large `date4` libc-format matrix.
- `lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php`
  and `SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php` cover
  upstream `date2.test` deterministic date/time expression and schema guards.
- Additional accepted followups cover date-affinity modifier, schema, auto, and
  UTC/NULL behavior.

The remaining non-overlapping upstream date-affinity source found in
`/home/claude/port-libs/.upstream-cache/libsqlite/test` is mainly
`date3.test`, with these sections:

- `date3-1.1..1.8`: `unixepoch()` integer conversion and millisecond truncation.
- `date3-1.7.1..1.7.100`: generated random unixepoch round-trip checks.
- `date3-2.1..2.40`: `auto` modifier Julian-day versus Unix-timestamp routing.
- `date3-3.1..3.2`: `unixepoch` modifier adjacency.
- `date3-4.1..4.3`: `julianday` modifier adjacency and text rejection.
- `date3-5.0`: first-63-days-of-1970 `auto` ambiguity count.

Porting `date3.test` directly would add roughly 120 distinct focused PASS cases,
well below the active hard handoff floor for `real-upstream-corpus-*` slices
(`>=1000` focused PASS cases or `>=5000` behavior assertions). Expanding the
100-row Tcl random loop into thousands of static rows would inflate beyond the
real upstream subtest denominator and would violate the real-upstream corpus
rule.

## Next Larger Batch

The next acceptable date/affinity batch should combine `date3.test` with a
disjoint real upstream affinity corpus, such as `affinity2.test`,
`affinity3.test`, `types.test`, `types2.test`, and `types3.test`, and port the
combined SQL/type-affinity matrix into one focused PHP test family. That batch
can plausibly satisfy the hard floor without fabricating script ids or repeating
the accepted date corpus.

## Verification

- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed for the attempted `date3.test` section. The
existing `SQLiteCoreScalarFunction` date/time implementation already exposes the
relevant entry points; the blocker is corpus size/non-overlap, not missing
infrastructure.
