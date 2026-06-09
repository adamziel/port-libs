# pandoc-docx-openxml-core-current-base-20260609T041827Z

Accepted base: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

Scope:
- Implemented bounded native DOCX/OpenXML `w:docDefaults` support from `word/styles.xml`.
- `DocxReader` now applies document default paragraph properties before paragraph styles and direct `w:pPr` overrides.
- `DocxReader` now applies document default run properties before paragraph style run properties, character styles, and direct `w:rPr` overrides.
- The handoff preserves default paragraph alignment, spacing, keep-next, fonts, language, italic, and highlight metadata through the existing AST, Markdown, and WordPress block metadata paths.
- Explicit style/direct removals such as `w:i w:val="0"` and `w:highlight w:val="none"` still suppress inherited defaults.

Focused evidence:
- Baseline focused test before production changes:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3873 assertions, 0 failures`.
- Final focused test after production changes:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3920 assertions, 0 failures`.
- Focused delta: `+47` assertions and `+1` PHP PASS line.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-style-defaults-handoff.php --self-test`
  passed with `wordpress-docx-style-defaults-handoff self-test passed`.

Dependency closure:
- No new native PHP support component is needed.
- This reuses `ZipPackage`/OPC package fixtures, DOM-based DOCX style loading in `DocxReader`, the existing run/paragraph metadata cascade, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane TestRunner.
- Full upstream Pandoc DOCX runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

Exclusions:
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, external validator, online service, live provider test, or live-service provider test was executed.
- This does not evaluate Word style defaults beyond bounded paragraph/run metadata needed by the native DOCX reader.

Non-overlap:
- This does not repeat accepted tracked-change formatting, comment/endnote metadata, bookmark and field-code hyperlinks, page geometry, OMML math, chart embedded-data provenance, custom XML/content-control metadata, picture/drawing metadata, table spans, numbering, media relationship provenance, or OPC target preflight work.
- The slice is limited to `styles.xml` document default paragraph/run property cascade behavior.

Next:
- A next DOCX/OpenXML slice could cover table style inheritance, numbering style interactions, latent style defaults, chart style/color metadata, or another bounded body/style mapping gap without repeating document defaults.
