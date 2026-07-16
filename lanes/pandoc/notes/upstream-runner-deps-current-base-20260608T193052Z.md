# Pandoc Upstream Runner Dependency Audit

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T193052Z`

Accepted base: `13ef792b9726ca74a5372ce5b45a701d4366670c`

## Slice

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. It adds one bounded static Cabal closure case for
the `pandoc-lua-engine` library stanza before any non-mutating Cabal plan can be
marked ready.

The prior audit already blocked native/system dependency fields on the Pandoc
runner and benchmark stanzas. This slice covers the non-overlapping library
surface: `UpstreamRunnerDependencyAudit` now parses Cabal native/system fields
from `library` stanzas and records:

- `expectedNativeSystemFields`
- `presentNativeSystemFields`
- `unexpectedNativeSystemFields`

Unexpected library-level `c-sources`, `extra-libraries`, `pkgconfig-depends`,
`hsc2hs-options`, `ld-options`, and the existing native/system field family now
block `readyForNonMutatingCabalPlan`.

## Evidence

Red-first test with only the new fixture:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1812 assertions, 1 failures
FAIL blocks unexpected lua engine library native system fields before cabal planning
Expected: false
Actual: true
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1826 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php

php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
```

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
lane-local Cabal stanza parser and `UpstreamRunnerDependencyAudit` static audit
path.

Full upstream Pandoc runner execution, Cabal solver/build/test planning,
Haskell test binaries, benchmark binaries, external converters, online
services, live provider tests, and live-service provider tests remain
intentionally out of scope for this slice.

No Pandoc binary, Cabal command, Haskell runner, Stack command, benchmark
executable, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, online service, live provider test, or live-service provider test was
executed.

## Next

For an upstream-runner follow-up, choose another non-overlapping static Cabal
closure edge or hydrate the pinned upstream checkout only for a reviewed
non-mutating plan audit. Keep actual Pandoc, Cabal solver/build/test commands,
Haskell runners, benchmark binaries, external converters, online services, and
live-service provider tests out of this lane unless explicitly authorized.
