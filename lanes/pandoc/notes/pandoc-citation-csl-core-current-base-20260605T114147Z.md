# Pandoc Citation/CSL Citation-Number Collapse Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T114147Z`

Base: `a20a696ad37cb38330c430dc42489a24868948cb`

## Source Truth

- CSL 1.0.2 `collapse="citation-number"` collapses increasing rendered
  citation-number ranges in numeric styles, e.g. `[1, 2, 3, 5]` to `[1-3, 5]`
  with U+2013 as the range delimiter; decreasing ranges such as `[3, 2, 1]`
  do not collapse. Source: https://docs.citationstyles.org/en/v1.0.2/specification.html#cite-collapsing
- This bounded PHP slice implements that contract only when the citation layout
  resolves to a pure numeric `citation-number` rendering element. Locators,
  prefixes, suffixes, nonnumeric numbers, missing items, suppressed-author
  modes, and decorated/non-pure numeric layouts remain explicit boundaries.
- No Pandoc, citeproc, Cabal build, Haskell runner, BibTeX, Biber,
  bibliography manager, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser
  renderer, online sanitizer, or online service was executed.

## Implementation

- `CitationCslProcessor` now routes `collapse="citation-number"` through a
  bounded numeric range renderer.
- The renderer validates the CSL citation rendering shape through existing
  `CslStyle` metadata and macro expansion, then collapses only adjacent
  increasing integer runs with U+2013.
- Non-pure numeric citation layouts fall back to existing per-citation rendering
  so custom labels such as `source 1, source 2` are preserved.
- `wordpress-citation-csl-numbering-handoff.php` now covers both the existing
  locator-boundary citation-number output and the new collapsed numeric range.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL collapses bounded csl citation-number ranges for numeric styles
Expected: '[1-5]' with U+2013
Actual: '[1, 2, 3, 4, 5]'
1 test files, 1063 assertions, 1 failures
```

Final:

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-numbering-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-numbering-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1071 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-numbering-handoff.php --self-test
wordpress-citation-csl-numbering-handoff self-test passed
```

```text
git diff --check -- lanes/pandoc
<no output; passed>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `873 -> 874`
- `benchmarkDenominator.mapped`: `1330 -> 1331`
- `mappedCitationCslCoreCases`: `10 -> 11`
- Focused coverage: `CitationCslProcessorTest.php` moved from 53 PASS cases /
  1060 assertions to 54 PASS cases / 1071 assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc
checkout with Cabal package/project files and creating a non-mutating Haskell
test-runner plan. The local citation-number collapse support is not blocked by
that runner gate.

## Non-Overlap And Follow-Up

This slice does not repeat accepted CSL date-part, date-form, text-case,
quote/strip-periods, macro, choose, locator/label, citation-number assignment,
position, name-part, name-substitute, year-suffix, author-date collapse,
bibliography display-part, et-al, BibTeX/BibLaTeX metadata, PDF engine, DOCX,
ODT, EPUB, archive, XML/HTML5 DOM, charset, YAML, doctemplate, table-geometry,
math, or legacy DOC/CFB work.

Follow-up CSL work should keep near-note/note-style citation behavior,
broader citeproc disambiguation breadth, locale delimiter options beyond this
bounded collapse path, and full citeproc parity as separate bounded slices.
