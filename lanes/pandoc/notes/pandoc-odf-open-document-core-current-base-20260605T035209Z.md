# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T035209Z`
- Accepted base: `6d64cdd094e3b18966c99f1b9175eeb1c0e36714`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for frame image dimensions:

- Reads `svg:width` and `svg:height` from `draw:frame` / `draw:image` image frames.
- Preserves those values on image AST nodes as `width`, `height`, and Pandoc-style image attributes.
- Allows the WordPress block writer to emit safe `width` and `height` attributes for image nodes.
- Updates the WordPress ODF handoff example self-test to prove ODT image dimensions survive into rendered WordPress blocks.

Source truth: upstream Pandoc `Text.Pandoc.Readers.ODT.ContentReader` at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` reads `svg:width` and
`svg:height` in `read_frame_img` and attaches them to Pandoc image attributes.

This is bounded to OpenDocument package/content XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 332 assertions, 0 failures`
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 353 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `597 -> 598`.
- `benchmarkDenominator.mapped`: `1071 -> 1072`.
- Focused `OdfReaderTest.php`: `13 -> 14` cases, `332 -> 353` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `14` mapped cases / `353` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local image dimension parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list/
annotation/text-box/image presence, footnote/endnote, bookmark-reference,
reference-mark/reference-ref, sequence, tracked-change, encrypted-manifest,
MathML object, linked/protected section, and page-layout/master-page clusters.
It adds only bounded OpenDocument image dimension handoff and safe WordPress
image attribute rendering.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview policy beyond MathML, table continuation semantics,
export-side ODT writing, and full Pandoc ODT reader parity.
