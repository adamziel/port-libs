# Pandoc CommonMark Raw Container Boundary

## Scope

- Added focused native `MarkdownReader` fixture coverage for CommonMark raw
  HTML boundaries inside blockquote and bullet-list containers.
- The slice covers comment, processing-instruction, declaration, CDATA,
  `script`, and blank-line-bounded `section` raw blocks.
- Raw HTML source stays opaque inside the container while following Markdown
  resumes as native paragraph content, and both MarkdownWriter and WordPress
  handoff preserve the raw boundary source.

## Verification

- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
  - 1 test file, 109 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockSurgeTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlContainerSurgeTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlReferenceBoundarySurgeTest.php`
  - 5 test files, 1000 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 218 test files, 171537 assertions, 0 failures

No Pandoc executable, cmark/commonmark runner, Cabal solver/build/test command,
Haskell runner, office suite, TeX/PDF engine, browser renderer, zip/unzip,
Jupyter, Node tooling, external validator, online service, live provider test,
or live-service provider test was executed.

## Accounting

- `phpPass` moves from 16668 to 16681.
- `phpFail` remains 0.
- `mappedMarkdownReaderCommonMarkRawContainerBoundaryCases` is 12.
- `markdownReaderCommonMarkRawContainerBoundaryAssertions` is 109.
- `UPSTREAM_TEST_MANIFEST.json` mapped cases move from 16223 to 16235.
- Benchmark denominator mapped cases move from 3361 to 3373.
