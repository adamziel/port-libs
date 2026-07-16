# pandoc-odf-open-document-core-current-base-20260607T150158Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260607T150158Z`
- Accepted base: `180cbd9396d0f069d253898f9c8b943402e9e222`
- Source truth: accepted Pandoc lane ODF image/frame handoff behavior in the native `OdfReader`, `UPSTREAM_TEST_MANIFEST.json`, and prior ODF notes. The local Pandoc upstream cache was not present in this worktree, so no upstream runner or external converter was used.

## Behavior

- `OdfReader` now preserves ODF image `draw:frame` anchor/provenance metadata when the frame carries review-relevant data beyond a plain name:
  - `draw:name`
  - `draw:style-name`
  - `text:anchor-type`
  - `text:anchor-page-number`
  - `svg:x`
  - `svg:y`
  - `draw:z-index`
- The metadata is stored on the image AST node as `odfFrameMetadata`.
- Markdown and WordPress handoffs receive inert `data-odf-frame-*` attributes alongside existing image width/height and xlink metadata.
- Plain frame names without additional frame metadata remain unchanged to preserve existing ODF image output.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1538 assertions, 0 failures`
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - New focused case failed before implementation because `odfFrameMetadata` was `NULL` for anchored `draw:frame` images.
  - `1 test files, 1545 assertions, 1 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1555 assertions, 0 failures`
- Final family check: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 1650 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON metadata validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - passed

Root harness was not run because this is an isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1519` -> `1520`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1939` -> `1940`
- ODF/OpenDocument core cases: `11` -> `12`
- ODF/OpenDocument core focused assertions: `251` -> `268`

## Dependency Closure

No new support component is needed. This slice reuses native `OdfReader` content XML parsing, `ZipPackage` fixtures, `AstNode` image metadata, `MarkdownWriter`, `WordPressBlockWriter`, focused ODF tests, and the existing WordPress ODF handoff example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF slices for image dimensions, image xlink metadata, text tabs, blockquote styles, heading identifiers/source IDs, conditional/hidden fields, database/page/statistic fields, generated indexes, chart metadata, form controls, object/OLE/math placeholders, or package URI normalization. It is limited to image `draw:frame` anchor/provenance metadata and downstream Markdown/WordPress handoff attributes.

## Next

For ODF/OpenDocument follow-up, keep work bounded to non-overlapping native content/styles/meta XML mapping such as draw object placeholder metadata, style-driven table/list metadata, or conditional-section policy metadata.
