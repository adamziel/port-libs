# Pandoc BibTeX/CSL Manual Booklet Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T041210Z`

Base accepted HEAD: `62e9eaec3f082b012a61e602d9a7179fe5930ba6`

## Behavior

`BibtexCslParser` now maps classic BibTeX `@manual` and `@booklet` entries to
CSL-compatible types instead of exposing raw entry type names to CSL styles.

- `@manual` maps to CSL `book`.
- `@booklet` maps to CSL `pamphlet`.
- The parser still preserves `rawBibtex.type` provenance as `manual` or
  `booklet`.
- Existing field handoff for `organization`, `address`, `edition`,
  `howpublished`, and `note` remains intact for citation rendering and
  WordPress bibliography blocks.

The WordPress smoke verifies that a CSL style using `<if type="book">` and
`<else-if type="pamphlet">` routes manual/booklet source packets through the
expected branches without invoking external citeproc tooling.

## Source Truth

The bounded support-library contract is native BibTeX-to-CSL entry-type handoff
for common classic `.bib` entry types. This slice ports the format contract into
the PHP parser/rendering path only; it does not run Pandoc, BibTeX, Biber,
citeproc, Cabal/Haskell runners, office tools, converters, online services, or
live provider tests.

## Verification

Baseline focused run before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3622 assertions, 0 failures
```

Red-first probe before edits:

```text
php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\CitationCslProcessor; $items = CitationCslProcessor::bibtexItems("@manual{m,title={Manual}}\n@booklet{b,title={Booklet}}"); echo $items[0]["type"]."\n".$items[1]["type"]."\n";'
manual
booklet
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3642 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-manual-booklet-type-handoff.php --self-test
wordpress-bibtex-csl-manual-booklet-type-handoff self-test passed
```

Focused delta:

- `phpPass`: `2280 -> 2281`
- `benchmarkDenominator.mapped`: `2682 -> 2683`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 141`
- `CitationCslProcessorTest.php`: `3622 -> 3642` focused assertions

Root harness was not run for this isolated micro-slice.

## Non-Overlap

This does not repeat accepted BibTeX/CSL clusters for xdata provenance,
crossref title parts, direct creator roles, director/source-locator handoff,
label prefix/sortinit, media aliases, unpublished speech routing, thesis type,
source-section and supplement-number variables, or numeric CSL sorting. It only
adds bounded classic BibTeX `@manual` / `@booklet` type routing into CSL
conditionals.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`BibtexCslParser` type mapping, `CitationCslProcessor` item normalization and
CSL choose rendering, `MarkdownReader`, `WordPressBlockWriter`, the existing
focused PHP test runner, and the lane-local WordPress example smoke. Full
upstream Pandoc/citeproc runner parity remains a separate upstream-runner
dependency task requiring a hydrated Pandoc checkout and Haskell test
executables.

## Next Task

A useful follow-up is another non-overlapping BibTeX/BibLaTeX datamodel alias,
entry-type mapping, or CSL variable gap with focused PHP tests and a WordPress
review smoke when the path is user visible.
