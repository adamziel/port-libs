# Pandoc CommonMark Paragraph Raw Boundary

## Scope

- Tightened `MarkdownReader` top-level `<p>` handling so a paragraph start tag
  without an explicit `</p>` or an implicit following HTML block close no longer
  consumes through blank-line boundaries as structured HTML.
- The existing CommonMark raw HTML reader now owns unclosed `<p ...>` block
  starts and preserves them as raw HTML until the next blank line.
- Closed HTML paragraph imports still map to structured paragraph nodes.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6778 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 80243 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, browser
renderer, office suite, external validator, online service, live provider test,
or live-service provider test was executed.

## Accounting

- `phpPass` moves from 3444 to 3445 after adding the focused CommonMark
  paragraph raw-boundary case.
- `phpFail` remains 0.
- `mappedMarkdownCommonMarkParagraphRawBoundaryCases` is 1.
- `markdownCommonMarkParagraphRawBoundaryAssertions` is 12.
