# Pandoc Citation CSL PubMed Underscore Alias Slice

Slice: `plib-gqa8a`

## Behavior

`BibtexCslParser` and `CitationCslProcessor` now normalize underscore PubMed
and PMC identifier aliases into canonical CSL medical identifier metadata:

- BibTeX `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id` map to `PMID` and
  `PMCID` fields.
- Direct CSL items accept `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id`
  while preserving raw field provenance.
- CSL styles can render `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id` text
  variables from the normalized `pmid` and `pmcid` values.
- Fallback bibliography output and WordPress bibliography handoff keep the
  resulting `PMID` and `PMCID` labels visible.

## Accounting

- Central lane counters were left on current `origin/main` to avoid regressing
  newer status metadata; this note records the direct-format parity accounting
  for the repaired slice.
- `mappedCitationCslPubmedUnderscoreAliasCases`: `1`
- `citationCslPubmedUnderscoreAliasAssertions`: `15`

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php` passed.
- `php -l lanes/pandoc/src/CitationCslProcessor.php` passed.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed: 2 test files, 7227 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted and remains outside this slice's passing gate: 534 test files, 142309 assertions, 8912 failures. The new underscore PubMed/PMC CSL case passes in the full run; the first failures are in existing DocBook reader, HTML writer global attribute, LaTeX writer, and Markdown surge fixtures.

## Non-Overlap

This extends the accepted PubMed/PMC alias slice only for underscore and
`*_id` spellings. It does not add identifier lookups, fetch PubMed/PMC records,
read attachments, invoke Pandoc, citeproc, BibTeX, Biber, office suites,
TeX/browser engines, Typst, Node tooling, zip/unzip, external validators, or
live services.
