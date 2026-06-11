# EPUB Manifest Media Type Base Summary

Bead: `plib-fey39`
Slice: Pandoc EPUB3 package ingestion core blocker, 2026-06-11T225523Z.

This slice adds a compact OPF manifest media-type handoff summary to `EpubPackage::summary()`.
The summary preserves sorted manifest media-type base counts, EPUB core media-kind counts,
parameterized base counts, and per-item rows with manifest id, href, package part, external
state, existence, byte-exposure state, raw media type, base media type, core kind, parameter
map, and resource properties.

The WordPress import review payload now mirrors the same summary so importer queues can bucket
XHTML, CSS, image, audio, and script package resources without reparsing raw OPF manifest items
or shelling out to Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` -> 1 file, 1575 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 66873 assertions, 0 failures
