# Upstream Runner Dependency Audit - pandoc-cli Flag Dependency Closure

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T023900Z`
- Accepted base: `cff2757f3c2ce59e8912b5b48a787409562aacb3`
- Rework notes: none named this session or current base.
- Upstream cache: no hydrated Pandoc checkout/project Cabal files were found in `/home/claude/port-libs/.upstream-cache`, so this remains a static audit.
- Upstream source truth: pinned `pandoc-cli/pandoc-cli.cabal` at Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Behavior

The static upstream-runner dependency audit now treats `pandoc-cli` flag and
wasm conditional dependency/source closure as part of the pre-Cabal-plan gate.

The previous slice checked conditional branch labels and body fields. This
slice tightens those expected branch bodies to the pinned upstream
`pandoc-cli.cabal` contract:

- `if arch(wasm32)` must include `hs-source-dirs: wasm`, `PandocWasm`,
  `-DINCLUDE_WASM`, the wasm export `ghc-options`, and the bounded wasm
  dependency set.
- `if flag(nightly)` must include `-DNIGHTLY`, `template-haskell`, and `time`.
- `if flag(server)` must include `server` plus the `pandoc-server`,
  `wai-extra`, `warp`, and `safe` dependencies; its `else` must use
  `hs-source-dirs: no-server`.
- `if flag(lua)` must include `lua` plus bounded `pandoc-lua-engine`; its
  `else` must use `hs-source-dirs: no-lua`.
- `if flag(repl)` must include `-DREPL`, `hslua-cli`, and `temporary`.

Drift in these flag-specific dependencies or placeholder source directories
keeps `readyForNonMutatingCabalPlan` false before any Cabal solver/build step.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Evidence

Baseline focused run before this slice:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2362 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2378 assertions, 0 failures`

Delta: `+1` focused PHP PASS case and `+16` focused assertions.

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `git diff --check -- lanes/pandoc`

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags, package files, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, the `pandoc-cli` executable stanza,
basic branch-label closure, or generic conditional branch body closure. The
owned behavior is only pinned upstream flag-specific `pandoc-cli`
dependency/source/cpp closure before Cabal planning.

## Dependency Closure

No new native support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal fixtures, and the focused
PHP TestRunner. Full upstream runner parity remains gated on a hydrated pinned
Pandoc checkout and a reviewed non-mutating Cabal dependency plan before any
Haskell build or runner execution.

Local availability observed for this audit: `ghc` and `cabal` are present;
`stack` and `pandoc` are absent from `PATH`. That is sufficient for the static
audit and insufficient for a safe upstream runner execution slice.

## Follow-Up

If the upstream-runner audit remains active, the next non-overlapping static
slice can cover conditional source artifact existence for the `wasm`, `server`,
`no-server`, `lua`, and `no-lua` directories. Otherwise, keep runner execution
parked until the hydrated checkout and non-mutating Cabal plan gate are
explicitly authorized.
