# pandoc-epub-xhtml-ruby-annotations-20260614T133443Z

Slice: `plib-hyajy`, EPUB3 package ingestion.

This slice adds bounded native PHP EPUB XHTML ruby annotation provenance to
`EpubReader`. The XHTML content scan now reports:

- `contentRubies` / `contentRubyDiagnostics` on raw HTML spine handoff blocks;
- per-document `xhtmlResourceReport` ruby counters and aggregate package counts;
- ruby base text, direct `rt` annotations, `rtc` annotation-container provenance,
  fallback `rp` parentheses, language/direction/attribute metadata, and validity
  diagnostics for missing annotations or unbalanced fallback parentheses.

The change is intentionally static review metadata only. It does not perform
ruby rendering, reading-system layout, EPUBCheck validation, external fetching,
or full XHTML-to-AST conversion.

Verification after rebase onto current main `e139f8da9a`:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 file, 4436 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 83442 assertions, 0 failures

Direct parity accounting:

- `phpPass`: `3521 -> 3522`
- `phpFail`: `0`
- `mappedEpubXhtmlRubyAnnotationCases`: `1`
- `epubXhtmlRubyAnnotationAssertions`: `45`

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.
