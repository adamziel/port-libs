# bulk-upstream-runner-map-gap-closure-dynamic-20260530T185734Z-0 blocked

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

Attempted upstream runner-map section:

- Hydrated SQLite upstream cache:
  `/home/claude/port-libs/.upstream-cache/libsqlite`
- Existing lane-local dynamic helper:
  `lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php`
- Existing top-level script surface:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test`
- Remaining non-top-level inventory categories:
  extension tests, Tcl harness helpers, C helper programs, mptest files, and
  tool test files.

Blocker:

This slice cannot honestly produce a ready bulk denominator patch on the
current accepted base. The current lane status already records mapped coverage
at `1472 / 1589`, leaving `117` rows. The only existing dynamic runner-map
closure helper in this worktree models the stale top-level hydrated script
movement from `958 -> 1189`; replaying or extending that helper would overlap
accepted top-level `test/*.test` coverage instead of adding new rows.

The hydrated upstream checkout contains these real inventory counts:

- top-level `test/*.test`: `1189`
- extension `ext/**/*.test`: `278`
- test-directory Tcl harness files: `31`
- test-directory C programs: `31`
- source test C/header helpers: `47`
- mptest files: `6`
- tool test-like files: `5`

Those raw categories are larger than the remaining `117` rows and the accepted
manifest in this worktree does not expose a current, file-level list of the
already-mapped `1472` denominator units. Without that accepted file-level map
or a guarded runner artifact naming the exact remaining rows, any attempt to
select `117` category files would be ambiguous and could double-count accepted
extension, harness, or tool rows.

Count impact:

- PHP PASS lines: `355604 -> 355604` (`+0`)
- Behavior assertions added: `0`
- Mapped denominator rows: `1472 / 1589 -> 1472 / 1589` (`+0`)
- Upstream runner pass/fail rows admitted: `0`

Next larger batch to try:

Build a category-aware map-gap adapter that reads or generates an accepted
file-level denominator ledger for all `1589` units, not just top-level
`test/*.test` scripts. The next valid ready handoff should either:

- identify the exact remaining `117` real filenames with accepted non-overlap
  evidence and guarded runner/admission artifacts; or
- run a bounded, zero-error upstream artifact for a coherent remaining category
  and admit only those exact real files that are not already present in the
  accepted file-level map.

Dependency closure:

No new external support component is needed. The blocker is lane-local mapping
tooling/evidence: the existing helper validates real top-level `.test`
filenames, but the remaining denominator gap requires a category-aware
file-level ledger plus guarded runner evidence for extension, harness, C,
mptest, or tool inventory rows.

Verification:

- `git diff --check -- lanes/libsqlite`
