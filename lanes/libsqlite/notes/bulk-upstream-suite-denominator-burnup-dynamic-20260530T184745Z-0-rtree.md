# Bulk upstream suite denominator burnup dynamic RTREE evidence

- Slice: `bulk-upstream-suite-denominator-burnup-dynamic-20260530T184745Z-0`
- Session: `port-dev-sqlite-yield-dyn-bulk-suite-20260530T184745Z`
- Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`
- Lane status before attempt: `343392` PHP PASS, `0` PHP FAIL, mapped coverage `1189 / 1589`
- Expected mapped coverage after admitting this guarded artifact: `1216 / 1589`
- Countable PASS-line growth: `0`
- Countable PHP behavior assertions: `0`
- Countable mapped denominator growth: `+27`
- Upstream runner pass/fail rows: `202` upstream Tcl tests, `0` errors

## Guarded Runner Evidence

The remaining direct top-level `test/*.test` denominator surface is already
closed at `1189` real hydrated scripts. This slice therefore targeted one
coherent non-top-level upstream inventory group, `ext/rtree`, instead of
extending historical generated shard ids.

Guarded runner command:

```text
/home/claude/port-libs/scripts/run-sqlite-tcl-bounded-runner.sh bulk-denom-ext-rtree-20260530T184745Z /tmp/bulk-denom-ext-rtree-20260530T184745Z.md /tmp/bulk-denom-ext-rtree-20260530T184745Z-scratch /tmp/bulk-denom-ext-rtree-20260530T184745Z.log all 1 300 ext/rtree/*.test
```

Runner result:

```text
Exit: 0
Parsed summary: 0 errors out of 202 tests
SQLite 2026-05-21 15:14:35 9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d
```

Runner gates recorded by the audit:

- active SQLite testfixture runners: `0`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- audit artifact: `/tmp/bulk-denom-ext-rtree-20260530T184745Z.md`
- runner log: `/tmp/bulk-denom-ext-rtree-20260530T184745Z.log`

## Real Upstream Scripts

The guarded artifact covers these `27` real hydrated upstream scripts:

- `ext/rtree/rtree1.test`
- `ext/rtree/rtree2.test`
- `ext/rtree/rtree3.test`
- `ext/rtree/rtree4.test`
- `ext/rtree/rtree5.test`
- `ext/rtree/rtree6.test`
- `ext/rtree/rtree7.test`
- `ext/rtree/rtree8.test`
- `ext/rtree/rtree9.test`
- `ext/rtree/rtreeA.test`
- `ext/rtree/rtreeB.test`
- `ext/rtree/rtreeC.test`
- `ext/rtree/rtreeD.test`
- `ext/rtree/rtreeE.test`
- `ext/rtree/rtreeF.test`
- `ext/rtree/rtreeG.test`
- `ext/rtree/rtreeH.test`
- `ext/rtree/rtreeI.test`
- `ext/rtree/rtreeJ.test`
- `ext/rtree/rtreecheck.test`
- `ext/rtree/rtreecirc.test`
- `ext/rtree/rtreeconnect.test`
- `ext/rtree/rtreedoc.test`
- `ext/rtree/rtreedoc2.test`
- `ext/rtree/rtreedoc3.test`
- `ext/rtree/rtreefuzz001.test`
- `ext/rtree/tkt3363.test`

## Non-Overlap

This evidence does not touch the previous synthetic
`bulk-suite-b-*.test` denominator rows and does not claim PHP PASS-line growth.
It is a real non-top-level upstream runner artifact for `ext/rtree/*.test`,
which is outside the already mapped top-level `test/*.test` surface and outside
the accepted real-PHP corpus batches for SQL, JSON, WAL, B-tree, trigger,
PRAGMA, date, expression, UPSERT, VFS, and window behavior.

## Dependency Closure

No new PHP support component is needed for this denominator movement. The
required support was the existing guarded upstream runner plus the hydrated
SQLite source/build cache. The integrator can admit this as mapped denominator
growth only if the lane policy counts non-top-level extension `.test` files
from zero-error guarded artifacts.

## Next Larger Batch

The next denominator-burnup worker should repeat this pattern for another real
non-top-level group, preferably `ext/fts5/test/*.test`, `ext/session/*.test`,
`ext/rbu/*.test`, `ext/recover/*.test`, or `mptest/*.test`, and should cite
the exact real files and parsed runner summary before changing mapped counts.
