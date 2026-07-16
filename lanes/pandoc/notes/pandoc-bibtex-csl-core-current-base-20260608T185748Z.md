# Pandoc BibTeX/CSL Author Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T185748Z`
Base accepted HEAD: `be1daac3955666cd7f4550d89b27b78d713e0ae0`
Date: 2026-06-08 UTC

## Behavior

This slice adds bounded native BibLaTeX `authortype` and `bookauthortype`
handoff support. `BibtexCslParser` now maps those fields into CSL
`author-type` and `container-author-type` metadata while preserving the raw
BibTeX fields. `CitationCslProcessor` normalizes the metadata, renders bounded
CSL text-variable aliases, and surfaces default review bibliography labels for
WordPress import review packets.

The implementation intentionally preserves role metadata only. It does not try
to implement BibLaTeX bibliography drivers, role inflection, citeproc parity, or
external bibliography-manager behavior.

## Source Truth

Source truth is the lane's existing BibTeX/CSL format contract and static Pandoc
inventory row for bounded citation/bibliography support. No hydrated Pandoc
checkout was available in the upstream cache for this worker, and no Pandoc,
citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids the accepted BibTeX/CSL clusters for entry options, related/xref
metadata, language options, refsection/refsegment provenance, keyword lists,
index title fields, event metadata, gender, field annotations, name
annotations, pagination, entry subtype, library call numbers, and
bookauthor/container-author handoff. The new behavior is limited to
`authortype` and `bookauthortype` role qualifiers.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2629 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2648 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-author-type-handoff.php --self-test`
  passed.
- Assertion delta: `+19` focused assertions.
- Status delta: `phpPass 1724 -> 1725`,
  `benchmarkDenominator.mapped 2145 -> 2146`,
  `mappedBibtexCslCoreCases 7 -> 8`,
  `bibtexCslCoreAssertions 121 -> 140`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses
`BibtexCslParser` field extraction, `CitationCslProcessor` item normalization
and style rendering, `MarkdownReader`, and `WordPressBlockWriter`.

Follow-up candidates should stay non-overlapping, for example BibLaTeX
`editora`/`editorb`/`editorc` role variants, entryset bibliography ordering, or
CSL bibliography role labels.
