# pandoc-docx-openxml-core-current-base-20260610T111523Z

Base accepted HEAD: `ddb894e80ff8d5c583f3e6576a7ba13e6af1cd39`

## Behavior

Bounded DOCX/OpenXML `w:altChunk` handoff for embedded WordprocessingML document parts:

- `DocxReader` now treats alternative-format chunks with content type `application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml` as importable native WordprocessingML.
- Imported chunk body blocks are parsed through the existing DOCX block pipeline and annotated with `docx-altChunk` provenance including relationship id, target part, content type, and byte count.
- The chunk part relationship set is loaded from `word/chunks/_rels/<part>.rels`, so media inside the embedded document resolves through local OPC relationships rather than the parent document relationship set.
- The import report now records paragraph and block counts for WordprocessingML altChunk parts and keeps missing, external, malformed, or empty chunks explicit.

This deliberately does not evaluate or invoke Office conversion behavior. It only imports bounded WordprocessingML body content already present in the package.

## Evidence

- Syntax: `php -l lanes/pandoc/src/DocxReader.php` -> no syntax errors.
- Syntax: `php -l lanes/pandoc/tests/DocxReaderTest.php` -> no syntax errors.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` -> `1 test files, 4586 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 59704 assertions, 0 failures`.

Expected lane movement: +1 PHP PASS case, +1 mapped DOCX/OpenXML core case, +36 focused assertions. `UPSTREAM_TEST_MANIFEST.json` now reports `mapped` 3126, `mappedDocxOpenXmlCoreCases` 36, and `docxWordprocessingAltChunkAssertions` 36.

## Non-Overlap

This avoids Markdown/plain/CommonMark/HTML microdata/wiki/roff/media-bag diagnostic-only work. It extends the DOCX/OpenXML alternative-format chunk path beyond prior HTML and plain-text chunks by importing native WordprocessingML chunks and their local relationships.

## Dependency Closure

No new native support component is needed. The slice reuses `ZipPackage`, `OpcRelationships`, the existing DOCX block parser, the shared AST, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, zip/unzip, browser renderer, TeX/PDF engine, external validator, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Non-overlapping DOCX follow-ups could preserve nested chunk diagnostics for invalid embedded WordprocessingML, extend altChunk import to additional package-local XML document part variants, or add reviewer metadata for chunk style/numbering part divergence.
