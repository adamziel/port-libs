# Pandoc Markdown CommonMark Raw Precedence - 2026-06-28

Bead: `plib-nzf`

Scope:
- Added explicit CommonMark-family raw HTML block precedence before structured
  HTML helpers for focused top-level cases where CommonMark/GFM should keep
  opaque source HTML as raw blocks.
- Covered `commonmark` `<pre>` without nested `<code>`, `gfm` `<figure>`,
  `commonmark_x` `<details>`, and `commonmark` `<noscript>` raw text blocks.
- Kept default Markdown structured `<pre>` import behavior unchanged for the
  existing HTML-reader path.

Accounting:
- `phpPass`: `461 -> 462`
- `UPSTREAM_TEST_MANIFEST.json`:
  `mappedMarkdownReaderCommonMarkRawPrecedenceCases`: `0 -> 5`.

Validation:
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php`
  - 1 file, 17 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockFourthWaveTest.php`
  - 5 files, 594 assertions, 0 failures

Residual:
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` remains
  baseline-red in this checkout with 35 unrelated writer/template/profile
  failures; the raw HTML reader and HTML-reader cases in that file passed.
