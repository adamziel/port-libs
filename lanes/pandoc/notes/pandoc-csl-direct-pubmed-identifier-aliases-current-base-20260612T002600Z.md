# Pandoc CSL Direct PubMed Identifier Alias Slice

Bead: `plib-rmf09`
Base: current main `8881a8911c`
Date: 2026-06-12 UTC

## Scope

CSL citation/bibliography handoff now normalizes direct CSL JSON compact PubMed identifier aliases into canonical PMID/PMCID metadata:

- PMID aliases: `pubmed`, `pubmed-id`, `pubmedId`, `pubmedid`, and identifier variants.
- PMCID aliases: `PMC`, `pmc`, `pmc-id`, `pmcId`, `pubmed-central`, `pubmedCentral`, `pubmedcentral`, and `pubmed-central-id` variants.

The existing CSL text-variable renderer, default bibliography, and WordPress bibliography handoff already knew how to expose canonical `PMID` and `PMCID`; this slice closes the direct JSON alias boundary.

## Focused Coverage

- `CitationCslProcessor::normalizeItem()` maps compact direct CSL PubMed keys to normalized `pmid` and `pmcid`.
- `CitationCslProcessorTest.php` adds `normalizes bounded direct csl json pubmed identifier aliases`, covering direct item normalization, default bibliography output, custom CSL `<text variable="PMID|PMCID"/>` rendering, citation cluster output, and WordPress blocks.

## Non-Overlap

This does not repeat the accepted BibTeX/BibLaTeX PubMed field slice or canonical direct `PMID`/`PMCID` item-field support. It only owns compact direct CSL JSON alias keys at the citation/bibliography boundary.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 4960 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67875 assertions, 0 failures`

Lane status moved `phpPass` `3153 -> 3154`; `phpFail` remains `0`.
