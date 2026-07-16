# pandoc-odf-open-document-core-current-base-20260608T213956Z

## Scope

Implemented one bounded ODF/OpenDocument native support-library slice:
`OdfReader` now preserves `draw:layer-set` declarations and `draw:frame`
`draw:layer` references as review metadata. The handoff records declared layer
display/protected/hidden provenance, missing-layer diagnostics, frame layer
reference counts, and safe Markdown/WordPress `data-odf-frame-layer*`
attributes for image and text-box frames.

This is metadata-only. The reader does not evaluate OpenDocument layer
visibility, apply drawing UI policy, run an office suite, or hide rendered
content based on layer declarations.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - passed: `1 test files, 2384 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - passed: `odf open document handoff self-test ok`
- `php -l lanes/pandoc/src/OdfReader.php`
  - passed: no syntax errors
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - passed: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - passed: no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - passed: `json ok`
- `git diff --check -- lanes/pandoc`
  - passed: no whitespace errors

Root harness was not run because this is an isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1876 -> 1877`
- Manifest mapped denominator: `2301 -> 2302`
- ODF/OpenDocument mapped cases: `13 -> 14`
- ODF/OpenDocument focused assertions: `295 -> 326`
- Focused ODF test assertion delta: `+31`

## Dependency Closure

No new support component is needed. This slice reuses native `OdfReader`
content XML parsing, existing ODT package fixtures, `AstNode` frame metadata,
`MarkdownWriter`, `WordPressBlockWriter`, focused ODF tests, and the existing
WordPress ODF handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat prior ODF slices for settings.xml metadata, typed
user-defined metadata, inline meta spans, content validation, default cell
styles, field handoff, data-pilot/subtotal metadata, table tracked changes,
chart object metadata, image xlink/frame anchor metadata, heading source ids,
drop-down fields, or conditional/hidden text fields. The new behavior is
limited to drawing-layer declarations and frame layer references.

## Follow-Up

Next ODF/OpenDocument work should choose a non-overlapping native package or
content mapping gap such as additional draw object policy metadata, table
covered-cell provenance, or bounded style-driven table/list semantics.
