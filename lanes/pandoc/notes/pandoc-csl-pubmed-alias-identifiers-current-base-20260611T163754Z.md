# CSL PubMed alias identifier handoff

Slice: `plib-gqa8a` / 2026-06-11 UTC.

Current base: `446b499ac`.

This slice maps additional PubMed/PMC identifier spellings into the existing CSL identifier handoff:

- BibTeX/BibLaTeX `pubmed`, `pubmedid`, and `pubmed-id` now normalize to `PMID`
- BibTeX/BibLaTeX `pmc`, `pmc-id`, and `pmc_id` now normalize to `PMCID`
- direct CSL JSON item aliases normalize into `pmid` / `pmcid`
- custom CSL style variables `pubmed`, `pubmedid`, `pubmed-id`, `pmc`, `pmc-id`, and `pmc_id` render from the normalized values

Focused verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: 1 test file, 4639 assertions, 0 failures.

Full verification:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: 44 test files, 63951 assertions, 0 failures.

No Pandoc, citeproc, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
