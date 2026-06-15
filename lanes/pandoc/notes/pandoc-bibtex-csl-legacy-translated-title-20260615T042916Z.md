# Pandoc BibTeX/CSL Legacy Translated Title Slice

Slice: `pandoc-bibtex-csl-legacy-translated-title-20260615T042916Z`

## Behavior

`BibtexCslProcessor` now preserves bounded BibLaTeX translated-title aliases in
legacy CSL handoff items:

- `titletranslation`, `title-translation`, `translatedtitle`, and
  `translated-title` map to CSL `translated-title`.
- `subtitletranslation`, `subtitle-translation`, `translatedsubtitle`,
  `translated-subtitle`, `titletranslationsubtitle`, and
  `title-translation-subtitle` map to CSL `translated-subtitle`.
- Default bibliography text composes the translated title/subtitle for reviewer
  output while keeping CSL item metadata separated so `CitationCslProcessor`
  can apply its existing translated-title composition path.

## Evidence

Red-first probe before implementation:

```sh
php -r 'require "tools/bootstrap.php"; $items=(new PortLibs\Pandoc\BibtexCslProcessor())->cslItems("@book{source,title={Migration Manual},titletranslation={Manual de Migracion},year={2026}}"); var_export($items["source"]["translated-title"] ?? null); echo PHP_EOL;'
```

Result: `NULL`.

Final verification:

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Result: focused `BibtexCslProcessorTest.php` passed: `1 test files, 233
assertions, 0 failures`. Full `lanes/pandoc/tests` passed: `46 test files,
85929 assertions, 0 failures`.

## Accounting

- `phpPass`: `3647 -> 3648`
- `mappedBibtexCslProcessorCases`: `6 -> 7`
- `mappedBibtexCslProcessorTranslatedTitleCases`: `1`
- `bibtexCslProcessorTranslatedTitleAssertions`: `15`
- `phpFail` remains `0`

## Non-Overlap

This does not repeat the stricter `BibtexCslParser` / `CitationCslProcessor`
translated-title normalization, direct CSL JSON title aliases, original-title
aliases, original-publication metadata, main/volume/part title family slices,
or registry identifier slices. It only fills the legacy `BibtexCslProcessor`
BibLaTeX handoff gap and verifies downstream CSL/WordPress rendering without
invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.
