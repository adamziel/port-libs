# Direct CSL Annotation Abstract Fallback

Slice: `plib-n4okx`, 2026-06-11.

Implemented bounded `CitationCslProcessor` coverage for direct CSL JSON
annotation metadata aliases.

Direct CSL items now keep explicit `abstract` values as the primary abstract
metadata, while falling back to `annotation` and then `annote` when no explicit
abstract exists. The existing annotation review metadata remains available
through `annotation`/`annote` CSL variables.

The focused fixture covers:

- explicit `abstract` plus separate `annotation`/`annote` provenance;
- direct `annotation`-only abstract fallback;
- direct `annote`-only abstract fallback;
- rendered CSL `abstract`/`annote` variables in citations and bibliographies;
- WordPress bibliography handoff for all three direct CSL records.

This intentionally does not invoke Pandoc, citeproc, BibTeX, Biber, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 file, 4717 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 64957 assertions, 0 failures.

Accounting:

- `phpPass`: 3090 -> 3091.
- `mapped`: 3202 -> 3203.
- `mappedCitationCslDirectAnnotationAliasCases`: 1.
- `citationCslDirectAnnotationAliasAssertions`: 17.
