# DOCX OpenXML Relationship Source Directory Depths

Bead: plib-pc6gl

This slice stays within DOCX/OpenXML package ingestion recovery. It records `sourceDirectoryDepth` on relationship source provenance and adds `relationshipSourceDirectoryDepthCount`, `relationshipSourceDirectoryDepthCounts`, and `relationshipSourceDirectoryDepths` to the package summary.

The focused fixture covers invalid-source sidecars, the package root, root-level package parts, shallow package part directories, missing-source sidecars, nested header directories, and relationship-part source directories. Each directory-depth bucket preserves source and existence counts, relationship counts, source path-depth, directory, base-name, extension, content-type, role buckets, source directories, source parts, relationship parts, byte totals, and largest existing source provenance.

Verification stays native-PHP-only after rebasing onto `523c838711`: `php -l` for the reader and focused test file, focused `DocxOpenXmlReaderTest.php` expected at 1 file / 8328 assertions / 0 failures, and full `lanes/pandoc/tests` expected at 260 files / 180150 assertions / 0 failures. No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, or live provider tests are invoked.
