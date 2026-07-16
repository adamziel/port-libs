# CSL/BibLaTeX Status Taxonomy Aliases

## Scope

CSL/BibLaTeX citation and bibliography handoff now accepts bounded status and
taxonomy alias fields without invoking Pandoc, citeproc, BibTeX, Biber,
bibliography managers, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

## Native PHP coverage

- `publication-status`, `publicationStatus`, `publicationstatus`, and
  `pubstate` normalize into CSL status metadata.
- `keyword-list`, `keywordList`, and `keywordlist` normalize with
  `keyword`/`keywords` into keyword lists and `keywordSummary`.
- `category-list`, `categoryList`, and `categorylist` normalize with
  `category`/`categories` into category lists and `categorySummary`.
- `CitationCslProcessor::fromBibtex()` preserves the aliases through citation
  clusters, bibliography entries, and WordPress bibliography output.
- Legacy `BibtexCslProcessor` preserves the aliases in CSL item metadata,
  raw BibTeX provenance, citation handoff items, and bibliography node payloads.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 2 files, 5756 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 81346 assertions, 0 failures

Accounting: `phpPass` moves 3474 -> 3475, `phpFail` remains 0,
`mappedCslBiblatexStatusTaxonomyAliasCases` is 1, and
`cslBiblatexStatusTaxonomyAliasAssertions` is 40.
