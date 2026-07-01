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

## Evidence

```sh
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `2 test files, 7227 assertions, 0 failures`.

## Accounting

- `phpPass`: `490 -> 491`
- `benchmarkDenominator.mapped`: `2883 -> 2884`
- `mappedCitationCslPubmedUnderscoreAliasCases`: `1`
- `citationCslPubmedUnderscoreAliasAssertions`: `15`

## Non-Overlap

This extends the accepted PubMed/PMC alias slice only for underscore and
`*_id` spellings. It does not add identifier lookups, fetch PubMed/PMC records,
read attachments, invoke Pandoc, citeproc, BibTeX, Biber, office suites,
TeX/browser engines, Typst, Node tooling, zip/unzip, external validators, or
live services.
