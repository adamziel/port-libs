# bulk-upstream-runner-map-gap-closure-dynamic-20260530T184901Z-0 blocked

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

Attempted upstream runner-map section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/ext/**/*.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test` harness/C/helper inventory

Blocker:

This slice cannot honestly produce a ready bulk denominator patch on the current
accepted status. The hydrated upstream checkout contains `1189` top-level
`test/*.test` files, and `lanes/libsqlite/lane-status.json` already records
mapped coverage at `1189 / 1589` from the latest accepted libsqlite source
`325bbff80e`. The existing runner-map gap helper/test in this worktree models
the older stale movement `958 -> 1189`; replaying it would overlap accepted
mapped-denominator growth rather than add new coverage.

The remaining `400` denominator units are not additional top-level
`test/*.test` files. The manifest inventory identifies the remaining map-gap
surface as extension and non-`.test` runner inventory:

- `278` extension `.test` files under `ext`
- `146` nested extension `.test` files
- `32` top-level Tcl harness files under `test`
- `33` test-directory C programs
- `47` source test C/header helpers
- `6` mptest files
- `4` tool test programs
- `76` tool test-like files

That remaining surface needs a category-aware denominator map and guarded
runner evidence. A top-level hydrated-script closure patch cannot move it
without fabricating script ids or double-counting already accepted `test/*.test`
coverage.

Focused evidence collected:

```text
git rev-parse HEAD
7e63d4798cb030955a466f3272d59cba9c03648e

python -m json.tool lanes/libsqlite/lane-status.json
...
"phpPass": 343392,
"phpFail": 0,
"currentWork": "Published source 325bbff80e with a +13279 honest selected PASS-line libsqlite batch, bringing the current run from 330113 to 343392 pass / 0 fail while keeping mapped coverage at 1189 / 1589.",
"commit": "325bbff80e7915c3b1793c4a7098457f2e106da8"

find /home/claude/port-libs/.upstream-cache/libsqlite/test -maxdepth 1 -name '*.test' | wc -l
1189

find /home/claude/port-libs/.upstream-cache/libsqlite/ext -name '*.test' | wc -l
278

python lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json inventory inspection
testDirectoryTclTests: 1189
extensionTclTests: 278
extensionNestedTclTests: 146
testDirectoryTclHarnessFiles: 32
testDirectoryCPrograms: 33
srcTestCOrHeaderHelpers: 47
mptestFiles: 6
toolTestPrograms: 4
toolTestishFiles: 76
```

Count impact:

- PHP PASS-line growth: `0`
- Behavior assertions added: `0`
- Mapped denominator rows added: `0`
- Upstream runner pass/fail rows added: `0`

Next larger batch to try:

Build a category-aware runner-map closure batch that owns the remaining
non-top-level denominator categories. The first useful batch should enumerate
real files from `ext/**/*.test`, `test/*` harness files, `test/*.c`, `mptest/*`,
and `tool/*`, then admit only rows with a matching guarded runner or explicit
non-runnable category decision. A valid ready handoff should move a coherent
subset of those remaining `400` units, cite the exact real filenames, include
duplicate-runner gating, and avoid claiming release/all parity until a
zero-error guarded artifact exists.

Dependency-closure note:

No new external support component is needed. The blocker is lane-local
denominator tooling: the existing hydrated-script map-gap helper is scoped to
top-level `test/*.test` files and must be extended or replaced with a
category-aware inventory/runner evidence adapter before the remaining map gap
can be closed honestly.
