# DOCX settings protection package review metadata

## Direct-format parity

- `DocxOpenXmlReader` already parsed `w:documentProtection` and `w:writeProtection` into the DOCX settings map.
- This slice adds metadata-only package review accounting for those settings under `settings['protectionDetails']` and `packageProvenance['summary']`.
- The review details report protection kind, enforcement/recommendation state, edit mode, algorithm names, spin counts, cryptographic metadata presence, and SHA-256 digests/lengths for hash and salt values.
- Raw hash and salt strings are not copied into the new review details or summary fields. Existing compatibility fields in `settings['documentProtection']` and `settings['writeProtection']` are unchanged.
- No external validators, Office tools, Pandoc shellouts, browser engines, ZIP shell tools, or Node/Jupyter tooling were used.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
