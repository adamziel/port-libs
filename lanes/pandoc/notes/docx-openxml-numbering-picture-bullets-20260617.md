# DOCX OpenXML numbering picture bullets

Issue: plib-1ucyo

Scope: native DOCX/OpenXML package ingestion only. The slice maps `word/numbering.xml` picture bullet metadata from `w:numPicBullet` and the numbering relationship sidecar without exposing image bytes or shelling out to office suites, Pandoc, zip/unzip, external validators, or online services.

Implemented:

- Reads `word/_rels/numbering.xml.rels` alongside the selected numbering part.
- Summarizes picture bullet ids, referenced and unreferenced image relationships, target suffix/query/fragment details, content type provenance, byte length/CRC32/SHA-256 metadata for local image parts, external targets, and issue codes.
- Carries `pictureBulletId` and metadata-only `pictureBullet` details onto recovered list nodes that use `w:lvlPicBulletId`.
- Tags internal numbering image targets in package inventory as `numbering-picture-bullet`.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 file, 4237 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 258 files, 176005 assertions, 0 failures.
