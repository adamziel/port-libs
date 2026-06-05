# Pandoc Citation/CSL Near-Note Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T124825Z`

Base: `c1cf1f37714011b48942dddb280e21fdc933c11e`

## Source Truth

- CSL 1.0.2 defines the citation `position` test values, including
  `near-note`, and the citation option `near-note-distance` for note-style
  references. Source: https://docs.citationstyles.org/en/v1.0.2/specification.html
- This bounded PHP slice implements note-distance detection for repeated
  citations in parsed footnotes/endnotes and explicit handoff metadata. It
  keeps footnotes and endnotes in separate note streams, uses CSL's default
  near-note distance of 5 when omitted, and validates explicit distances as
  non-negative integers.
- No Pandoc, citeproc, Cabal build, Haskell runner, BibTeX, Biber,
  bibliography manager, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser
  renderer, online sanitizer, or online service was executed.

## Implementation

- `CslStyle` now parses and summarizes `near-note-distance` on the CSL
  `citation` element.
- `CitationCslProcessor` now tracks note indexes while walking the AST and
  adds `near-note` to `cslPositionTests` when a repeated same-item citation is
  within the configured note distance.
- Explicit citation metadata such as `cslNoteIndex`, `noteIndex`, and
  `noteNumber` can carry note context when a caller has already supplied a
  citation AST instead of Markdown footnotes.
- The WordPress smoke example renders nearby repeated footnote citations
  through the CSL `near-note` branch while distant repeats stay on the
  `subsequent` branch.

## Verification

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
No syntax errors detected in lanes/pandoc/src/CitationCslProcessor.php

php -l lanes/pandoc/src/CslStyle.php
No syntax errors detected in lanes/pandoc/src/CslStyle.php

php -l lanes/pandoc/tests/CitationCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/CitationCslProcessorTest.php

php -l lanes/pandoc/examples/wordpress-citation-csl-near-note-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-citation-csl-near-note-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1115 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-near-note-handoff.php --self-test
wordpress-citation-csl-near-note-handoff self-test passed
```

```text
git diff --check -- lanes/pandoc
<no output; passed>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `903 -> 904`
- `benchmarkDenominator.mapped`: `1361 -> 1362`
- `mappedCitationCslCoreCases`: `10 -> 11`
- Focused coverage: `CitationCslProcessorTest.php` moved from 55 PASS cases /
  1071 assertions to 56 PASS cases / 1115 assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc
checkout with Cabal package/project files and creating a non-mutating Haskell
test-runner plan. The local near-note support is not blocked by that runner
gate.

## Non-Overlap And Follow-Up

This slice does not repeat accepted CSL date-part, date-form, text-case,
quote/strip-periods, macro, choose, locator/label, citation-number assignment
or collapse, repeated-citation position, name-part, name-substitute,
year-suffix, author-date collapse, bibliography display-part, et-al,
BibTeX/BibLaTeX metadata, PDF engine, DOCX, ODT, EPUB, archive,
XML/HTML5 DOM, charset, YAML, doctemplate, table-geometry, math, or legacy
DOC/CFB work.

Follow-up CSL work should keep broader citeproc disambiguation, note-style
punctuation/localization breadth, locale option coverage beyond this bounded
near-note-distance path, bibliography manager parity, and full upstream
citeproc/Pandoc runner parity as separate bounded slices.
