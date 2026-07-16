# Pandoc CSL compact registry identifier alias slice

Slice: `plib-uxc7o`

## Summary

CSL citation/bibliography import now maps compact PubMed and registry identifier aliases into canonical metadata for both BibTeX/BibLaTeX entries and direct CSL-like item input.

Covered aliases include `pubmed`, `pubmedid`, `pubmed-id`, `pmc`, `pmc-id`, `pmcid-id`, `jstorid`, `jstor-id`, `hdlid`, `hdl-id`, `handleid`, `handle-id`, `lccnnumber`, `lccn-number`, `oclcnumber`, and `oclc-number`.

The canonical normalized fields continue to render through existing PMID, PMCID, JSTOR, HDL, LCCN, and OCLC bibliography output, while raw alias provenance remains available on normalized items.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 5016 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 69106 assertions, 0 failures
