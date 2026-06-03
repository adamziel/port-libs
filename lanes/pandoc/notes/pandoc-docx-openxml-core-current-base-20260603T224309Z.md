# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260603T224309Z`

Base accepted HEAD: `85557f8778556ce0ac84fb5a5ff73b8dff7ca4e4`

## Behavior Added

- Extended `DocxReader` to load optional DOCX styles and numbering parts via
  document-level OPC relationships.
- Resolved paragraph heading levels from direct `Heading1`-`Heading6` style
  ids, `w:outlineLvl`, and custom paragraph styles that inherit from heading
  styles with `w:basedOn`.
- Resolved list semantics from direct paragraph `w:numPr` and style-carried
  `w:numPr`.
- Parsed bounded `word/numbering.xml` definitions for bullet lists and ordered
  lists, including decimal/lower-alpha/upper-alpha/lower-roman/upper-roman
  formats, start values, and one-/two-parenthesis marker delimiters.
- Grouped consecutive flat DOCX list paragraphs into existing
  `bullet_list`/`ordered_list` AST nodes so the accepted Markdown and
  WordPress writers preserve imported checklist and review-step lists.
- Updated the WordPress DOCX body handoff smoke with a custom style heading,
  bullet checklist items, and a lower-alpha ordered review sequence.

## Source Truth

- This slice builds on the accepted DOCX/OpenXML source-truth record from the
  prior DOCX body note and manifest: upstream Pandoc reads the DOCX OPC
  package, resolves the `officeDocument` target, walks `word/document.xml`, and
  converts WordprocessingML paragraphs/runs/tables into Pandoc AST blocks.
- The current bounded extension ports the next WordprocessingML contract that
  was explicitly left as the next gate: styles and numbering affect whether a
  paragraph is a heading or list item before it reaches Markdown/WordPress
  output.
- This is intentionally not a full Word style engine. It does not implement
  multilevel list nesting, list restarts beyond `w:start`/`w:startOverride`,
  complex paragraph/run property inheritance, comments, endnotes, OMML, field
  codes, charts, or diagrams.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 89 assertions, 0 failures`.
  - Delta: focused DOCX test moved from 57 to 89 assertions and from 4 to 5
    PASS cases.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `6 test files, 2811 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, OPC content-types parser, OPC relationships parser, and OPC
relationship graph. It does not invoke Pandoc, Cabal, Word, LibreOffice,
`zip`, `unzip`, TeX/PDF engines, external template engines, online conversion
services, citeproc, BibTeX/Biber, bibliography managers, or Haskell test
binaries.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory metadata,
local-header validation, OPC content types, OPC relationship graph loading or
target-integrity preflight, doctemplate, YAML, Citation/CSL, Markdown
reader/writer, HTML reader, WordPress Markdown handoff, or the prior minimal
DOCX body/core-property reader. It extends that DOCX reader only with bounded
style and numbering semantics.

## Follow-Up

Keep richer DOCX nested numbering, table grid spans, comments/endnotes, media
extraction policy, OMML/math, charts/diagrams, field-code interpretation, and
higher-level OPC preflight diagnostics as separate gates.
