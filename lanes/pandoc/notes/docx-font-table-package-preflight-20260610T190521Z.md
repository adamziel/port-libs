# DOCX Font Table Package Preflight - 2026-06-10

Bead: `plib-1y12k`

Scope:
- Added native `DocxReader` metadata/import-report handoff for `word/fontTable.xml`.
- Reports declared font names, alternate names, charset/family/pitch, Panose and signature metadata.
- Preflights embedded font relationships for present, missing, and unsafe external package targets.
- Reports byte counts and hashed obfuscation-key presence without exposing raw font bytes or raw font keys.

Verification:
- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed 1 file / 4802 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 files / 61806 assertions / 0 failures.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was executed.
