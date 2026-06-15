# Pandoc Markdown Reader Raw HTML Block Surge

## Scope

- Added native `MarkdownReader` raw HTML block coverage for 50
  Markdown/CommonMark/GFM-style block boundary cases.
- Extended closing-tag raw block handling for `noscript` and `xmp`, so blank
  lines and Markdown-looking content stay inside the raw block until the
  matching closing tag.
- Covered comments, processing instructions, declarations, CDATA, raw-text
  elements, HTML5 sectioning and embedded elements, SVG, MathML, void tags, and
  generic custom raw blocks.

No Pandoc, cmark/commonmark runner, Cabal/Haskell runner, Node tooling, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderRawHtmlBlockSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockSurgeTest.php`
  - 1 file, 301 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 73 files, 104087 assertions, 0 failures

## Accounting

- `phpPass`: `6022 -> 6072`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `6012 -> 6062`
- `mappedMarkdownReaderRawHtmlBlockSurgeCases`: `50`
- `markdownReaderRawHtmlBlockSurgeAssertions`: `301`
