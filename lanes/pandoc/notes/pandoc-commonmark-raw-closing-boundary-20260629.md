# Pandoc CommonMark Raw Closing Boundary Slice

## Scope

- MarkdownReader now recognizes CommonMark block-level closing tags such as
  `</div>` and `</section   >` as paragraph-interrupting raw HTML block starts.
- Standalone custom closing tags remain blank-line raw HTML blocks, while custom
  closing tags inside active paragraphs stay RawInline HTML.
- The slice stays native PHP under `lanes/pandoc` and does not invoke Pandoc,
  browser, TeX, office, Node, ZIP/package, or external validation tools.

## Accounting

- Direct-format denominator is unchanged.
- Focused CommonMark raw HTML interrupt mapped cases increased from 13 to 17.
- New cases cover two block-level closing-tag boundaries plus standalone and
  paragraph-inline custom closing-tag boundaries.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php`
  - Result: 1 test file, 71 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkSearchRawBlockCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockFourthWaveTest.php`
  - Result: 5 test files, 607 assertions, 0 failures
- Neighboring `MarkdownReaderRawHtmlBlockSurgeTest.php` remains known baseline-red outside this slice with 15 structured HTML precedence failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 296 test files, 116,995 assertions, 9,781 failures
  - The failure count matches the existing broad non-slice baseline recorded in
    lane status; assertion count increased with this slice's focused coverage.
