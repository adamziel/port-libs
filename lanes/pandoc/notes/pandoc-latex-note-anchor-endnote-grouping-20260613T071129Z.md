# Pandoc LaTeX note anchor endnote grouping follow-up

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

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 27 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 75554 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 3354 to 3355.
- `phpFail` remains 0.
- The mapped denominator moves from 3314 to 3315.
- `mappedLatexNoteAnchorEndnoteGroupingCases` is 1.
- `latexNoteAnchorEndnoteGroupingAssertions` is 5.
