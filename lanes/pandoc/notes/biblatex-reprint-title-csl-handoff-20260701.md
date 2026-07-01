# BibLaTeX reprint title CSL handoff

Date: 2026-07-01
Hook: plib-mvdk4

## Slice

`BibtexCslProcessor` now carries legacy BibLaTeX `reprinttitle` and hyphenated
`reprint-title` fields into the CSL `reprint-title` metadata variable. The
fallback bibliography renderer includes the field as `Reprint title`, matching
the existing `CitationCslProcessor` normalization and CSL style renderer support
for `reprintTitle`.

## Coverage

- `lanes/pandoc/tests/BibtexCslProcessorReprintTitleTest.php`
- 1 mapped legacy BibLaTeX reprint-title case
- 19 focused assertions

## Boundaries

This slice stays inside `lanes/pandoc` and does not invoke Pandoc, citeproc,
BibTeX, Biber, office suites, TeX/browser engines, Node, validators, or live
services. Direct-format parity remains active; this is a bounded CSL/BibLaTeX
handoff increment, not a broad upstream runner parity claim.
