# pandoc-odf-open-document-core-current-base-20260609T013230Z

Base accepted HEAD: `800b696344a9bf658321def4bebfd04d22ba2df2`

## Behavior

- Added native ODF/OpenDocument `draw:caption` support for block-level `draw:frame` image figures in `OdfReader`.
- The caption text is normalized into the figure `caption`, preserved as `odfFrameCaption` source metadata, counted in `importReport.content.frameCaptionCount`, emitted as Markdown provenance attributes, and rendered as a WordPress `<figcaption>` with `data-odf-frame-caption-*` figure attributes.
- Image `alt`, image `title`, package media metadata, frame dimensions, and frame provenance stay separate from the figure caption.

## Source Truth

- This slice follows the existing lane ODF package contract for `draw:frame`, `draw:image`, `draw:text-box`, and table caption mappings in `lanes/pandoc/src/OdfReader.php`.
- It is intentionally bounded to the OpenDocument XML handoff surface; no office-suite conversion or upstream Pandoc runner was invoked.
- Non-overlap: this does not repeat the accepted ODT `text:tab`, heading anchor/source-id, table caption, text-box image caption, table subtotal, dropdown field, form control, chart, object/OLE, settings.xml, drawing-layer, or frame dimension/xlink/layer slices.

## Verification

- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2628 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2647 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.
- PHP lint passed for changed PHP files.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `OdfReader` DOM/libxml NONET parsing, `ZipPackage` in-memory ODT fixtures, `AstNode` figure/image metadata handoff, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane test harness.

Follow-up, if desired: preserve rich inline formatting inside `draw:caption` captions instead of the bounded normalized plain-text caption used here.
