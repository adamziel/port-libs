# pandoc-docx-openxml-core-current-base-20260608T151922Z

Accepted base: `6c4bc0c28ab0b14efaf267cac934e33e915db42f`

## Behavior

This slice adds a bounded native DOCX/OpenXML reader handoff for run-level typographic metrics in WordprocessingML `w:rPr`.

Mapped properties:

- `w:spacing/@w:val` -> `docx-run-spacing` with expanded/condensed classes and `data-docx-run-spacing-twips`
- `w:w/@w:val` -> `docx-run-scale` with `data-docx-run-scale-percent`
- `w:kern/@w:val` -> `docx-run-kern` with `data-docx-run-kern-half-points`
- `w:position/@w:val` -> `docx-run-position` with raised/lowered classes and `data-docx-run-position-half-points`
- `w:fitText/@w:val` and `@w:id` -> `docx-run-fit-text` with width/id review attributes

The implementation reuses the existing DOCX run metadata span path and style-override families so direct run properties replace inherited character-style metric metadata without leaving stale classes or data attributes.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 2703 assertions, 1 failures`
  - Failure: new run metric fixture remained plain text, so the paragraph had one child instead of metric reviewer spans.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 2736 assertions, 0 failures`
  - Delta: one new TestRunner PASS case and 33 focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- Syntax: `php -l lanes/pandoc/src/DocxReader.php`, `php -l lanes/pandoc/tests/DocxReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Status JSON: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc JSON ok\n";'`
  - Result: `pandoc JSON ok`
- Diff hygiene: `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Non-Overlap

This does not repeat accepted DOCX slices for run language/RTL metadata, run effects, tracked paragraph/run formatting changes, structured document tag form controls, deleted OMML math revision auditing, paragraph borders, embedded OLE/package placeholders, document background, or settings/theme/glossary/package metadata. It targets the separate WordprocessingML typographic metric children under `w:rPr`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `DocxReader`, `ZipPackage`, `MarkdownWriter`, and `WordPressBlockWriter`. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external office tools, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Follow-Up

Next DOCX/OpenXML work should choose a non-overlapping reader gap such as additional run/paragraph property provenance, chart/shape metadata, or styles/numbering edge behavior.
