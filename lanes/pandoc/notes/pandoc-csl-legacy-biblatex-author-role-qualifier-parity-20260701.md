# Pandoc CSL Legacy BibLaTeX Author Role Qualifier Parity

Slice: `plib-vjxso`
Date: 2026-07-01 UTC

## Behavior

The legacy `BibtexCslProcessor` handoff now preserves BibLaTeX
`nameaddon`, `authortype`, and `bookauthortype` metadata as CSL-style
`name-addon`, `author-type`, and `container-author-type` fields.

This closes a parity gap with the strict `BibtexCslParser` path: legacy
`cslItems()`, direct bibliography text, `CitationCslProcessor::fromItems()`
style rendering, and WordPress bibliography output now all keep those role
qualifiers visible without external citeproc, BibTeX, Biber, Pandoc, or
validators.

## Non-Overlap

This does not repeat the existing strict `CitationCslProcessor::fromBibtex()`
support for the same fields, name annotations, editor role variants,
container-author names, custom fields, relation summaries, date ranges, or
source-file attachment policy. It only adds the missing legacy
`BibtexCslProcessor` field handoff and fallback bibliography labels.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with `1 test files, 1025 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  passed with `3 test files, 7524 assertions, 0 failures`.
- JSON parse, whitespace, and conflict-marker checks passed for the touched lane
  files.
