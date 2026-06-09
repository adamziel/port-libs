# Upstream Runner Dependency Audit - pandoc-cli Conditional Branch Bodies

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T020811Z`
- Accepted base: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`
- Rework notes: only stale May 25 pandoc rework notes were present; none named this session or current base.
- Upstream cache: `/home/claude/port-libs/.upstream-cache/pandoc` was not present, so lane-local Cabal fixtures remained the source for this static audit.

## Behavior

The static upstream-runner dependency audit now treats `pandoc-cli` conditional
branch bodies as part of the closure required before a non-mutating Cabal plan
can be marked ready.

The prior audit checked that these conditional labels existed. This slice also
checks the branch body fields and disambiguates repeated `else` branches:

- `common common-options: if os(windows)` must add `cpp-options: -D_WINDOWS`.
- `executable pandoc: if arch(wasm32)` must add `hs-source-dirs: wasm`.
- the non-wasm `else` must add `ghc-options: -threaded`.
- `if flag(nightly)` must add `cpp-options: -DNIGHTLY`.
- `if flag(server)` must add `hs-source-dirs: server`; its `else` must add `cpp-options: -DNO_SERVER`.
- `if flag(lua)` must add `hs-source-dirs: lua`; its `else` must add `cpp-options: -DNO_LUA`.
- `if flag(repl)` must add `hs-source-dirs: repl`.

Drift in those branch bodies now keeps `readyForNonMutatingCabalPlan` false
even when the branch labels themselves still match.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Evidence

Baseline focused run before this slice:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2346 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2362 assertions, 0 failures`

Delta: `+1` focused PHP PASS case and `+16` focused assertions.

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f ".json_last_error_msg().PHP_EOL); exit(1); } echo "$f OK\n"; }'`
- `git diff --check -- lanes/pandoc`

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags, package files, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, or the basic `pandoc-cli` executable
stanza and branch-label closure. The owned behavior is only `pandoc-cli`
conditional branch body closure before Cabal planning.

## Dependency Closure

No new native support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal fixtures, and the focused
PHP TestRunner. Full upstream runner parity remains gated on hydrating the
pinned Pandoc checkout and recording a reviewed non-mutating Cabal dependency
plan before any Haskell build or runner execution.

## Follow-Up

If the upstream-runner audit remains active, the next non-overlapping static
slice can cover `pandoc-cli` flag-specific dependency variants. Otherwise,
park upstream-runner work until the pinned checkout and non-mutating Cabal
plan artifact are explicitly authorized.
