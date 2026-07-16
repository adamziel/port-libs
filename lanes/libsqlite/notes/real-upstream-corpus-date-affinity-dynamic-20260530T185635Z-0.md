# real-upstream-corpus-date-affinity-dynamic-20260530T185635Z-0

Status: blocked as an additive throughput handoff.

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

## Upstream Sections Checked

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

## Blocker

The assigned date/affinity dynamic surface is already covered on this accepted
base by existing real-upstream corpus tests:

- `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` ports `date.test`,
  `date4.test` through `date4-24858`, and the generated `date5.test` Gregorian
  cycle.
- `SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php` and
  `SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php` cover
  `date3.test` `unixepoch`, `auto`, `julianday`, and first-63-days ambiguity
  sections.
- `SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php` and
  `SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php` cover
  `date2.test` deterministic schema/index/check behavior.
- `SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php`,
  `SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`, and the
  `SQLiteRealUpstreamExpressionAffinityDynamic*` family cover the obvious
  `affinity2.test`, `affinity3.test`, `types2.test`, and `types3.test`
  affinity surfaces.

Adding another convenience-sized file here would duplicate accepted PASS lines
and would not satisfy the throughput handoff floor of at least 1,000 distinct
new TestRunner PASS cases, 5,000 behavior assertions, or a named blocker fix.

## Next Larger Batch

Use a fresh non-overlapping upstream source outside this exhausted
date/affinity slice. The best next real-corpus refill is a broad `veryquick`
or suite-denominator shard that excludes the files above, then ports at least
1,000 distinct new PHP PASS cases or proves a runner-map/mapped-denominator
unlock with guarded upstream-runner evidence.

## Verification

- No PHP source or test files changed.
- `git diff --check -- lanes/libsqlite` should be run after this note is
  written.

## Dependency Closure

No new support component is proposed. The attempted slice reuses existing
date/time scalar and affinity-comparison support; the blocker is overlap with
already accepted real upstream coverage, not missing PHP support.
