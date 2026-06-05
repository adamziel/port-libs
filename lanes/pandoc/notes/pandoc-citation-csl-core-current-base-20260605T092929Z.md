# Pandoc Citation/CSL Citation-Number Handoff

Slice: `pandoc-citation-csl-core-current-base-20260605T092929Z`

Base: `c8211330df24d426ecd63e3e94fda9cb6afe5586`

## Source Truth

- CSL 1.0.2 defines `citation-number` as a standard variable and permits it in
  numeric citation and bibliography layouts through rendering elements such as
  `cs:number` and `cs:text`.
- This bounded native slice maps citation numbers from the local CSL
  bibliography order. When a document bibliography is sorted by CSL keys, the
  rendered citation numbers match that sorted bibliography order.
- No Pandoc, citeproc, Cabal build, Haskell runner, BibTeX, Biber, Word,
  LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online sanitizer,
  or online service was executed.

## Implementation

- `CitationCslProcessor` now annotates known citations with
  `cslCitationNumber` before normalization.
- `citation-number` now renders through CSL `text` and `number` variables,
  including direct bibliography entry rendering and WordPress display-part
  output.
- Bibliography definition-list entries now pass citation-number context into
  custom CSL bibliography layouts.
- `choose variable="citation-number"` now sees the variable as present when a
  bounded number can be resolved.
- Added `wordpress-citation-csl-numbering-handoff.php` to exercise the
  WordPress import/review path.

## Verification

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
1 test files, 937 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-numbering-handoff.php --self-test
wordpress-citation-csl-numbering-handoff self-test passed
```

```text
git diff --check -- lanes/pandoc
<no output; passed>
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `807 -> 808`
- `benchmarkDenominator.mapped`: `1267 -> 1268`
- `mappedCitationCslCoreCases`: `10 -> 11`
- Focused assertion delta: `925 -> 937` in `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc
checkout with Cabal package/project files and creating a non-mutating Haskell
test-runner plan. The local CSL support is not blocked by that runner gate.

## Non-Overlap And Follow-Up

This slice does not repeat accepted CSL date-part, text-case,
quote/strip-periods, macro, choose, locator/label, item-number, citation
position, name-part, name-substitute, year-suffix, collapse, bibliography
display-part, et-al, BibTeX/BibLaTeX metadata, PDF engine, DOCX, ODT, EPUB,
archive, XML/HTML5 DOM, charset, YAML, doctemplate, table-geometry, math, or
legacy DOC/CFB work.

Follow-up CSL work should keep citation-number range collapse, full
disambiguation parity, near-note/subsequent-author substitution,
punctuation-in-quote locale behavior, richer name-part typography, note-style
output, and full citeproc parity as separate bounded slices.
