# ODF Package Directory Name Character Review

Slice: plib-ymtzs
Date: 2026-07-01

Compact `OpenDocumentPackage` and rich `OdfReader` ODF/ODT package
provenance now carry package directory-name character review metadata. The
new handoff groups package entries whose directory path contains uppercase
ASCII, whitespace, percent-encoded octets, or non-ASCII bytes while keeping
base-name-only flags separate from directory flags.

The package inventory, package identity, rich import report, and document
manifest provenance now expose:

- directory and entry counts for flagged package directories
- per-flag entry counts, directory lists, and entry-name lists
- per-directory base-name, extension, role, byte, media-type, policy, and
  largest-entry summaries
- per-package-entry booleans for directory uppercase, whitespace,
  percent-encoded octet, and non-ASCII state

This is metadata-only package review. Package bytes remain bounded to
existing digests, lengths, and package inventory summaries. No external
Pandoc, office suite, TeX/browser engine, Typst, Node, zip/unzip, validators,
Jupyter, or live services were invoked.

Post-rebase validation:

- `php -l lanes/pandoc/src/OdfPackageDirectoryNameCharacters.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageDirectoryNameCharactersTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageDirectoryNameCharactersTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageDirectoryNameCharactersTest.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageDirectoryNameCharactersTest.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- conflict-marker scan of changed lane files
