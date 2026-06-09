# Pandoc ZIP Package Core Current-Base Slice - 2026-06-09T032954Z

## Behavior

- Added `ZipPackage::unicodeExtraFieldPolicyPreflight()` for raw Info-ZIP Unicode path/comment extra-field review before package instantiation.
- The preflight scans bounded EOCD, central-directory, and local-header metadata without extracting payload bytes or invoking external ZIP tools.
- It reports structured Unicode path/comment metadata counts and issue entries for:
  - CRC32 mismatches.
  - missing or mismatched local Unicode path metadata.
  - duplicate/truncated/unsupported/invalid/empty Unicode path and comment replacements.
- `ZipPackage::rawStrictImportPreflight()` now carries the summary as `unicodeExtraFields` and emits `unicode-extra-field-issues` plus the concrete Unicode extra-field issue names before constructor failure.
- The WordPress ZIP package preflight example now exposes and self-tests the central-only Unicode path policy through both direct and raw strict preflight summaries.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2272 assertions, 0 failures`

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2307 assertions, 0 failures`
- Assertion delta: `+35`
- PASS-line delta: `+1`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP EOCD, central-directory, local-header, CRC32, Unicode metadata, raw strict import preflight, and in-memory ZIP fixture helpers. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted stored-first mimetype descriptor policy, central-directory local-header offset diagnostics, Unicode filename hygiene, entry-count mismatch direction, external-attribute policy, ZIP64 end/extra-field reporting, split archive detection, data descriptor integrity, unsupported compression/encryption policy, local-header metadata mismatch, central-directory signatures, archive extra-data record detection, central-directory recovery metadata, or duplicate local-header offset diagnostics. It only adds raw Unicode path/comment extra-field policy diagnostics for packages that may fail before instantiation.

## Next

Good follow-ups are ZIP64/data-descriptor edge diagnostics, DOCX/EPUB/ODT reader consumption of strict package preflight diagnostics, or remaining package media policy gaps as separate native PHP slices.
