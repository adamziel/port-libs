# Upstream Runner Dependency Audit - Cabal Plan Stability

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T040657Z`
- Accepted base: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83` Cabal-project runner dependency closure already recorded in the lane audit fixtures.

## Behavior

`UpstreamRunnerDependencyAudit` now records stable Cabal plan file provenance
before any solver/build step:

- expected stable plan files: `cabal.project` and `cabal.project.freeze`;
- SHA-256 and byte count provenance for present plan files;
- missing, wrong-type, and empty plan-file diagnostics;
- an explicit `unpinnedPlanRisk` when `cabal.project.freeze` is absent,
  wrong-type, or empty.

This is intentionally non-blocking for the static audit: a hydrated checkout can
still be ready for a reviewed non-mutating Cabal plan without a freeze file, but
the risk is now visible in the audit payload and activation gate instead of
being implied by prose only.

## Evidence

Focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2445 assertions, 0 failures`.

Delta against the previous detailed upstream-runner audit baseline: `+1`
focused PHP PASS case and `+16` focused assertions.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
runner, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip, external
converter, conversion service, live provider test, or live-service provider
test was executed.

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal/source fixtures, and the
focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal dependency plan before any Haskell executable
build or runner execution is considered in a separate explicitly authorized
slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags/files/native fields, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, `pandoc-cli` executable stanza,
conditional branch labels/bodies, flag-specific conditional dependencies,
`pandoc-cli` source artifact byte/hash closure, or `pandoc-cli` source
semantics.

The owned behavior is only Cabal plan-stability provenance and the
`cabal.project.freeze` unpinned-plan risk before Cabal planning.

## Follow-Up

If upstream-runner dependency work remains active, either hydrate the pinned
Pandoc checkout and run this native static audit against real Cabal/source files
before recording a non-mutating Cabal plan, or choose another non-overlapping
static source gate. Keep Pandoc/Cabal/Haskell runner execution parked unless
explicitly authorized.
