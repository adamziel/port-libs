# bulk-upstream-runner-map-gap-closure-dynamic-20260530T203215Z-0

Status: blocked, no ready throughput patch.

Launcher base: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.

This slice rechecked runner-map gap closure against the current accepted
worktree and the hydrated upstream SQLite checkout at
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Current accepted evidence already covers the countable real `test/*.test`
runner-map path:

- lane status mapped denominator: `1472 / 1589`
- hydrated upstream `test/*.test` scripts: `1189`
- accepted real script map closure includes the non-overlapping
  `next1045-1161` range from `valuesfault.test` through `windowA.test`
- current source also contains the later guarded tail audit for
  `walseh1.test` through `zipfilefault.test`

The apparent old `next965-980` hole is explicitly stale. Reusing it here would
overlap the supervisor-excluded stale path and would not add non-overlapping
accepted coverage on top of the current base. The remaining denominator rows
are not fresh hydrated `test/*.test` scripts that can be admitted through the
bulk script-map helper.

Countable movement for this slice:

- PHP PASS-line growth: `0`
- behavior assertions: `0`
- mapped denominator rows: `1472 / 1589 -> 1472 / 1589` (`+0`)
- upstream runner pass/fail rows: `0`

Next larger batch to try: build a guarded classifier for the remaining
non-`test/*.test` inventory surfaces using real upstream filenames and
per-category evidence. The useful split is harness Tcl files, upstream C test
programs/helpers, mptest files, and tool/tool-ish rows. Each row needs either a
runnable zero-error artifact or an explicit blocker/non-portability decision.
Do not represent these rows as generated `.test` script ids, do not revive
`next965-980`, and do not count static metadata loops as TestRunner PASS-line
growth.

Dependency closure: no new native PHP support component is needed for this
blocked handoff. The next acceptance gate is upstream runner/tooling evidence
for non-`.test` inventory, not libsqlite runtime behavior.
