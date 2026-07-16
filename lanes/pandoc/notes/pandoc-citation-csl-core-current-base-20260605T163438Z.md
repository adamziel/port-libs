# Pandoc Citation/CSL Current-Base Name Form Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T163438Z`
Base accepted HEAD: `f8c365b5b1fd87bac0411884c446464b5c9c15f7`

## Source Truth

- Bounded upstream contract: CSL 1.0.2 `cs:name` can carry `form`. The short
  form renders compact family/non-dropping-particle names, while count form is
  used for creator counts.
- Source: https://docs.citationstyles.org/en/v1.0.2/specification.html
- This slice ports the local format contract only. It does not invoke Pandoc,
  citeproc, BibTeX, Biber, Cabal, Haskell test binaries, external bibliography
  managers, online services, Word, LibreOffice, or converter tools.

## Implementation

- `CslStyle` now parses and validates bounded `cs:name form` values for
  `long`, `short`, and `count`.
- Style summaries and per-rendering-element metadata now carry the selected
  `cs:name` form.
- `CitationCslProcessor` now applies element-level name form options, renders
  `form="count"` as a creator count, and renders bibliography `form="short"`
  as family/non-dropping-particle labels without given names or suffixes.
- Added `wordpress-citation-csl-name-form-handoff.php` to exercise Markdown
  import, CSL citation rendering, bibliography output, and WordPress block
  handoff for compact creator labels.

## Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1289 assertions, 0 failures
```

Red-first after adding the focused expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1296 assertions, 1 failures
```

The first red failure proved `form="count"` metadata was parsed but not merged
into the element-level renderer.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1304 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-name-form-handoff.php --self-test
wordpress-citation-csl-name-form-handoff self-test passed
```

Final hygiene:

```text
php -l lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-name-form-handoff.php
git diff --check -- lanes/pandoc
```

## Status Delta

- `CitationCslProcessorTest.php`: `67 -> 68` focused PASS cases.
- Focused assertions: `1289 -> 1304` (+15).
- `lanes/pandoc/lane-status.json` `phpPass`: `999 -> 1000`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `1454 -> 1455`.
- `mappedCitationCslCoreCases`: `10 -> 11`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, `AstNode`, and
`WordPressBlockWriter`.

Full upstream Pandoc/citeproc runner parity remains gated on hydrating a local
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the
Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, CSL short
title/container-title text variables, date-part/date-form rendering, macros,
choose conditionals, labels, numbers, text-case, quotes, punctuation-in-quote,
name-part formatting, initialization hyphen handling, sort-separator handling,
demote-non-dropping-particle, delimiter-precedes-et-al, explicit `cs:et-al`,
et-al-use-last, subsequent et-al thresholds, citation-number/year collapse,
near-note behavior, subsequent-author bibliography substitution, BibTeX/
BibLaTeX metadata, table geometry, DOCX/ODT/EPUB, PDF, YAML, doctemplate,
ZIP/OPC, archive compression, charset/Unicode, XML/HTML5 DOM, legacy DOC/CFB,
or syntax-highlighting work.

Root harness: not run - isolated micro-slice.
