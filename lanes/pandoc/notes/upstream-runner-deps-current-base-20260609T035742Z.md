# Upstream Runner Dependency Audit - pandoc-cli Version Semantics

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T035742Z`
- Accepted base: `bc187b52dace5db0ab124375f4ca1c25f2f84168`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83` `pandoc-cli/src/pandoc.hs`; a read-only raw source check confirmed the option/version markers.

## Behavior

`UpstreamRunnerDependencyAudit` now blocks non-mutating Cabal-plan readiness
when `pandoc-cli/src/pandoc.hs` loses the pinned version-option and
feature-reporting source semantics:

- `-v` / `--version` detection before the `--` option separator;
- `versionOr` wrapping for command dispatch;
- `pandoc server` subcommand routing through the version handler;
- option parsing with the executable program name;
- option-info handling with the selected scripting engine;
- `versionInfo` output including the feature list, scripting-engine name, and
  version suffix.

This extends the accepted `pandoc-cli` executable, conditional branch, source
artifact, and enabled/disabled source semantics gates. It does not execute any
Pandoc runner or Cabal build.

## Evidence

Red-first focused run after adding the new test and before extending the source
gate:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2418 assertions, 1 failures`.

Final focused run after implementation:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2429 assertions, 0 failures`.

Delta against the previous upstream-runner audit baseline: `+1` focused PHP
PASS case and `+12` focused assertions.

Final dependency-audit family run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `3 test files, 2519 assertions, 0 failures`.

Additional checks:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f." ".json_last_error_msg().PHP_EOL); exit(1); } echo $f." OK".PHP_EOL; }'`
- `git diff --check -- lanes/pandoc`

All completed successfully.

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
build or runner execution is considered in a separate authorized slice.

## Non-Overlap

This does not repeat accepted static audits for package identity, setup hooks,
package flags/files/native fields, project pins/packages/flags/constraints,
runner/benchmark dependencies/options/modules/file artifacts, Lua-engine
library closure, server library closure, `pandoc-cli` executable stanza,
conditional branch labels/bodies, flag-specific conditional dependencies,
`pandoc-cli` source artifact byte/hash closure, or enabled/disabled CLI source
semantics. The owned behavior is only `pandoc-cli/src/pandoc.hs`
version-option and feature-reporting source semantics before Cabal planning.

## Follow-Up

If upstream-runner dependency work remains active, either hydrate the pinned
Pandoc checkout and run this native static audit against real Cabal/source
files before recording a non-mutating Cabal plan, or choose another
non-overlapping static source gate. Keep Pandoc/Cabal/Haskell runner execution
parked unless explicitly authorized.
