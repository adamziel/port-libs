# ZIP Selected Handoff Extension Buckets

Hook: `plib-97xuk`, Pandoc shared ZIP/OPC package core blocker slice.

## Scope

`ZipPackage::entryHandoffPreflight()` now summarizes selected file package-part
extensions before reader handoff.

The preflight reports extension buckets for both:

- selected unique package entries, including present entries blocked later by
  per-entry size or reader support checks;
- ready handoff entries whose payload bytes are readable under the requested
  limits.

Directories and missing requests are excluded from extension buckets, matching
the full ZIP package manifest's file-only extension summaries. Each bucket
carries file counts, compressed/uncompressed byte totals, roles, and entry
names so DOCX/OpenXML, EPUB3, ODF/ODT, and generic OPC importers can compare
requested package-part extension shape before exposing selected payload bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

## Limits

This does not add ZIP64 support, encrypted payload support, extraction,
external validators, office-suite validation, `zip`/`unzip` calls, or broader
archive tooling. It only extends native PHP selected-entry metadata before
payload handoff.
