# Upstream Runner Dependency Audit - Partial Checkout Gate

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T035039Z`
- Accepted base: `64291fcd23e3d1b723e600a8842760d1fbcdb417`
- Rework notes: none named this lane in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Upstream cache scan: no `cabal.project`, `cabal.project.freeze`, `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `pandoc-server.cabal`, `pandoc-cli.cabal`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files were found under
  `/home/claude/port-libs/.upstream-cache` with the bounded cache search used
  for this audit.

## Behavior

`PandocUpstreamRunnerDependencyAudit` now treats a local Pandoc checkout as
hydrated only when it contains the project file, all package manifests needed
by the upstream Cabal project, and both Tasty test entrypoints:

- `cabal.project`
- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `pandoc-server/pandoc-server.cabal`
- `pandoc-cli/pandoc-cli.cabal`
- `test/test-pandoc.hs`
- `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`

Before this slice, the lightweight lane/cache-root audit could mark a partial
checkout ready when it had only the `pandoc` and `pandoc-lua-engine` Cabal
files plus the two Tasty entrypoints. That was weaker than the detailed
`UpstreamRunnerDependencyAudit`, which already treats the project file and the
server/CLI package manifests as preconditions for a useful non-mutating Cabal
plan.

The ready gate still does not execute Pandoc, Cabal, Stack, Haskell test
binaries, benchmarks, office tools, zip/unzip, external converters, online
services, live provider tests, or live-service provider tests. A checkout with
`cabal.project` but no `cabal.project.freeze` can still reach the
non-mutating-plan stage, but the activation gate now names `cabal.project.freeze`
as the exact unpinned-plan risk instead of only warning when both project files
are absent.

## Evidence

Baseline before this slice:

`php tools/run-tests.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 30 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 37 assertions, 0 failures`

Delta: `+1` focused PHP PASS case and `+7` focused assertions.

Combined dependency-audit family:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `3 test files, 2514 assertions, 0 failures`

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/PandocUpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f OK\n"; }'`
- `git diff --check -- lanes/pandoc`

Result: all passed.

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted static audits for detailed Cabal package identity,
setup hooks, package flags, package files, project pins/packages/flags/
constraints, runner/benchmark dependencies/options/modules/file artifacts,
Lua-engine library closure, server library closure, `pandoc-cli` executable
stanza, conditional branch labels, conditional branch body fields,
flag-specific conditional dependencies, `pandoc-cli` source artifact byte/hash
closure, or `pandoc-cli` source semantics.

The owned behavior is only the lightweight local source-hydration gate used
before considering any non-mutating Cabal plan.

## Dependency Closure

No new native support component is needed. This reuses
`PandocUpstreamRunnerDependencyAudit`, lane-local fixture directories, and the
focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
containing the project file, all four package manifests, and both Tasty
entrypoints. After hydration, the next safe step is a reviewed non-mutating
Cabal dependency plan; Haskell runner execution remains a separate explicitly
authorized slice.

## Follow-Up

If the upstream-runner audit remains active, the next non-overlapping step is
to hydrate the pinned Pandoc checkout only with explicit authorization and
record a non-mutating Cabal plan artifact, or to continue static gates around
plan stability without running any upstream executable.
