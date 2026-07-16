# Pandoc Citation/CSL Core Current Base - Subsequent Et Al

Slice: `pandoc-citation-csl-core-current-base-20260605T150144Z`

Accepted base: `4b3f5d4114499e26b9bdc5ec4aade9cc1ee778a2`

## Behavior

- Added bounded native CSL `et-al-subsequent-min` and
  `et-al-subsequent-use-first` support for citation-scope `cs:names` /
  `cs:name` rendering options.
- `CslStyle` now parses and validates both attributes as positive integers and
  exposes them through the style summary and rendering-element metadata.
- `CitationCslProcessor` now passes citation-position context into fallback
  author-date labels and custom CSL `<names>` rendering. Repeated citations
  whose `cslPositionTests` include `subsequent` use the subsequent et-al
  thresholds, while first citations keep the ordinary `et-al-min` /
  `et-al-use-first` thresholds.
- Added `wordpress-citation-csl-subsequent-et-al-handoff.php` to exercise
  Markdown import, repeated citation rendering, CSL bibliography output, and
  WordPress block handoff without invoking external citation tooling.

## Source Truth

- CSL 1.0.2 name rendering supports `et-al-min`, `et-al-use-first`,
  `et-al-subsequent-min`, and `et-al-subsequent-use-first` as bounded name
  rendering options.
- This slice maps only the native PHP handoff contract for repeated in-text
  citation name abbreviation. It does not attempt et-al-use-last,
  cite-specific given-name disambiguation, note-style output beyond existing
  near-note metadata, locale-specific collapse punctuation, external CSL style
  catalogs, or full citeproc parity.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1204 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 1208 assertions, 1 failures` because
  `etAlSubsequentMin` metadata was absent.
- Green:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1219 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-subsequent-et-al-handoff.php --self-test`
  passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter` support paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, citeproc, BibTeX,
Biber, bibliography manager, Word, LibreOffice, zip/unzip, TeX/PDF engine,
browser renderer, online sanitizer, or online service was executed.

Full upstream Pandoc/citeproc runner parity remains gated on hydrating a local
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, short-form text variables,
date-part/date-form rendering, macros, choose conditionals, labels, numbers,
text-case, quotes, punctuation-in-quote, name-part formatting, initialization
hyphen handling, sort-separator handling, delimiter-precedes-et-al, explicit
`cs:et-al` rendering, citation-number/year collapse, near-note behavior,
subsequent-author bibliography substitution, table geometry, DOCX/ODT/EPUB,
PDF, YAML, doctemplate, ZIP/OPC, archive compression, charset/Unicode,
XML/HTML5 DOM, legacy DOC/CFB, or syntax-highlighting work.

Next bounded CSL follow-ups: `et-al-use-last`, richer name disambiguation,
locale-specific collapse punctuation, note-style output, and full citeproc
parity.
