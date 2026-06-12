# Direct CSL Issue Subtitle Aliases

Bead: `plib-gokhq`
Base: `ea1d3358b3`

This slice keeps direct CSL JSON issue metadata aligned with the native citation
and bibliography handoff. `CitationCslProcessor` now composes direct CSL
`issue-subtitle`, `issueSubtitle`, and `issuesubtitle` aliases with
`issue-title`/`issueTitle`/`issuetitle` into canonical `issueTitle` metadata,
while preserving `issue-title-addon` aliases for CSL style rendering,
bibliography entries, and WordPress bibliography review blocks.

Verification on 2026-06-12 UTC:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4959 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67999 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
