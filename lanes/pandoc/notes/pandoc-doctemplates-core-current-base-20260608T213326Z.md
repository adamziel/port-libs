# Pandoc Doctemplates Core Current Base - LaTeX Partials

Slice: `pandoc-doctemplates-core-current-base-20260608T213326Z`
Base accepted HEAD: `17b111d85a0bb4b5cb849a471da21f0b1ab9bf09`

## Source Truth

- Pinned Pandoc template inventory: `jgm/pandoc` commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, `data/templates/`.
- Static upstream files inspected: `document-metadata.latex`, `passoptions.latex`, `fonts.latex`, `font-settings.latex`, `common.latex`, `after-header-includes.latex`, and `hypersetup.latex`.
- No Pandoc, external template engine, TeX/PDF engine, Cabal build/test command, Haskell runner, browser renderer, online service, live provider test, or live-service provider test was executed.

## Behavior Added

- `DocTemplate` now exposes bundled fallback resources for the seven pinned Pandoc LaTeX partials used by `default.latex`.
- Custom LaTeX template resources can include `${ document-metadata.latex() }`, `${ passoptions.latex() }`, `${ fonts.latex() }`, `${ font-settings.latex() }`, `${ common.latex() }`, `${ after-header-includes.latex() }`, and `${ hypersetup.latex() }` without caller-provided partial files.
- Caller/user-data resources still override bundled fallback partials; the focused test asserts `templates/fonts.latex` wins over the bundled fallback.
- The WordPress doctemplate review-packet example now has a local LaTeX partial fallback self-test for `fonts.latex` and `hypersetup.latex`.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - `1 test files, 991 assertions, 0 failures`
- Red-first after adding the focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - `1 test files, 991 assertions, 1 failures`
  - Failure: `Missing doctemplate partial document-metadata.latex at templates/review.latex:1:1`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - `1 test files, 1055 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - `OK wordpress doctemplate review packet`
- Syntax and metadata checks:
  - `php -l lanes/pandoc/src/DocTemplate.php` - no syntax errors
  - `php -l lanes/pandoc/tests/DocTemplateTest.php` - no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` - no syntax errors
  - JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` - OK
  - `git diff --check -- lanes/pandoc` - OK
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `1871 -> 1872`
- `benchmarkDenominator.mapped`: `2297 -> 2298`
- `mappedDoctemplatePartialCases`: `4 -> 5`
- `doctemplatePartialAssertions`: `5 -> 69`

## Dependency Closure

No new support component is needed. This slice reuses native `DocTemplate` resource lookup, partial discovery, basename fallback registration, and bundled default-template fallback plumbing. Full upstream Pandoc/Haskell doctemplate runner parity remains outside this isolated micro-slice.

## Non-Overlap

This does not repeat the recent doctemplate default-template slices for Markdown/CommonMark, man, ms, Beamer, HTML4, EPUB2, ANSI/BibTeX, nor the parser behavior slices for braced separators, breakable-space wrapping, and applied partial rebinding. This slice only maps pinned LaTeX partial fallback resources.
