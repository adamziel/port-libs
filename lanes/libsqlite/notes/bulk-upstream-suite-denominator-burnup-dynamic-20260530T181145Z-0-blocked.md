# Bulk Upstream Suite Denominator Burnup Dynamic Blocker

Slice: `bulk-upstream-suite-denominator-burnup-dynamic-20260530T181145Z-0`

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

Attempted upstream section: dynamic suite denominator burnup over the hydrated SQLite upstream cache at `/home/claude/port-libs/.upstream-cache/libsqlite`.

Observed counts:

- Current manifest `benchmarkDenominator.total`: `1589`
- Current manifest `benchmarkDenominator.mapped`: `1189`
- Hydrated `/test/*.test` files: `1189`
- Hydrated `.test` files under `/test`, `/ext/*/test`, and `/mptest`: `1339`
- New PHP PASS lines added by this slice: `0`
- New behavior assertions added by this slice: `0`
- New mapped denominator rows admitted by this slice: `0`
- Upstream runner pass/fail rows admitted by this slice: `0`

Blocker: the real hydrated `/test/*.test` surface is already fully mapped in the accepted manifest. The finite remaining denominator gap is `400` rows, but those rows are not represented by unmapped real `/test/*.test` scripts in the hydrated cache. The broader cache has only `150` additional `.test` files under extension or mptest directories, and this slice has no guarded zero-error runner artifacts proving those files are countable as suite denominator movement. Admitting them as plain generated rows would violate the current bulk rule against fabricated script ids and metadata-only PASS inflation.

Next larger batch to try:

1. Define the remaining `400` denominator rows from real upstream inventory categories beyond `/test/*.test`, including exact source paths and countability rules.
2. Add or reuse a guarded runner artifact parser that accepts those paths without reducing them to invented basename-only script ids.
3. Run a bounded guarded shard that produces zero-error evidence for a large subset, then admit only those real artifact-backed rows.

Dependency closure: no new PHP runtime support component is needed for this blocker note. The missing dependency is upstream-suite inventory and guarded-runner evidence for non-`/test/*.test` denominator rows.

Root harness: not run - isolated micro-slice.
