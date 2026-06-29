# Pandoc HTML Reader Microdata Value Policy

Slice: `pandoc-html-reader-microdata-value-policy-20260629`

## Behavior

- `HtmlReader` now records bounded microdata value-policy metadata for scalar
  properties, including byte length, empty-value flags, truncation flags, and
  per-item/global counters.
- URL-valued microdata properties are classified as relative, root-relative,
  protocol-relative, absolute HTTP(S), or absolute non-HTTP, with
  `metadata-only-no-fetch` policy metadata and no network or browser execution.
- Empty property values, nameless `itemprop`, and non-HTTP absolute URL values
  are surfaced as diagnostics while preserving existing item, itemref, nested
  item, and value-source summaries.

## Accounting

- `HtmlReaderTest.php` microdata mapped-case count moves from 7 to 8.
- `UPSTREAM_TEST_MANIFEST.json` adds
  `mappedHtmlReaderMicrodataValuePolicyCases: 1`.

## Verification

- `php -l lanes/pandoc/src/HtmlReader.php`
- `php -l lanes/pandoc/tests/HtmlReaderTest.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/HtmlReaderTest.php`
  - Result before final rebase gate: 1 test file, 100 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result before final rebase gate: 295 test files, 116997 assertions,
    9781 failures.
  - Visible failures are broad existing lane failures outside this slice,
    including `UnicodeTextTest.php` and `YamlMetadataReviewTest.php`.

No Pandoc, Haskell/Cabal runner, browser, external validator, network fetch,
office suite, TeX/PDF engine, Node tooling, or live service was invoked.
