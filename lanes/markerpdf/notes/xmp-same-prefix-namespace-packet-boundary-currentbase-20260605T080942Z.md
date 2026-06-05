# markerPDF XMP same-prefix namespace packet boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T080942Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In this no-GPU native PHP lane, catalog `/Metadata` XMP streams are document metadata only when their packet root is a document-level `rdf:RDF` root or an Adobe `adobe:ns:meta/` `xmpmeta` wrapper.
- A complete `x:xmpmeta` packet bound to a non-Adobe namespace is not an Adobe XMP packet wrapper, even when it contains nested RDF with document-looking fields. It must not leak into promoted WordPress metadata or rejected-stream review summaries.

## Behavior

- `PdfMetadataExtractor` now treats a packet containing only non-Adobe `xmpmeta` roots as no bounded document-XMP candidate, so the next complete xpacket can be evaluated.
- RDF fallback no longer escapes from a complete same-prefix wrong-namespace `x:xmpmeta` packet.
- Accepted `/Type /Metadata /Subtype /XML` streams promote the later Adobe packet, while rejected XML metadata-stream reviews summarize the same later Adobe packet with text values redacted.
- Existing different-prefix namespace, trailing packet, packet begin/end, UTF-16, CDATA/comment, entity rejection, empty/self-closing root, typed-node, lang-alt, and nested qualifier XMP boundaries remain green.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNamespaceBoundaryCurrentBaseTest.php`

Failed before the parser change:

- `skips complete same-prefix non-Adobe xmpmeta packets before document XMP`: expected `Current Same Prefix Boundary XMP Title`, got `Wrong Same Prefix Namespace XMP Title`.
- `summarizes rejected XML streams after complete same-prefix non-Adobe xmpmeta packets`: expected `2026-06-05T07:11:17Z`, got `2026-06-05T07:09:59Z`.

Result: `1 test files, 66 assertions, 2 failures`.

## Verification

Focused namespace boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNamespaceBoundaryCurrentBaseTest.php`

Result: `1 test files, 81 assertions, 0 failures`.

Focused XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `17 test files, 745 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-namespace-boundary-currentbase.php`

Emits `title_from_current_adobe_xmp=true`, `packet_boundary_applied=true`, `same_prefix_wrong_namespace_decoy_excluded=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpNamespaceBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-namespace-boundary-currentbase.php` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted packet padding, begin/end pre-packet, different-prefix namespace-wrapper, UTF-16, CDATA/comment false-closer, DTD/entity rejection, empty/self-closing root, typed-node, qualified-value, lang-alt, PDF/A schema correlation, encrypted metadata source-priority, XRef metadata generation, or CMap/font/text extraction slices. The bounded behavior is specifically complete same-prefix non-Adobe `x:xmpmeta` xpacket bodies before a later Adobe document XMP packet.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet scanner, DOM-based metadata extraction, metadata review summary path, text extractor, and WordPress smoke pattern. Full OCR/model execution, Surya/Texify/Torch, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
