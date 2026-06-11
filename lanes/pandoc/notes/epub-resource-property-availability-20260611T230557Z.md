# EPUB Resource Property Availability Slice

Bead: `plib-s4ngz`

Current base: `ba99e7070b`

Change:
- `EpubPackage::resourceProperties()` now carries parsed OPF manifest target provenance for EPUB3 resource-property review items.
- Resource property items now expose exact target, stripped package part, external and missing states, href query/fragment suffixes, item diagnostics, ZIP byte/compression/CRC provenance, and byte-exposure policy.
- Added a focused EPUB3 fixture covering a local suffix-bearing XHTML resource, an external scripted resource, and a missing local script.

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` (1 file, 1581 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 66956 assertions, 0 failures)

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, or live provider test was invoked.
