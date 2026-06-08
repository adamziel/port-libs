## DOCX/OpenXML Numbering Level Override - 2026-06-08

Slice: `pandoc-docx-openxml-core-current-base-20260608T140438Z`

Base accepted HEAD: `b9d66374b3887e74516b9fc42fe11d6e845c86e5`

Implemented one bounded WordprocessingML numbering cluster:

- `DocxReader::loadNumbering()` now honors full `w:lvlOverride/w:lvl`
  replacement levels on concrete `w:num` instances before applying optional
  `w:startOverride`.
- Replacement levels can change inherited abstract numbering format, delimiter,
  start, and `w:numFmt w:val="none"` suppression before AST grouping.
- The DOCX body WordPress smoke now exercises an overridden upper-Roman list
  that would previously render as the inherited decimal abstract level.

Verification:

- Baseline `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2651 assertions, 0 failures`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  after adding the focused case
  - `1 test files, 2656 assertions, 1 failures`
  - Failure: the override list stayed `decimal` instead of `upper_roman`.
- Final `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2669 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`
- `php -l lanes/pandoc/src/DocxReader.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - no syntax errors
- `php -r` JSON validation for `lane-status.json` and
  `UPSTREAM_TEST_MANIFEST.json`
  - both valid
- `git diff --check -- lanes/pandoc`
  - passed

Status delta:

- Added one mapped native DOCX/OpenXML support case.
- Added one named PHP PASS case.
- Added +18 focused `DocxReaderTest.php` assertions.
- Lane `phpPass` moves from `1660` to `1661`.
- Manifest mapped count moves from `2080` to `2081`.

Non-overlap:

- This does not repeat accepted DOCX body/media/property/style metadata,
  tracked changes, comments, notes, bookmarks, field-code hyperlinks,
  paragraph border/frame/layout metadata, structured document tag form controls,
  embedded object/package placeholders, altChunk import, deleted OMML revision,
  or table geometry slices.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external office tool, online service, live provider
  test, or live-service provider test was executed.

Dependency closure:

- No new support component is needed.
- Reused native PHP `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`,
  `ZipPackage`, and OPC relationship/content-type support.
- Full upstream Pandoc runner parity remains separate; this isolated worktree
  does not contain a local pinned Pandoc checkout.
