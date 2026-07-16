# Upstream Runner Dependencies Current Base 20260609T124419Z

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T124419Z`
Accepted base: `a38edfb50352ef212fcb62803d82a7ae9bd2908c`

## Scope

This is the explicit Pandoc upstream-runner dependency audit slice. It does not
run Pandoc, Cabal solver/build commands, Haskell test binaries, Haskell
benchmarks, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
engines, browser renderers, online services, live provider tests, or
live-service provider tests.

## Behavior

`UpstreamRunnerDependencies` now keeps the lightweight non-mutating Cabal gate
blocked until the current Pandoc Cabal project package closure is present:

- `pandoc-server/pandoc-server.cabal`
- `pandoc-cli/pandoc-cli.cabal`
- `benchmark/benchmark-pandoc.hs`

The solver target list now includes `benchmark:benchmark-pandoc` alongside
`test:test-pandoc` and `test:test-pandoc-lua-engine`. This aligns the small
gate with the broader `UpstreamRunnerDependencyAudit` closure, which already
tracks server, CLI, benchmark, dry-run command, and workspace descriptor
requirements before any reviewed Cabal dependency plan.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note was present before
editing.

Red-first focused check after changing the gate and before updating the stale
test expectation:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reports blocked pandoc runner gate when upstream cabal files are absent
String does not contain 'missing-required-upstream-files:5'
Haystack: pandoc-runner-not-executed,cabal-build-not-run,haskell-test-binaries-not-run,cabal-available:3.12.1.0,ghc-available:9.10.3,stack-not-on-path,missing-required-upstream-files:8
1 test files, 66 assertions, 1 failures
```

Final direct focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

Focused upstream-runner dependency family check:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2737 assertions, 0 failures
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencies.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencies.php

php -l lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK
```

Delta:

- `+1` focused PHP PASS case in `UpstreamRunnerDependenciesTest.php`.
- `phpPass` updated from `2778` to `2779`.
- No mapped upstream denominator increase is claimed.

Example smoke: not run, because this audit-only slice changes no
WordPress-visible conversion example.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing
PHP upstream-runner dependency audit helpers and lane-local fixtures.

Full upstream Pandoc runner parity remains blocked on a hydrated checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, a reviewed non-mutating Cabal
dependency plan for the two test suites plus the benchmark component, and a
separate explicitly authorized runner slice before any Haskell executable build
or execution.

## Non-Overlap

This does not repeat accepted conversion slices for Markdown/SVG, DOCX, EPUB,
ODT, archive compression, citations, BibTeX, math, tables, Unicode, XML/HTML,
PDF handoff, or legacy DOC/CFB. It also does not repeat the broad
`UpstreamRunnerDependencyAudit` package, project, benchmark, CLI, server,
freeze, dry-run command, or workspace descriptor closures. The owned behavior
is only the lightweight `UpstreamRunnerDependencies` gate alignment.
