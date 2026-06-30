# shared-zip-selected-handoff-expansion-ratio-buckets-20260630

Hook: `plib-uxyy8`, Pandoc shared ZIP/OPC package core blocker slice.

`ZipPackage::entryHandoffPreflight()` now reports selected and readable
expansion-ratio bucket summaries before DOCX/EPUB/ODT package-reader byte
handoff. The summaries group zero-byte, up-to-1x, 1x-to-10x, 10x-to-100x,
over-100x, and unknown expansion entries with role, entry-name, compressed
byte, uncompressed byte, unknown-ratio, and largest-ratio provenance.

Blocked oversized selections and unreadable/unsupported-compression entries
remain visible in selected buckets while staying out of readable handoff
buckets, so package readers can review expansion-risk metadata without
exposing blocked payload bytes.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 5824 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `295 test files, 117957 assertions, 9781 failures`
  - The new ZipPackage expansion-ratio bucket case passed in the broader run;
    visible failures were outside this ZIP/OPC slice, including
    `SyntaxHighlighterTest.php`, `TableGeometryReaderHandoffTest.php`, and
    `YamlMetadataReviewTest.php`.

No Pandoc, office suite, TeX/browser/Typst engine, `zip`/`unzip`, ZipArchive,
external validator, Node tooling, Jupyter, online service, or live provider
test was invoked.
