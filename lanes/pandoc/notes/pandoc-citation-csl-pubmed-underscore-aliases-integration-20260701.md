# Pandoc Citation CSL PubMed Underscore Alias Integration Slice

Slice: `plib-c35ou`

## Behavior

`BibtexCslParser` and `CitationCslProcessor` now normalize underscore PubMed
and PMC identifier aliases into canonical CSL medical identifier metadata on the
`integration/pandoc-semantics-csl` lane:

- BibTeX `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id` map to `PMID` and
  `PMCID` fields.
- Direct CSL items accept `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id`
  while preserving raw field provenance.
- CSL styles can render `pubmed_id`, `pmid_id`, `pmc_id`, and `pmcid_id` text
  variables from normalized `pmid` and `pmcid` values.
- Fallback bibliography output and WordPress bibliography handoff keep the
  resulting `PMID` and `PMCID` labels visible.

## Evidence

Baseline before implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `2 test files, 7386 assertions, 0 failures`.

Final focused verification:

```sh
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `2 test files, 7401 assertions, 0 failures`.

## Accounting

- `phpPass`: `473 -> 474`
- `benchmarkDenominator.mapped`: `2316 -> 2317`
- `mappedCitationCslPubmedUnderscoreAliasCases`: `1`
- `citationCslPubmedUnderscoreAliasAssertions`: `15`

## Non-Overlap

This extends the accepted PubMed/PMC compact alias slice only for underscore and
`*_id` spellings in the integration CSL lane. It does not add identifier
lookups, fetch PubMed/PMC records, read attachments, invoke Pandoc, citeproc,
BibTeX, Biber, office suites, TeX/browser engines, Typst, Node tooling,
zip/unzip, external validators, or live services.
