# ZIP Package Raw Name Provenance

Slice: `pandoc-shared-zip-package-core-current-base-20260609T025145Z`
Base: `cb8eb4f51cf712622b553d57173410c449f7e04d`

## Behavior

`ZipPackage::rawNamePreflight()` now reports raw/decoded entry-name provenance
for package entries whose exposed name differs from the raw central-directory
name or depends on legacy name metadata:

- `provenanceEntryCount`
- `legacyEncodedNameEntryCount`
- `unicodePathExtraEntryCount`
- `decodedNameDiffersFromRawNameEntryCount`
- per-entry `nameEncoding`, `rawNameMatchesDecodedName`,
  `usesLegacyNameEncoding`, `usesUnicodePathExtraField`, and
  `hasRawNameProvenance`
- per-entry issues `raw-name-decoded-value-differs`,
  `raw-name-legacy-encoding`, and `raw-name-info-zip-unicode-path`

This keeps the accepted raw-name collision preflight while adding reviewable
provenance for CP437-decoded names and Info-ZIP Unicode path extras before
Office/EPUB/ODT media bytes are treated as importer attachments.

`ZipPackage::assertNoRawNameProvenanceReviewEntries()` provides an explicit
review gate for import paths that want to reject any package whose entry names
require legacy decoding or Unicode-path substitution.

The WordPress ZIP package preflight smoke now emits and self-tests the Unicode
path raw-name provenance policy without invoking `zip`, `unzip`, `ZipArchive`,
Pandoc, Word, LibreOffice, or online services.

## Verification

Red/focused compatibility adjustment after adding the richer provenance output:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2167 assertions, 1 failures`
- Failure: existing raw-name collision test expected only
  `raw-name-collision`; the expanded issue list also reports
  `raw-name-decoded-value-differs` and `raw-name-info-zip-unicode-path`.

Final focused checks:

- `php -l lanes/pandoc/src/ZipPackage.php`
- Result: `No syntax errors detected in lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- Result: `No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2180 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`

Root harness: not run; isolated micro-slice only.

## Dependency Closure

No new support component is required. This reuses the existing native PHP ZIP
central/local directory parser, Info-ZIP Unicode path extra-field handling, and
in-memory package fixture builder.

The slice does not require shelling out to Pandoc, Cabal/Haskell runners, Word,
LibreOffice, `zip`, `unzip`, `ZipArchive`, external converters, online
services, live provider tests, or live-service provider tests.

## Non-Overlap

This slice does not repeat accepted ZIP package work for central-directory
inventory count direction, DOS/NTFS/extended timestamp metadata, ZIP64
extra-field metadata, unsupported compression, encryption preflight, symlink
and special-file rejection, local header span and offset checks, name hygiene,
raw-name collision detection, Unicode comments, or ZIP/OPC relationship
parsing. It only adds raw/decoded name provenance accounting and an optional
review gate around already decoded entry names.

## Next

Suggested next ZIP/package gap: ZIP64 central-directory size/count
reconciliation, bounded central-directory recovery metadata, or a DOCX/EPUB/ODT
reader handoff that consumes the strict ZIP preflight diagnostics.
