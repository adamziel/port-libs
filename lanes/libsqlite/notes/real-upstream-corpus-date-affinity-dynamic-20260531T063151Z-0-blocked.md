# real-upstream-corpus-date-affinity-dynamic-20260531T063151Z-0

Status: blocked by accepted current-base coverage saturation for the assigned
date-affinity dynamic surface.

## Upstream source inspected

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- upstream loop: `for {set i 0} {$i<=24858} {incr i}` with
  `SELECT strftime($::FMT,$::TS,'unixepoch');`
- nearby real affinity candidates:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`

## Blocker

The obvious high-yield date target for this slice is already saturated in the
accepted lane. Current focused PHP files cover `date4.test` rows through
`24858`, including `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows2300To3299Test.php`
through `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To24858Test.php`
plus earlier `date4` continuation files. Existing date-affinity files also
cover `date.test`, `date2.test`, `date3.test`, `date5.test`, invalid
`strftime`, component validation, `timediff`, modifier-index, Unix epoch,
boundary, and date/affinity type matrix behavior.

The nearby fallback surfaces are not available for a fresh high-yield
date-affinity handoff either. `types3.test` sections `types3-3.1..3.5` are
already ported by
`SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes3DualRep20260531T051009ZTest.php`
with 1,440 focused cases, and `affinity3.test` sections `affinity3-100..142`
and `affinity3-200..260` are already ported by
`SQLiteRealUpstreamExpressionAffinity3DynamicTest.php` and related
real-affinity shards.

Adding another date4 range, a generated-looking duplicate matrix, or
metadata-only assertions would violate the hard handoff floor and the real
upstream corpus rule. This slice cannot honestly add 1,000 distinct fresh
TestRunner PASS cases or 5,000 fresh behavior assertions from the assigned
surface on base `7685e747971ca86ceced872addf2e1032378bd34`.

## Next larger batch to try

Pick a non-date-affinity known-red cluster instead of another date4 split:

- expression IS/unary-plus semantics;
- JSON1/JSONB aggregate or JSON502 escaped-path regressions;
- pager/WAL default-memory pressure;
- PRAGMA schema expected-shape failures;
- SELECT limit or compound-collation broad diagnostic failures.

If the next worker must stay in date/time, first inventory upstream
`timediff1.test` and any date scenario not matched by existing
`SQLiteRealUpstream*Date*` files, then only proceed if the selected scenario
can produce at least 1,000 non-overlapping focused PASS cases or a behavior fix
that unlocks a larger accepted batch.

## Dependency closure

No new support component was needed. The blocker is coverage overlap, not a
missing native PHP dependency.
