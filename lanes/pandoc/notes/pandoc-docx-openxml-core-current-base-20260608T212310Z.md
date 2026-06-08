# DOCX/OpenXML Current-Base DrawingML Picture Crop/Transform

## Behavior

`DocxReader` now preserves bounded DrawingML picture metadata for DOCX image handoff nodes:

- `pic:blipFill/a:srcRect` crop values are retained as `data-docx-picture-crop-*` reviewer attributes.
- `pic:spPr/a:xfrm` rotation, horizontal/vertical flip flags, offsets, and extents are retained as `data-docx-picture-*` reviewer attributes.
- Image nodes gain `docx-picture-crop`, `docx-picture-transform`, and flip-specific classes when the source picture records those states.

The metadata is preserved through Markdown image attributes and WordPress image block handoff without changing image bytes or invoking external Office tooling.

## Source Truth

The slice maps the native DOCX/OpenXML picture contract for `pic:pic` containers, `pic:blipFill/a:srcRect`, and `pic:spPr/a:xfrm` values. Raw source units are retained for reviewer audit instead of normalizing them through an external renderer.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework check: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Red-first focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed with `1 test files, 3082 assertions, 1 failures` because DrawingML `a:srcRect`/`a:xfrm` metadata was not preserved on image nodes.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3151 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed.
- PHP syntax checks passed for changed PHP files.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This does not repeat prior DOCX/OpenXML slices for DrawingML container geometry, chart/diagram placeholders, DrawingML text, VML images, altChunk, embedded objects/packages, subdocuments, structured document tags, field handoff, comments/notes, or tracked formatting changes. This patch is limited to picture-specific crop and transform metadata.

## Dependency Closure

No new support component is needed. The slice reuses existing native `DocxReader` package/body parsing, AST image attributes, `MarkdownWriter`, and `WordPressBlockWriter` handoff paths.

## Follow-Up

Good next DOCX/OpenXML gaps include non-overlapping theme font inheritance, caption numbering heuristics, or remaining DrawingML non-picture shape metadata.
