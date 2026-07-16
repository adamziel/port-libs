# bulk-upstream-runner-map-gap-closure-dynamic-20260530T194228Z-0

Status: blocked, no ready throughput patch.

Launcher base: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

This slice attempted to find a fresh runner-map denominator gap against the
hydrated upstream SQLite checkout at
`/home/claude/port-libs/.upstream-cache/libsqlite`. The current lane manifest
already records the real `.test` runner-map closure:

- top-level upstream `test/*.test`: `1189`
- extension and nested extension `.test` scripts admitted by the latest map
  gap closure: `283`
- manifest mapped denominator before this slice: `1472 / 1589`
- manifest mapped denominator after this slice: `1472 / 1589`
- remaining denominator rows: `117`

The remaining denominator rows are not unadmitted real `.test` scripts. The
manifest inventory shows they are non-`.test` upstream harness/tool/helper
surfaces:

- `testDirectoryTclHarnessFiles`: `32`
- `testDirectoryCPrograms`: `33`
- `srcTestCHelpers`: `44`
- `srcTestCOrHeaderHelpers`: `47`
- `mptestFiles`: `6`
- `toolTestPrograms`: `4`
- `toolTestishFiles`: `76`

Those categories require separate guarded runner/tooling evidence or explicit
blocker decisions. Admitting them through
`upstreamRunnerHydratedScriptMapGapClosure()` would either re-count already
mapped `.test` scripts or fabricate script ids for C, harness, mptest, and
tool rows. This would violate the real-upstream corpus rule and the bulk
throughput floor.

Countable movement for this slice:

- PHP PASS-line growth: `0`
- behavior assertions: `0`
- mapped denominator rows: `1472 / 1589 -> 1472 / 1589` (`+0`)
- upstream runner pass/fail rows: `0`

Next larger batch to try: build a guarded denominator classifier for the
remaining non-`.test` inventory classes. Split it by harness Tcl files, C
programs/helpers, mptest files, and tool/tool-ish rows; cite real upstream
filenames; and attach either runnable zero-error artifacts or explicit
non-portability/blocker decisions per row. Do not use stale `next965-980`
veryquick metadata or generated fake `.test` names for this remaining
denominator.

Dependency closure: no new native PHP support component is needed for this
blocker note. The next acceptance gate is runner/tooling evidence for
non-`.test` upstream inventory, not a libsqlite behavior implementation.
