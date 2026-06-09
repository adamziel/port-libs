# Upstream Runner Dependency Audit - Roff Manual Format Registry

- Micro-slice: `pandoc-upstream-runner-roff-manual-format-registry-audit`
- Hook bead: `plib-f78`
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`
- Upstream files checked: `src/Text/Pandoc/Readers.hs`, `src/Text/Pandoc/Writers.hs`, and `src/Text/Pandoc/Format.hs`

## Behavior

`UpstreamRunnerDependencyAudit` now treats Pandoc roff/manual format registry
source semantics as a pre-Cabal-plan gate. A hydrated checkout is blocked when:

- `Readers.hs` no longer exports/imports/registers the `man` reader.
- `Writers.hs` no longer exports/imports/registers the `man` and `ms` roff writers.
- `Format.hs` no longer infers `ms` from `.ms` and `.roff`, or `man` from numeric manual suffixes `.1` through `.9`.

This is a static source audit only. It does not implement a roff reader/writer
and does not execute Pandoc, Cabal solver/build/test commands, Haskell runners,
benchmark executables, roff renderers, external validators, online services,
live provider tests, or live-service provider tests.

## Evidence

Syntax:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

Focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2646 assertions, 0 failures
```

Upstream-runner family:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
3 test files, 2761 assertions, 0 failures
```

Full Pandoc lane PHP suite:

```text
php tools/run-tests.php lanes/pandoc/tests
38 test files, 56376 assertions, 0 failures
```

Delta:

- `+1` focused PHP PASS case.
- `+24` focused assertions in `UpstreamRunnerDependencyAuditTest.php`.
- `lane-status.json` `phpPass`: `2794 -> 2795`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3029 -> 3030`.

## Non-Overlap

This does not repeat prior upstream-runner audit slices for package identity,
setup hooks, package flags/files/native fields, project pins/packages/flags,
runner/benchmark dependency closure, benchmark entry-source dispatch, benchmark
fixtures, Cabal plan descriptors, dry-run workspaces, Lua/server/CLI closure, or
Markdown/HTML/WordPress conversion behavior.

The owned behavior is only the roff/manual format registry source contract for
Pandoc `man` reader registration, `man`/`ms` writer registration, and direct
file-format inference before Cabal planning.
