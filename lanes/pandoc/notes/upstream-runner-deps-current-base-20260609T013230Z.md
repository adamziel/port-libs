# Upstream Runner Dependency Audit - pandoc-cli Executable

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T013230Z`

Accepted base: `800b696344a9bf658321def4bebfd04d22ba2df2`

## Scope

This slice adds a static native PHP audit for the `pandoc-cli/pandoc-cli.cabal`
`executable pandoc` stanza. It keeps the upstream-runner path blocked until the
CLI executable closure matches the bounded contract needed before a reviewed
non-mutating Cabal plan:

- executable stanza name `pandoc`
- `main-is: pandoc.hs`
- `buildable: True`
- common import closure `common-executable` -> `common-options`
- direct dependencies `base`, `pandoc`, and `text`, with exact constraints for
  `base` and `pandoc`
- expected inherited executable `ghc-options`
- `Haskell2010` default language
- `hs-source-dirs: src`
- `OverloadedStrings` other-extension closure
- `PandocCLI.Lua` and `PandocCLI.Server` other-modules closure
- known bounded conditional branch labels for `os(windows)`, `arch(wasm32)`,
  `nightly`, `server`, `lua`, and `repl`

The patch intentionally does not run Pandoc, Cabal solver/build/test commands,
Stack, Haskell runners, benchmark executables, Word, LibreOffice, zip/unzip,
external converters, online services, live provider tests, or live-service
provider tests.

## Evidence

Baseline focused run before this slice:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2273 assertions, 0 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2346 assertions, 0 failures`

Delta: `+1` focused PHP PASS case and `+73` focused assertions.

Syntax and lane checks run after implementation:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f ".json_last_error_msg().PHP_EOL); exit(1); } echo "$f OK\n"; }'`
- `git diff --check -- lanes/pandoc`

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted static audits for package identity,
`cabal.project` source pins, runner test-suite type/buildable/default-language,
runner/benchmark direct dependencies, executable options, common imports,
Lua-engine library closure, server library closure, package-level files, or
runner/benchmark field drift. The owned behavior is only `pandoc-cli`
executable closure and drift blocking.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`UpstreamRunnerDependencyAudit` static Cabal parser, in-memory fixture trees,
and focused lane test harness. A hydrated checkout and a separately reviewed
non-mutating Cabal plan remain the activation gate before any Haskell runner
or benchmark execution.

## Follow-Up

If the upstream-runner audit remains active, the next non-overlapping static
slice can cover `pandoc-cli` conditional branch bodies or flag-specific
dependency variants. Otherwise, keep this parked until a hydrated checkout and
non-mutating Cabal plan artifact are explicitly authorized.
