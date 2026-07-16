# ODF ZIP Source Record Directory Roots

Bead: `plib-qiasi`

Slice: `odf-zip-source-record-directory-roots`

## Scope

- Added ODF package ZIP source-record directory-root rollups to compact
  `OpenDocumentPackage` summaries and rich `OdfReader` package provenance.
- New `packageZipSourceRecordDirectoryRoot*` fields summarize source-record
  byte spans by root directory while preserving local header, compressed data,
  central directory, role, media-type, byte-exposure, and largest-entry
  metadata.
- The same rollup is carried through package identity payloads so reviewers can
  compare source ZIP layout changes without exposing blocked package bytes.

## Fixture

- Added `OdfZipSourceRecordDirectoryRootsTest.php`.
- The fixture covers root ODF entries, `META-INF/`, declared `Pictures/`
  media, mixed stored/deflated compression methods, central-directory comments,
  and an undeclared blocked `Notes/` entry.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php`
  - `1 test files, 88 assertions, 0 failures`
- Related ODF/OpenDocument package source-record gate:
  `php tools/run-tests.php lanes/pandoc/tests/OdfZipSourceRecordDirectoryRootsTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `8 test files, 8380 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
