# Pandoc Citation CSL Direct Title Subtitle Aliases

Bead: `plib-60iyj`
Base: `57511e8004`

## Scope

Implemented a bounded native PHP Citation/CSL slice for direct CSL JSON title-family subtitle aliases. `CitationCslProcessor` now composes direct item title/subtitle pairs for:

- `mainTitle` / `mainSubtitle` and compact `maintitle` / `mainsubtitle`
- `volumeTitle` / `volumeSubtitle` and compact `volumetitle` / `volumesubtitle`
- `issueTitle` / `issueSubtitle` and compact `issuetitle` / `issuesubtitle`

The composed values feed existing CSL `main-title`, `volume-title`, and `issue-title` rendering, bibliography entries, and WordPress review blocks. This keeps direct CSL JSON behavior aligned with the accepted BibTeX/BibLaTeX parser composition without invoking Pandoc or external bibliography tooling.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 4984 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68616 assertions, 0 failures`

## Accounting

- Adds 1 focused direct CSL JSON title-family subtitle PASS case.
- Adds 16 focused assertions.
- Moves `phpPass` from `3163` to `3164`.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
