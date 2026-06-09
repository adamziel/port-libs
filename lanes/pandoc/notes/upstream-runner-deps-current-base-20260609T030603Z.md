# Upstream Runner Dependency Audit - pandoc-cli Source Artifacts

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T030603Z`
- Accepted base: `82ece526c3b1abf329ce3c42e1c2113cbac669aa`
- Rework notes: none named this session or current base.
- Upstream cache: no hydrated Pandoc checkout/project Cabal files were found in `/home/claude/port-libs/.upstream-cache`, so this remains a static audit.
- Upstream source truth: pinned `pandoc-cli` tree at Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Behavior

The static upstream-runner dependency audit now treats `pandoc-cli` executable
source artifacts as part of the pre-Cabal-plan gate. A checkout can no longer
be marked `readyForNonMutatingCabalPlan` unless these pinned source artifacts
exist as non-empty files and have recorded byte/hash provenance:

- `pandoc-cli/src/pandoc.hs`
- `pandoc-cli/wasm/PandocWasm.hs`
- `pandoc-cli/server/PandocCLI/Server.hs`
- `pandoc-cli/no-server/PandocCLI/Server.hs`
- `pandoc-cli/lua/PandocCLI/Lua.hs`
- `pandoc-cli/no-lua/PandocCLI/Lua.hs`

This extends the previous slice's Cabal conditional field checks. The earlier
audit verified that `hs-source-dirs` selected the expected enabled/disabled
source directories; this slice verifies that those directories actually supply
the executable entry file and conditional shim modules needed before a reviewed
non-mutating Cabal plan is useful.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
command, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Evidence

Baseline focused run before this slice:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2378 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2401 assertions, 0 failures`

Delta: `+2` focused PHP PASS cases and `+23` focused assertions.

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f ".json_last_error_msg().PHP_EOL); exit(1); } echo "$f OK\n"; }'`
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `git diff --check -- lanes/pandoc`

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags, package files, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, `pandoc-cli` executable stanza,
conditional branch labels, conditional branch body fields, or flag-specific
conditional dependencies. The owned behavior is only `pandoc-cli` source
artifact existence/type/non-empty/provenance closure for the executable entry
file and enabled/disabled Lua/server/wasm conditional source trees.

## Dependency Closure

No new native support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal fixtures, and the focused
PHP TestRunner. Full upstream runner parity remains gated on a hydrated pinned
Pandoc checkout and a reviewed non-mutating Cabal dependency plan before any
Haskell build or runner execution.

Local availability remains sufficient only for the static audit path: the
previous audit observed `ghc` and `cabal` on `PATH`, with `stack` and `pandoc`
absent. This slice did not run tool discovery again and did not execute any
solver/build/test command.

## Follow-Up

If the upstream-runner audit remains active, the next non-overlapping static
slice can cover source semantics inside the `PandocCLI.Lua` and
`PandocCLI.Server` enabled/disabled shims. Otherwise, keep runner execution
parked until the pinned checkout and non-mutating Cabal plan artifact are
explicitly authorized.
