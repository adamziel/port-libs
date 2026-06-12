# CSL PubMed Compact Aliases

Slice: `plib-9vmm8` / `20260612T005655Z`

## Scope

Adds bounded native PHP citation/bibliography handling for compact PubMed
identifier aliases in CSL and BibLaTeX handoff paths.

## Coverage

- BibLaTeX `pubmedid`, `pubmed`, `pmc`, and `pubmedcentral` fields normalize to
  CSL `PMID`/`PMCID` metadata.
- Direct CSL item aliases `pubmed-id`, `pubmed`, `pmc`, and `pubmedcentral`
  normalize to canonical processor metadata.
- CSL style variables `pubmed`, `pubmed-id`, `pmc`, and `pubmedcentral` render
  through citation and bibliography layouts.

No Pandoc, citeproc, BibTeX, Biber, browser renderers, external validators,
online services, live provider tests, or live-service provider tests are invoked.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 file, 4992 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 files, 68849
  assertions, 0 failures.
