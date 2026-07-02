# ZIP package manifest name-byte-length review

Slice: `plib-xmosr` shared ZIP/OPC package core blocker.

`ZipPackage::packageManifestPreflight()` now emits metadata-only
`zip-package-manifest-name-byte-length-review` fields. The review packet
records decoded ZIP entry name byte buckets, a long-name threshold, longest
entry names, long-name entry rows, and local plus central-directory raw-name
byte totals using existing source-span provenance.

This does not read package payload bytes beyond existing manifest hashing,
change ZIP parsing, invoke external ZIP tools, or change the stable manifest
identity hash contract.

Validation for `plib-xmosr` passed on 2026-07-02:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageManifestNameByteLengthReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageManifestNameByteLengthReviewTest.php`
  - Result: 1 file, 20 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 1 file, 6,290 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: 1 file, 4,916 assertions, 0 failures.

Manifest accounting:

- `mappedSharedZipPackageManifestNameByteLengthReviewCases`: `1`
- `sharedZipPackageManifestNameByteLengthReviewAssertions`: `20`
- Benchmark mapped denominator: `2319 -> 2320`

No external Pandoc, office-suite, TeX/browser-engine, Typst, Jupyter, Node,
ZIP/unzip, validator, or live-service tooling was used.
