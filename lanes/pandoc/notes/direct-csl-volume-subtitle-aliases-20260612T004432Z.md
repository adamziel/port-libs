# Direct CSL Volume Subtitle Aliases

Bead: `plib-gokhq`
Base: `99cd6d2022`

This slice keeps direct CSL JSON volume metadata aligned with the native citation
and bibliography handoff. `CitationCslProcessor` now composes direct CSL
`volume-subtitle` and `volumesubtitle` aliases with
`volume-title`/`volumetitle` into canonical `volumeTitle` metadata, while also
accepting `short-volume-title` and `shortvolumetitle` aliases for the existing
short volume-title rendering path.

Verification on 2026-06-12 UTC:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4959 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68181 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
