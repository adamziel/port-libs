# Upstream Runner Dependency Audit - Pandoc Server Library Closure

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T011621Z`
- Accepted base: `403bbfa850b87a30b18d0488738d4e785be58580`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Upstream cache: `/home/claude/port-libs/.upstream-cache/pandoc` was not present in this isolated worktree; lane-local pinned Cabal fixtures were used for the static audit.

## Behavior

The static upstream-runner dependency audit now treats the `pandoc-server`
package's default library as part of the Cabal plan readiness closure. It
records and gates:

- direct `build-depends` names;
- pinned version constraints;
- `Text.Pandoc.Server` exposed module;
- `hs-source-dirs: src`;
- `default-language: Haskell2010`.

Drift in any of those fields now keeps `readyForNonMutatingCabalPlan` false
and adds explicit blocked reasons. No external command was run.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 2238 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 2273 assertions, 0 failures`.
- Focused delta: `+1` PASS case and `+35` assertions.
- Example smoke: not run; audit-only slice has no WordPress-visible conversion path.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, the native Cabal stanza parser, and lane-local
Cabal fixture builders.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout and recording a reviewed non-mutating Cabal plan before any Haskell
runner or benchmark execution.

## Non-Overlap

This does not repeat prior audit slices for package identity, setup, package
flags, package data/extra/native fields, project pins/packages/flags/
constraints/source-repository fields, runner/benchmark dependency/option/
module/file/conditional/native-field closure, or pandoc-lua-engine library
closure. The owned behavior is only `pandoc-server` default-library dependency/
exposed-module/source-dir/default-language closure before Cabal planning.

## Follow-Up

A next non-overlapping audit slice could cover `pandoc-cli` executable/library
component dependency closure, or park static runner audits until a hydrated
checkout is available for a reviewed non-mutating Cabal plan.
