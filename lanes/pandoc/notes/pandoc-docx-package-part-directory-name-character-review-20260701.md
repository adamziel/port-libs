# DOCX Package Part Directory Name Character Review

Slice: plib-sgz6w
Date: 2026-07-01

DocxOpenXmlReader now carries package part directory-name character review metadata for DOCX/OpenXML package ingestion. The new provenance groups loaded package parts whose directory path contains uppercase, whitespace, percent-encoded octets, or non-ASCII bytes while keeping base-name-only flags separate from directory flags.

The package provenance summary now exposes:

- directory and part counts for flagged package part directories
- per-flag part counts, directory lists, and package part names
- per-directory base-name, extension, content-type source/base, role, byte, and largest-part summaries
- per-part inventory booleans for directory uppercase, whitespace, percent-encoded octet, and non-ASCII state

This is metadata-only review. Package bytes remain bounded to existing digests, lengths, and package inventory summaries. No external Pandoc, office suite, TeX/browser engine, Typst, Node, zip/unzip, validators, citeproc, BibTeX, Biber, or live services were invoked.

Post-rebase validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryNameCharactersTest.php`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryNameCharactersTest.php` failed with missing `partDirectoryNameCharacterReviewDirectories`
- Focused `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryNameCharactersTest.php` passed with 1 file, 42 assertions, 0 failures
- Related `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetDirectoryNameCharactersTest.php` passed with 1 file, 54 assertions, 0 failures
- Related `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 12,486 assertions, 0 failures
