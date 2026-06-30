# Pandoc BibTeX/CSL legacy editor-translator handoff

Slice: `plib-y06n5`

## Summary

`BibtexCslProcessor` now maps legacy BibLaTeX `editortranslator` and
`editor-translator` fields into the normalized CSL `editor-translator` name
variable. The handoff is preserved through `CitationCslProcessor::fromItems()`,
CSL style `cs:names variable="editor-translator"`, bibliography role summaries,
and WordPress-facing bibliography output without invoking Pandoc, citeproc,
BibTeX, Biber, bibliography managers, browser renderers, online services, or
external validators.

The processor also treats `editortranslator+an` and `editor-translator+an` as
name-role annotations for the legacy annotation filter, matching the surrounding
role-field behavior.

## Validation

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Result: `BibtexCslProcessorTest.php` passed with `1 test files, 645 assertions,
0 failures`.

## Accounting

- `phpPass`: `469 -> 470`
- `phpFail`: `0`
- Focused mapped case: legacy BibLaTeX `editortranslator` into CSL
  `editor-translator`

## Non-Overlap

This does not repeat the stricter `BibtexCslParser` /
`CitationCslProcessor::fromBibtex()` direct creator-role slice, which already
imports `editortranslator` through the newer bibliography reader path. This
only fills the legacy `BibtexCslProcessor` handoff gap used by direct citation
bibliography handoff tests.
