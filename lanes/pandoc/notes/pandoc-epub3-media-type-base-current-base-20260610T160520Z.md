# Pandoc EPUB3 Manifest Media-Type Base Slice

Bead: plib-usly
Slice: 20260610T160520Z

## Implemented

- Routed EPUB package ingestion decisions through parsed MIME base media types instead of raw manifest strings.
- Kept OPF media-type parameters available in existing media type reports while allowing parameterized XHTML/CSS/NCX/SMIL core package resources to participate in content, CSS, navigation, media overlay, binding, and asset-role handoff paths.
- Added a regression fixture proving parameterized spine XHTML and stylesheet items remain imported and scanned.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 file, 3977 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 60564 assertions, 0 failures
