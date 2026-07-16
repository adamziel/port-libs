# Upstream Runner Dependency Audit: Package Data Files

Slice: `pandoc-upstream-runner-deps-current-base-20260608T230043Z`
Base accepted HEAD: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`

## Source Truth

- Pinned upstream commit: `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- Targeted raw reads of the pinned Cabal files showed:
  - `pandoc.cabal` has a top-level `data-files` stanza beginning at line 51.
  - `pandoc-lua-engine/pandoc-lua-engine.cabal`, `pandoc-server/pandoc-server.cabal`, and `pandoc-cli/pandoc-cli.cabal` do not have top-level `data-files` stanzas.
- The audited `pandoc.cabal` package-level payload covers Pandoc templates, translations, entity data, reference DOCX/ODT/PPTX skeleton files, EPUB/dzslides assets, Lua init/reader files, bash completion, citeproc localization, `MANUAL.txt`, and `COPYRIGHT`.

## Change

- Added package-level Cabal `data-files` parsing to `UpstreamRunnerDependencyAudit`.
- Added an exact expected package data-file closure for the pinned Pandoc workspace.
- Added `packageDataFileClosure` to the audit result with `expectedDataFiles`, `presentDataFiles`, `missingDataFiles`, and `unexpectedDataFiles`.
- Blocked the non-mutating Cabal plan when:
  - pinned `pandoc.cabal` package `data-files` are missing or drift;
  - other workspace packages add unexpected package-level `data-files`.
- Kept component-level `data-files` ownership unchanged for runner, benchmark, and Lua library stanzas.

## Verification Evidence

- Rework note check: no current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed with `1 test files, 29 assertions, 75 failures` because the new fixture/test requested `UpstreamRunnerDependencyAudit::expectedPackageDataFiles()` before the source helper existed.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Passed with `1 test files, 2160 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Both reported no syntax errors.
- JSON validation:
  - `lanes/pandoc/lane-status.json OK`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP Cabal parser/list normalization already used by the upstream-runner dependency audit.

Full upstream runner parity remains gated on a hydrated pinned checkout plus a reviewed non-mutating Cabal plan. This slice intentionally did not run Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark executables, external converters, online services, live provider tests, or live-service provider tests.

## Next Non-Overlapping Slice

A follow-up upstream-runner audit could cover package-level `extra-doc-files` / `extra-source-files` closure or another distinct Cabal package/project field not already covered by setup, flags, component `data-files`, Lua library generated fields, and package-level `data-files`.
