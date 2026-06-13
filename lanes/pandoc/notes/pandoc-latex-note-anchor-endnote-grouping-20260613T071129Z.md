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
- Additional round-trip coverage verifies duplicate labels across footnote and
  endnote placement, generated fallback anchors inside grouped endnotes,
  `sourceType` / `noteType` / `cslNoteType` endnote classification, and ordering
  of `fnref-*` reference anchors before their `fn-*` targets.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- In-memory `TestRunner` harness selecting the three note-anchor cases from
  `lanes/pandoc/tests/LatexWriterTest.php`
  - 3 selected note tests, 23 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 6187 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 37 assertions, 11 baseline failures outside the note slice
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 4228 assertions, 71 baseline failures across older writer
    expectations; labelled-note anchor differences were observed as part of
    this intentional preservation change
- `php tools/run-tests.php lanes/pandoc/tests`
  - 534 test files, 142283 assertions, 8915 baseline failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- Current-base lane status counters are not incremented because the rebased
  `main` branch already contains the note-anchor coverage; this slice restores
  the implementation beneath those tests.
- `mappedLatexNoteAnchorEndnoteGroupingCases` is 3.
- `latexNoteAnchorEndnoteGroupingAssertions` is 23.
