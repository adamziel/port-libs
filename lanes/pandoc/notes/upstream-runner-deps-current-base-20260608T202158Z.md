# Upstream Runner Dependency Audit - Module Interface Fields

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T202158Z`
- Accepted base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`
- Rework notes: none found for `port-pandoc` before editing.

## Behavior

The upstream-runner dependency audit now parses Cabal `signatures` and `virtual-modules` fields for the pinned runner and benchmark components. These fields are merged through imported `common` stanzas, reported in the component closure, and treated as static dependency-closure blockers when they appear unexpectedly.

This keeps a future non-mutating Cabal plan from being marked ready if `test:test-pandoc`, `test:test-pandoc-lua-engine`, or `benchmark:benchmark-pandoc` starts depending on Backpack signatures or virtual module interfaces outside the native audit contract.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed with `1 test files, 1887 assertions, 0 failures`.
- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php` passed.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php` passed.
- `git diff --check -- lanes/pandoc` passed.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack command, benchmark executable, external converter, online service, live provider test, or live-service provider test was executed.

## Dependency Closure

No new support component is needed. This slice reuses the native `UpstreamRunnerDependencyAudit` Cabal field parser, common-stanza import merging, and focused PHP test harness.

## Non-Overlap

This is distinct from prior upstream-runner audit slices for test-suite types, build tools, default/other extensions, cpp-options, autogen-modules, reexported-modules, native/system fields, test-options, benchmark-options, data-files, and extra file globs.

## Follow-Up

Next upstream-runner audit work should remain static and non-overlapping, such as remaining Cabal component field classes, package/project pin drift, or hydrated-checkout provenance. A real Cabal plan should only run when explicitly authorized.
