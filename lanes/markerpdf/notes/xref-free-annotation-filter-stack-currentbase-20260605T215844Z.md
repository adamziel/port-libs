# markerpdf-object-stream-xref-parser-current-base-20260605T215844Z

## Source Truth

- Lane: markerpdf
- Accepted base: 1ed26db9ffe690d36c29112143668a173c4194ae
- Upstream behavior boundary: PDF xref stream filters are applied in order before xref entries are interpreted. The native no-GPU annotation/link path must honor a latest xref-stream free row before exposing stale page annotations to WordPress review metadata.

## Implemented Behavior

- `PdfXrefFreeObjectMap` now decodes lightweight xref stream filter stacks for free-object suppression.
- Supported bounded filters in this path are no filter, `/FlateDecode` or `/Fl`, and ordered `/ASCIIHexDecode` or `/AHx` followed by Flate when declared in `/Filter [...]`.
- Unsupported or malformed filter declarations still fail closed by returning no free-map rows.
- The new fixture uses a previous classic xref section with a live link annotation, then a current xref stream encoded with `/Filter [ /ASCIIHexDecode /FlateDecode ]` that marks object `7 0` free. Link and annotation extractors now suppress that stale object.

## Verification

- Red-first before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php`
  - Result: `1 test files, 1 assertions, 1 failures`; failed because the free-object map did not decode the xref-stream filter stack.
- Focused after source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`.
- Adjacent xref/free-map sweep:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php`
  - Result: `4 test files, 36 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-xref-free-annotation-filter-stack-currentbase.php`
  - Result: emitted `free_annotation_suppressed=true`, `stale_link_excluded=true`, `stale_review_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses existing native PHP xref stream parsing and PHP `gzuncompress`; the ASCIIHex decoder is bounded local parser support for xref-stream free-row preflight only.

## Non-Overlap

This does not repeat accepted object-stream member-table repair, text-extractor xref DecodeParms handling, indirect `/W` and `/Index` free-row suppression, or indirect `/Prev` free-row traversal. The patch is scoped to the lightweight free-object map used by annotation/link suppression.
