# Upstream Runner Dependency Audit - Cabal Project Conditional Branches

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T061345Z`
Accepted base: `ad25c5c67f0859a34d555620436625e00d668451`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present before editing.

## Source Truth

Manifest pins `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
Hydrated upstream checkout was not used; this stays inside the native PHP audit fixture.
No Pandoc, Cabal, Haskell, benchmark, office, zip, TeX, browser, online, or live-provider commands were run.

## Behavior

`UpstreamRunnerDependencyAudit` now records top-level `cabal.project` conditional branch labels and blocks `readyForNonMutatingCabalPlan` if any unexpected branch is present. Conditional branch bodies remain excluded from unconditional package, constraint, and source-repository closure, so hidden conditional changes do not pollute those maps but still block plan readiness until reviewed.

New coverage exercises `if os(windows)`, `elif arch(wasm32)`, and `else after elif arch(wasm32)` branches with conditional packages, constraints, source repositories, and flags.

## Evidence

Baseline red command:

```bash
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Baseline result: `1 test files, 2195 assertions, 2 failures`.

Final focused command:

```bash
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Final focused result: `1 test files, 2581 assertions, 0 failures`.

Final family command:

```bash
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Final family result: `3 test files, 2678 assertions, 0 failures`.

Syntax and JSON checks:

```bash
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK" . PHP_EOL; }'
```

Results: no PHP syntax errors; both JSON files decoded successfully.

Diff check:

```bash
git diff --check -- lanes/pandoc
```

Result: passed with no output.

Delta:

- +1 focused PHP PASS case.
- +23 focused assertions.
- +1 mapped upstream-runner dependency audit case.
- Example smoke: not run, audit-only.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This reuses the existing PHP audit class, lane fixtures, manifest, and TestRunner. Full upstream parity remains gated on a hydrated pinned checkout and a reviewed non-mutating Cabal dependency plan in an explicitly authorized slice.

## Non-Overlap

This does not repeat previous static audits for required files, package identity, setup hooks, flags, file fields, native fields, project pins, packages, flags, constraints, stanza fields, unconditional fields, runner dependencies, benchmark dependencies, runner options, modules, artifacts, Lua/server/CLI closure, dry-run command envelopes, or workspace descriptors. This slice owns only `cabal.project` conditional branch closure before any Cabal invocation.

## Follow-Up

Hydrate and verify the pinned checkout, then run the static audit against real Cabal sources before any authorized non-mutating plan. Keep Pandoc, Cabal, Haskell runner, and benchmark execution parked unless explicitly authorized.
