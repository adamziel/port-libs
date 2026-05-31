# real-upstream-corpus-date-affinity-dynamic-20260531T121535Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix1000To1999Test.php` as an additive real upstream date/affinity corpus batch.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Scenario: `atof-3.2`
- Owned range: decimal REAL suffixes `1000..1999` from `format('18.44674407370955%04d', i)`.

## Behavior

- 1,000 distinct focused TestRunner cases compare native `CAST(... AS REAL)`, `typeof()`, `format('%.10e', ...)`, `GLOB`, quote/REAL round-trip, and insert REAL affinity against a local `sqlite3` oracle.
- The batch checks the upstream invariant that `CAST(vtxt AS REAL)` for decimal text beginning with `18.44674407370955` stays in the `18.446744073709*` textual REAL range.
- A generic application rollup verifies the same REAL-affinity behavior for non-domain decimal metric rows.

## Non-Overlap

This owns only upstream `atof1.test` `atof-3.2` suffixes `1000..1999`. It avoids the accepted `atof-3.2` suffixes `0000..0999`, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, accepted `atof2` rounding and alternate-form coverage, `date4` row ranges, date/timediff matrices, and types storage-class batches.

## Evidence

- New focused PASS cases: 1,003.
- Behavior assertions: 14,022.
- Mapped denominator remains `1589 / 1589`; this is upstream corpus PASS-line growth inside an already mapped script.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity helpers, `SQLiteCoreScalarFunction` quote/format behavior, and a local `sqlite3` oracle for hydrated upstream parity.
