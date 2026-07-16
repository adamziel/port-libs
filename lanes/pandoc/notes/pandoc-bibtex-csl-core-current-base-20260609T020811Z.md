# pandoc-bibtex-csl-core-current-base-20260609T020811Z

Lane: pandoc
Base accepted HEAD: ae05f994f04ccc78db62e7bd6dd42669f76246b1
Scope: BibTeX/CSL core direct extended creator field handoff

## Behavior

Implemented one bounded BibTeX/CSL support-library cluster: direct BibLaTeX
`redactor`, `founder`, `continuator`, `reviser`, and `collaborator` fields now
map into CSL name variables, and their `+an` metadata is treated as name
annotations instead of generic field annotations.

This is intentionally distinct from the already accepted editor-type role
aliases such as `editortype={redactor}` and `editoratype={continuator}`. This
slice covers direct source fields only.

## Evidence

Baseline focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result before this patch: `1 test files, 3304 assertions, 0 failures`.

Red-first command after adding the focused test and before the parser mapping:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: direct `redactor` metadata was missing; `1 test files, 3306 assertions,
1 failures`.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Result: `1 test files, 3333 assertions, 0 failures`, with `196` PASS lines.

Expected focused delta: +1 PASS case, +29 assertions.

## WordPress Smoke

Added `lanes/pandoc/examples/wordpress-bibtex-csl-direct-extended-creator-fields.php`
for a local WordPress block handoff smoke covering the same direct creator
fields, CSL names rendering, name-annotation summary, and bibliography output.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
BibTeX parser, CSL normalizer/renderer, Markdown reader, and WordPress block
writer. It does not invoke Pandoc, BibTeX, Biber, citeproc, Cabal/Haskell
runners, Word, LibreOffice, zip/unzip, external converters, online services,
live provider tests, or live-service provider tests.

## Non-Overlap

Avoided recent accepted archive compression, ODF/OpenDocument subtotal-rule,
direct CSL participant creator fields, secondary editor role, redactor variable,
and extended editor-type role slices. The only parser behavior changed here is
direct BibLaTeX extended creator field mapping and direct-field name annotation
classification.
