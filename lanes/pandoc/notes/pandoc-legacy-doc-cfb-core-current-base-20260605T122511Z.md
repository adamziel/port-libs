# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T122511Z`

Base accepted HEAD: `0a82c3593f80f47fd9434708f26fb1149dd9db9f`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now keeps the full CLX/PlcPcd piece-table text internally, even when the rendered AST should only use the FibRgLw97 main-document CP range. The reader maps non-main FibRgLw97 supplemental ranges into metadata-only `subdocuments` records for footnote, header, comment, and endnote text.

Footnote, endnote, and comment PLC reference records now receive bounded `bodyText` and `bodyCharacterCount` values when their text PLC ranges point into those supplemental subdocuments. Inline WordPress reference spans only expose non-rendering `has-body` and body-count attributes. Supplemental body text stays out of rendered WordPress blocks.

## Source Truth

The Word binary FibRgLw97 counts describe contiguous main, footnote, header, annotation/comment, and endnote CP ranges after the piece table is decoded. The legacy DOC PLC reference tables point to anchors in the main story and text ranges in the corresponding supplemental story. This slice ports that contract into bounded native PHP metadata extraction; it does not attempt full Word layout, Word automation, or Pandoc runner parity.

## Verification Evidence

Baseline before adding the new focused case:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 475 assertions, 0 failures`.

Red-first after adding the fixture/test before implementation:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 475 assertions, 1 failures`; the new test failed because `subdocuments` metadata was absent.

Post-implementation focused test:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 500 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Final handoff checks:

`php -l lanes/pandoc/src/LegacyDocReader.php`

Result: `No syntax errors detected in lanes/pandoc/src/LegacyDocReader.php`.

`php -l lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php`.

`php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`

Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`.

`php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " ok" . PHP_EOL; }'`

Result: both JSON files decoded successfully.

`git diff --check -- lanes/pandoc`

Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB header parsing, MiniFAT/FAT sector traversal, directory and OLE property metadata, encrypted FIB rejection, fExtChar Unicode main-text extraction, CLX main-text extraction, no-paragraph-last flags, FibRgLw97 range metadata, bookmarks, note/comment anchor PLC records, section/style/formatting tables, fields, ObjectPool or macro inventory, embedded object placeholders, DOCX, ODT, EPUB3, ZIP/OPC, or table geometry.

The owned behavior is only supplemental DOC story body text extraction from piece-table ranges and metadata handoff for note/comment bodies.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CompoundFileBinary`, `LegacyDocReader`, `AstNode`, and `WordPressBlockWriter` support. No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online sanitizer, or online service was executed.

## Follow-Up

Keep textbox subdocuments, richer header/footer routing, rendered footnote/endnote/comment body AST policy, FastSave edge cases, full style/section application, embedded object byte export policy, and full upstream Pandoc runner parity as separate bounded slices.
