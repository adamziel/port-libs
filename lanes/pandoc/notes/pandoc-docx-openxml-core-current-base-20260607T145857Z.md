# DOCX/OpenXML Current-Base Table Row Properties Slice

Micro-slice: `pandoc-docx-openxml-core-current-base-20260607T145857Z`
Base accepted HEAD: `8209e40a422edc00341bc56256bb3ab645e8d2d2`

This slice adds bounded native WordprocessingML table row property handoff:

- `DocxReader` now maps `w:trPr/w:tblHeader` to table-row repeat-header review metadata.
- `DocxReader` now maps `w:trPr/w:cantSplit` to table-row no-split review metadata.
- WordprocessingML on/off values are respected: absent/empty `w:val` defaults to enabled, while `false`, `off`, and `0` remain disabled.
- Existing `TableGeometry` row source-attribute packets and `WordPressBlockWriter` row attribute rendering expose the metadata without changing Markdown pipe-table output.
- The existing WordPress DOCX body handoff example now covers repeat-header and no-split table rows.

Source truth: WordprocessingML stores repeating table header rows and row split policy in `w:trPr` as `w:tblHeader` and `w:cantSplit`. This patch ports that bounded DOCX package contract into the existing PHP AST/WordPress handoff. It does not promote rows into `table_head`; that remains a follow-up because this slice is metadata-only and avoids changing table sectioning around existing vMerge/span behavior.

Focused verification:

- `php -l lanes/pandoc/src/DocxReader.php`
  - `No syntax errors detected in lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2115 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - passed

Delta:

- `+1` focused PHP PASS case.
- `+21` focused DOCX assertions over the prior `DocxReaderTest.php` status of `2094` assertions.
- Manifest mapped denominator: `1938 -> 1939`.
- `docxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 406`.
- Lane `phpPass`: `1518 -> 1519`.

Dependency closure:

No new support component is needed. This reuses native `DocxReader` WordprocessingML row/table parsing, `AstNode` table-row attributes, `TableGeometry` row source-attribute review packets, `MarkdownWriter` pipe-table fallback, `WordPressBlockWriter` safe row data/class handoff, focused DOCX tests, and the existing WordPress DOCX body example.

Exclusions:

No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed. Root harness was not run because this is an isolated micro-slice.

Non-overlap:

This does not repeat accepted DOCX table-span, table caption/description, table-cell vertical alignment, table-cell shading, paragraph border, section metadata, SDT form-control, embedded object/package, deleted OMML math revision, tracked formatting-change, or altChunk slices. Follow-up should target non-overlapping DOCX table-cell width/type hints, table row section promotion, or style inheritance merge edges.
