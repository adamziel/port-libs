# pandoc-odf-open-document-core-current-base-20260607T135520Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260607T135520Z`
- Accepted base: `aca46c9cdf383af520793341926598425c32c7ab`
- Source truth: pinned Pandoc ODT `ContentReader.hs` image-frame path reads `draw:image` `xlink:href`, frame title/description, and dimension attributes for image handoff. This slice keeps the existing native PHP image handoff and adds bounded ODF xlink review metadata from the same `draw:image` node.

## Behavior

- `OdfReader` now preserves `draw:image` `xlink:type`, `xlink:show`, and `xlink:actuate` when present.
- The metadata is exposed on image AST nodes as `odfImageMetadata` with `xlinkType`, `xlinkShow`, and `xlinkActuate`.
- Markdown and WordPress handoffs receive inert reviewer attributes: `data-odf-image-xlink-type`, `data-odf-image-xlink-show`, and `data-odf-image-xlink-actuate`.
- Existing image href normalization, title, alt text, dimensions, manifest media metadata, encrypted-resource behavior, and package byte handling are unchanged.

## Focused Evidence

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1514 assertions, 0 failures`
  - Adds one PHP PASS case and 14 focused assertions for ODF image xlink metadata.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 1609 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - passed

Root harness was not run because this is an isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native `OdfReader` content XML parsing, the existing package media handoff, and the existing Markdown/WordPress writers. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF slices for text tabs, blockquote styles, heading identifiers/source IDs, conditional/hidden fields, database/page/statistic fields, generated indexes, chart metadata, form controls, object/OLE/math placeholders, frame dimensions, or package URI normalization. It is limited to `draw:image` xlink review metadata.

## Next

For ODF/OpenDocument follow-up, keep work bounded to non-overlapping native content/styles/meta XML mapping such as image caption edge cases, remaining draw/frame metadata, or style-driven table/list behavior.
