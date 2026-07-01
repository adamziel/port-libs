# Pandoc BibLaTeX Original Series CSL Metadata - 2026-07-01

## Slice

- Added a bounded CSL handoff for BibLaTeX original series metadata:
  `origseries`, `orig-series`, `originalseries`, `original-series`,
  `original-collection-title`, and `originalcollectiontitle` now map to
  `original-collection-title`.
- Added the matching original series number aliases:
  `origseriesnumber`, `orig-series-number`, `originalseriesnumber`,
  `original-series-number`, `original-collection-number`, and
  `originalcollectionnumber`.

## Parity

- Direct-format parity is preserved across the Pandoc lane's BibTeX parser,
  legacy BibTeX CSL processor, normalized CSL item model, CSL text/number
  rendering, fallback bibliography entries, and WordPress block bibliography
  output.
- This is scoped to CSL bibliography/citation metadata for BibLaTeX original
  publication series fields and does not add shell-outs or external validators.

## Verification

- Focused test coverage is in
  `lanes/pandoc/tests/CitationCslProcessorTest.php`.
