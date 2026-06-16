# Pandoc Markdown Reader Attribute Profile Residual

## Scope

- Added native `MarkdownReader` coverage for 126 Markdown/CommonMark/GFM
  reader attribute/profile residual cases across headings, bracketed spans,
  fenced divs, and raw fenced block attributes.
- Markdown profile defaults now keep `header_attributes` and `fenced_divs`
  enabled, with `fenced_div`, `header_attribute`, and `header_attrs` normalized
  as aliases.
- ATX/setext heading attributes and fenced-div block starts are gated by their
  profile extensions, so literal CommonMark/GFM leave those markers as source
  text or code unless explicitly enabled.
- Bracketed spans and raw fenced block attributes remain extension-gated; raw
  fenced blocks are parsed only when `raw_attribute` is enabled.

No Pandoc, cmark/commonmark runner, Cabal/Haskell runner, Node tooling, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderAttributeProfileResidualSurgeTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- exact conflict-marker scan
- `git diff --check origin/main --`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderAttributeProfileResidualSurgeTest.php`
  - 1 file, 505 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php lanes/pandoc/tests/MarkdownReaderContainerAttributeSecondWaveTest.php lanes/pandoc/tests/MarkdownReaderAttributeTokenSurgeTest.php lanes/pandoc/tests/MarkdownReaderMetadataRawExtensionSurgeTest.php lanes/pandoc/tests/MarkdownReaderListLazyBlockStartSurgeTest.php`
  - 5 files, 8299 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReader*.php`
  - 69 files, 63376 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 176 files, 163166 assertions, 0 failures

## Accounting

- `phpPass`: `14796 -> 14923`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `14541 -> 14667`
- `UPSTREAM_TEST_MANIFEST.json` root `mapped`: `14548 -> 14674`
- `mappedMarkdownReaderAttributeProfileResidualCases`: `126`
- `markdownReaderAttributeProfileResidualAssertions`: `505`
