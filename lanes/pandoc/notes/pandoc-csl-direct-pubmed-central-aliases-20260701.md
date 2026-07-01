# Pandoc CSL Direct PubMed Central Alias Slice

Bead: `plib-rmf09`
Date: 2026-07-01 UTC

## Scope

Direct CSL-like item ingestion now normalizes additional PubMed and PubMed Central identifier aliases into canonical `pmid` and `pmcid` metadata:

- PMID aliases: `pubmedId`, `pubmed-identifier`, `pubmedIdentifier`, and `pubmedidentifier`.
- PMCID aliases: `PMC`, `pmcId`, `pubmed-central`, `pubmedCentral`, `pubmedcentral`, `pubmed-central-id`, `pubmedCentralId`, and `pubmedcentralid`.

The existing bibliography renderer and CSL text-variable renderer already expose canonical `PMID` and `PMCID` values. This slice closes the direct item alias boundary so medical review packets with camelCase or PubMed Central spellings render consistently without invoking external citeproc, BibTeX, Biber, or Pandoc.

## Focused Coverage

- `CitationCslProcessor::normalizeItem()` maps direct PubMed Central aliases to canonical `pmid` and `pmcid`.
- `CitationCslProcessorTest.php` adds `normalizes direct csl pubmed central identifier aliases`, covering direct item normalization, raw-key preservation, default bibliography output, custom CSL `<text variable="PMID|PMCID"/>` rendering, citation cluster output, and WordPress bibliography handoff.
- `UPSTREAM_TEST_MANIFEST.json` records `mappedDirectCslPubmedCentralAliasCases: 1` and `directCslPubmedCentralAliasAssertions: 15`.

## Non-Overlap

This does not repeat the accepted BibTeX/BibLaTeX PubMed field slice or the compact registry alias slice. It only owns direct CSL-like item aliases that were still absent on current `origin/main`.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed on `pubmedId` normalizing to an empty PMID before the implementation.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 6203 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - `3 test files, 7574 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/*Csl*Test.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - `11 test files, 7811 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Baseline-red outside this slice: `534 test files, 142310 assertions, 8912 failures`
