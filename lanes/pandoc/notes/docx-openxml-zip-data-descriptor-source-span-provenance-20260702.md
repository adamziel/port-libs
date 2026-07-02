# DOCX OpenXML ZIP Data Descriptor Source Span Provenance

Date: 2026-07-02
Bead: plib-hbub7
Lane: pandoc package DOCX/OpenXML

## Summary

DOCX/OpenXML package ingestion now carries metadata-only ZIP data descriptor
source-span provenance from `ZipPackage::packageManifestPreflight()` into the
DOCX package provenance handoff.

The slice adds descriptor byte counts and SHA-256 hashes for streamed ZIP
entries with signed and unsigned data descriptors while preserving the existing
ZIP package manifest hash identity contract. Descriptor bytes remain
metadata-only: DOCX provenance exposes hashes, offsets, lengths, and review
policy flags, not package payload or descriptor bytes.

## Coverage

- `ZipPackage::packageManifestPreflight()` now includes data descriptor review
  rollups, signed/unsigned descriptor counts, zero local-header placeholder
  counts, central-directory value match counts, descriptor issue rows, and
  per-entry descriptor source-span hashes.
- `DocxOpenXmlReader` carries descriptor source-span metadata through
  `packageProvenance.zipPackage.byPackagePath`, `packageProvenance.parts`, and
  summary rollups.
- The DOCX fixture covers a signed deflated descriptor, an unsigned stored
  descriptor, and a non-descriptor package part.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 4,918 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 16,030 assertions, 0 failures

No upstream Pandoc, Office tooling, zip/unzip shell-outs, network fetches, or
external validators are used.
