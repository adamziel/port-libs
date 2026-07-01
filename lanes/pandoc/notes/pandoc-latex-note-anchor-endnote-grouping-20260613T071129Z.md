# Pandoc LaTeX note anchor grouped-endnote round-trip follow-up

## Scope

- `LatexWriter` now assigns stable `fn-*` anchors to generated notes as well
  as labelled notes, and emits matching `fnref-*` reference anchors before the
  note command so backlink metadata is retained in LaTeX source.
- Duplicate source note labels continue to de-dupe deterministically and now
  leave a bounded `% pandoc-note-anchor duplicate ...` diagnostic in the LaTeX
  output.
- Source endnote nodes can render through grouped `endnote` / `theendnotes`
  output when `groupEndnotes` is enabled; default footnote output remains
  unchanged for callers that do not opt in.
- Additional focused coverage verifies duplicate labels across footnote and
  endnote placement, `label` / `noteLabel` / `identifier` anchor aliases,
  generated fallback anchors inside grouped endnotes, `sourceType` / `noteType`
  / `cslNoteType` endnote classification, stable `fnref-*` ordering before
  `fn-*` targets, and WordPress `data-pandoc-note-label` / backlink metadata
  preservation for safe nonnumeric labels.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterNoteAnchorEndnoteGroupingTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterNoteAnchorEndnoteGroupingTest.php`
  - 1 test file, 16 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterNoteAnchorEndnoteGroupingTest.php lanes/pandoc/tests/MarkdownWriterPreferredNoteLabelCompletionTest.php lanes/pandoc/tests/NativeWriterNoteLabelJsonModeTest.php`
  - 3 test files, 27 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 6187 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 37 assertions, 11 baseline failures outside the note slice;
    the labelled-note, grouped-endnote, and duplicate-anchor cases pass.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- Current-base PHP pass counters are incremented for the added focused
  `LatexWriterNoteAnchorEndnoteGroupingTest.php` coverage.
- `mappedLatexNoteAnchorEndnoteGroupingCases` is 4.
- `latexNoteAnchorEndnoteGroupingAssertions` is 39 across the existing three
  note-anchor cases plus the added focused alias/backlink test.
