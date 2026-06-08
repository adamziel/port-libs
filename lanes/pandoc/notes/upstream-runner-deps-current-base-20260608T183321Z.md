# Pandoc Upstream Runner Dependency Audit

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T183321Z`
Base accepted HEAD: `ac3303553ece8d04b2ac6e7da7800926d228ca87`

## Scope

This is a static upstream-runner dependency audit slice. It does not run
Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests.

## Change

`UpstreamRunnerDependencyAudit` now parses the Cabal `manual` field for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

The pinned runner closure expects the field to stay absent for all three
targets before `readyForNonMutatingCabalPlan` can be true. If a hydrated
checkout adds `manual: True`, `manual: False`, or another manual value to any
of those components, the audit now records a mismatch and keeps the
non-mutating Cabal plan blocked until the change is reviewed.

## Evidence

Baseline focused test before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1735 assertions, 0 failures
```

Focused test after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1771 assertions, 0 failures
```

The new test case is `blocks runner and benchmark manual fields before cabal
planning`.

## Non-overlap

This is additive to previous upstream-runner dependency-audit slices for
test-suite type, other-extensions, autogen-modules, data-files,
extra-tmp-files, executable options, test/benchmark options, native/system
dependency fields, source directories, conditionals, common imports, runner
artifacts, and benchmark artifacts. It only owns the Cabal `manual` field
closure for runner and benchmark stanzas.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing
static Cabal stanza parser and `UpstreamRunnerDependencyAudit`.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, recorded Cabal package/project
metadata, and a reviewed non-mutating Cabal plan before any Haskell runner or
benchmark execution.

## Next

Continue with a non-overlapping static Cabal closure gap or hydrate the pinned
Pandoc checkout only for a reviewed non-mutating runner plan. Do not execute
Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests from this lane.
