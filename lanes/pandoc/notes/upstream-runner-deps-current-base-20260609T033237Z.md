# Upstream Runner Dependency Audit - pandoc-cli Source Semantics

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T033237Z`
- Accepted base: `5a15a7a63f3c59d035e33a0be022ea134979a702`
- Rework notes: none named this lane in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Upstream cache: no hydrated Pandoc checkout or project Cabal files were found under `/home/claude/port-libs/.upstream-cache`.
- Local tool probe: `ghc=9.10.3`, `cabal=3.12.1.0`, `stack=not-on-path`.
- Upstream source truth: pinned Pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, with direct source checks against the raw `pandoc-cli/src/pandoc.hs`, `pandoc-cli/wasm/PandocWasm.hs`, `pandoc-cli/server/PandocCLI/Server.hs`, `pandoc-cli/no-server/PandocCLI/Server.hs`, `pandoc-cli/lua/PandocCLI/Lua.hs`, and `pandoc-cli/no-lua/PandocCLI/Lua.hs` files.

## Behavior

`UpstreamRunnerDependencyAudit` now treats the `pandoc-cli` source artifacts as
semantic preconditions, not just present non-empty files. A hydrated checkout is
blocked from `readyForNonMutatingCabalPlan` if the pinned CLI sources do not
contain the expected static markers for:

- main executable dispatch to `PandocCLI.Lua`, `PandocCLI.Server`, server mode,
  `pandoc lua`, scripting-engine selection, and feature reporting;
- wasm `convert` and `query` exports, virtual `/stdout` output, default-template
  lookup, and scripting-engine loading;
- enabled server mode through CGI timeout middleware, parsed server options, and
  Warp port execution;
- disabled server mode through the unsupported handler and `ExitFailure 4`;
- enabled Lua mode through `#ifdef REPL`, standalone Lua settings, pandoc Lua
  engine imports, and the no-REPL fallback message;
- disabled Lua mode through `PandocNoScriptingEngine` and `pure noEngine`.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
runner, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip, external
converter, online service, live provider test, or live-service provider test
was executed.

## Evidence

Baseline from the previous upstream-runner dependency note:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2401 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2417 assertions, 0 failures`

Delta: `+1` focused PHP PASS case and `+16` focused assertions.

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f ".json_last_error_msg().PHP_EOL); exit(1); } echo "$f OK\n"; }'`
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`
- `git diff --check -- lanes/pandoc`

Combined focused dependency-audit result:
`3 test files, 2507 assertions, 0 failures`

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags, package files, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, `pandoc-cli` executable stanza,
conditional branch labels, conditional branch body fields, flag-specific
conditional dependencies, or `pandoc-cli` source artifact byte/hash closure.
The owned behavior is only static source semantics inside the `pandoc-cli`
enabled/disabled Lua, server, wasm, and main executable source files.

## Dependency Closure

No new native support component is needed. This reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal fixtures, and the focused PHP
TestRunner. Full upstream runner parity remains gated on a hydrated pinned
Pandoc checkout, then a reviewed non-mutating Cabal solver/build plan, before
any Haskell executable build or runner execution is considered in a separate
authorized slice.

## Follow-Up

The next non-overlapping upstream-runner dependency slice can inspect deeper
`pandoc-cli/src/pandoc.hs` option-dispatch semantics around version handling
and feature reporting, or move to hydrating the pinned checkout and recording a
non-mutating Cabal plan if the supervisor explicitly authorizes that runner
audit step.
