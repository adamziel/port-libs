# Pandoc BibTeX/CSL Series Creator Editor Translator Handoff

Slice: `pandoc-bibtex-csl-series-creator-editor-translator-20260629`
Bead: `plib-f1l4w`

## Behavior

`BibtexCslProcessor` now maps legacy BibLaTeX `seriescreator` /
`series-creator` and `editortranslator` / `editor-translator` name lists into
bounded CSL name variables. The handoff preserves:

- `series-creator` and `editor-translator` CSL item name lists;
- BibLaTeX name annotations on `seriescreator+an`;
- direct bibliography fallback name-annotation review text;
- `CitationCslProcessor` styled `<names>` rendering;
- WordPress citation and bibliography output without external citeproc.

This closes a legacy processor gap: `BibtexCslParser` and
`CitationCslProcessor` already knew these variables, but the older
`BibtexCslProcessor` path dropped the incoming BibLaTeX fields.

## Evidence

Syntax checks:

```text
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
1 test files, 826 assertions, 0 failures
```

## Accounting

- `lane-status.json` `phpPass`: `464 -> 465`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2306 -> 2307`
- Added `mappedLegacyBiblatexSeriesCreatorEditorTranslatorCases: 1`

## Non-Overlap

This slice does not repeat secondary editor role fields
(`editora`/`editorb`/`editorc`), legal authority metadata, literal
publisher/language/event lists, custom name fields, or direct CSL JSON alias
work. It only adds the missing legacy BibLaTeX name-list import path for
series creators and editor-translators.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
TeX/PDF engine, office suite, external validator, online service, or live
provider test was executed.
