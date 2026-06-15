# Pandoc BibTeX/CSL Legacy Citation Aliases Slice

Slice: `pandoc-bibtex-csl-legacy-citation-aliases-20260615T111252Z`

## Behavior

`BibtexCslProcessor` now preserves bounded BibLaTeX `ids` aliases in legacy CSL
handoff items:

- `ids`, `citation-aliases`, `citationaliases`, `citation-alias`, and
  `citationalias` map to CSL-like `citation-aliases`.
- `citationHandoff()` resolves cited alias keys back to the canonical
  bibliography item while keeping `cslItems()` keyed only by canonical BibTeX
  entry IDs.
- Legacy bibliography review text exposes alias metadata as `Citation aliases:
  ...` so Markdown and WordPress review packets keep the provenance visible.

## Evidence

Red-first probe before implementation:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\Pandoc\BibtexCslProcessor(); $items=$p->cslItems("@book{canonical,ids={legacy},title={Alias Manual},year={2026}}"); var_export($items["canonical"]["citation-aliases"] ?? null); echo PHP_EOL;'
```

Result: `NULL`.

Final verification:

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Result: focused `BibtexCslProcessorTest.php` passed: `1 test files, 270
assertions, 0 failures`. Post-rebase full `lanes/pandoc/tests` passed: `46
test files, 88285 assertions, 0 failures`.

## Accounting

- `phpPass`: +1 focused legacy BibTeX/CSL alias case
- `mappedBibtexCslProcessorCases`: +1
- `mappedBibtexCslProcessorCitationAliasCases`: `1`
- `bibtexCslProcessorCitationAliasAssertions`: `12`
- Focused `BibtexCslProcessorTest.php`: `270` assertions
- `phpFail` remains `0` in the focused run

## Non-Overlap

This does not repeat the newer `BibtexCslParser` / `CitationCslProcessor`
alias normalization, direct CSL JSON alias rendering, shorthand-list handling,
related-entry metadata, or source-locator slices. It only fills the legacy
`BibtexCslProcessor` alias lookup and review-output gap, without invoking
Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.
