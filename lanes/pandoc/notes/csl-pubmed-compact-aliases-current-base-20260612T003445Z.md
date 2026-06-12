# CSL PubMed Compact Identifier Aliases

Bead: plib-nnx9p
Base: current main 8881a8911c
Date: 2026-06-12 UTC

## Scope

- Normalized BibTeX `pubmed`, `pubmedid`, `pubmed-id`, `pmc`, `pmcid-id`, and `pmc-id` aliases into canonical `PMID` and `PMCID` CSL metadata.
- Normalized direct CSL JSON `pubmedId` and `pmcId` aliases into canonical `pmid` and `pmcid` fields.
- Exposed compact `pubmed`, `pubmed-id`, `pmc-id`, and `pmcid-id` CSL text variables through citation, bibliography, and WordPress review handoff.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 4971 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67886 assertions, 0 failures.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
