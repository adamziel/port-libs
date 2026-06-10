# Pandoc BibTeX/CSL Series Editor Alias Handoff

Slice: `pandoc-bibtex-csl-series-editor-aliases-current-base-20260610T164819Z`

## Behavior

`BibtexCslParser` now imports BibLaTeX `serieseditor` and `series-editor`
name fields as CSL `collection-editor` creator metadata. Matching
`serieseditor+an` and `series-editor+an` annotations stay attached to the
parsed name lists instead of being downgraded to generic BibLaTeX field
annotations.

The focused regression starts from BibTeX text and verifies raw
`CitationCslProcessor::bibtexItems()` output, normalized
`CitationCslProcessor::fromBibtex()` aliases, CSL `<names
variable="collection-editor">` rendering, annotation summaries, and appended
WordPress bibliography blocks.

## Evidence

- Red-first focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed as expected because `serieseditor` did not populate
  `collection-editor`.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 4366 assertions, 0 failures`.
- PHP lint passed for `lanes/pandoc/src/BibtexCslParser.php` and
  `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- `git diff --check -- lanes/pandoc` passed.
- Full lane run:
  `php tools/run-tests.php lanes/pandoc/tests` passed with
  `44 test files, 60771 assertions, 0 failures` after rebase.

## Manifest Delta

- `lane-status.json phpPass`: `2989 -> 2990`

## Non-Overlap

This does not repeat the direct CSL participant-name slice or the direct
BibLaTeX `collectioneditor` handoff. It only closes the BibLaTeX
`serieseditor` alias import path for the active parser while reusing existing
CSL collection-editor normalization and rendering.

## Dependency Closure

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, browser renderer, external bibliography manager, external validator,
online service, live provider test, or live-service provider test was executed.
