# Pandoc Upstream Runner Dependency Audit

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T195409Z`

Accepted base: `5ab7f3dd2c18dec97fb5d2517ffc7501ba04e5b8`

## Slice

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. It adds one bounded static Cabal closure case for
the `pandoc-lua-engine` library stanza before any non-mutating Cabal plan can
be marked ready.

Earlier audit slices already block unexpected runner and benchmark
`data-files`, `extra-source-files`, `extra-doc-files`, and `extra-tmp-files`.
This slice covers the non-overlapping library surface. The
`UpstreamRunnerDependencyAudit` library parser now records:

- `expectedExtraSourceFiles`, `presentExtraSourceFiles`, and `unexpectedExtraSourceFiles`
- `expectedExtraDocFiles`, `presentExtraDocFiles`, and `unexpectedExtraDocFiles`
- `expectedExtraTmpFiles`, `presentExtraTmpFiles`, and `unexpectedExtraTmpFiles`
- `expectedDataFiles`, `presentDataFiles`, and `unexpectedDataFiles`

Unexpected library-level artifact globs now block
`readyForNonMutatingCabalPlan` and are named in `blockedReasons` and the
activation gate. This prevents generated Lua-engine support sources, docs,
temporary outputs, or data fixtures from silently widening the Haskell runner
closure before a reviewed plan exists.

## Evidence

Red-first focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1827 assertions, 1 failures
FAIL blocks lua engine library file artifact globs before cabal planning
Expected: false
Actual: true
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
1 test files, 1847 assertions, 0 failures
```

Focused assertion delta: `+21` assertions.

Focused PHP PASS delta: `+1` test case.

Mapped denominator delta: `+1` static upstream-runner dependency audit case,
from `2196` to `2197`.

## Dependency Closure

No new native PHP support component is needed. This reuses the existing
lane-local Cabal stanza parser and `UpstreamRunnerDependencyAudit` static audit
path.

Full upstream Pandoc runner execution, Cabal solver/build/test planning,
Haskell test binaries, benchmark binaries, Stack, external converters, online
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
