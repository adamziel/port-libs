# XMP Unbounded Root Boundary Current Base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T095127Z`  
Base: `f37923538221acd51c7fa0f16b86121e0ff32955`

## Behavior

Catalog `/Metadata` streams without xpacket delimiters can still carry a root
Adobe `x:xmpmeta` XML packet. If that first root is unbounded, the native PHP
metadata extractor now treats it as the active malformed metadata boundary and
fails closed. It no longer promotes an inner `rdf:RDF` title from inside the
unclosed wrapper, and it does not let a later appended valid-looking
`x:xmpmeta` root replace the malformed first root.

This keeps WordPress imports on trailer `/Info` fallback metadata while
recording `catalog.metadata_stream_review.status =
rejected_malformed_document_xmp_packet` and a redacted `xmp_summary` reason of
`unbounded_adobe_xmpmeta_root`.

## Source Truth

Upstream markerPDF at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` loads searchable PDF text through
`pdftext.dictionary_output()`/PDFium before model stages in
`marker/pdf/extract_text.py` and `marker/convert.py`. Under the no-GPU scope,
this maps the same parser boundary natively: document XMP is metadata, not
visible text, and malformed metadata packets must not be recovered by scanning
later stale stream bytes.

## Red-First Evidence

Before the parser patch, a no-xpacket fixture with an unclosed first Adobe
`x:xmpmeta` root plus a trailing valid root promoted `Unbounded First XMP Root
Title` as root XMP metadata.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnboundedRootBoundaryCurrentBaseTest.php`

Result: `1 test files, 47 assertions, 0 failures`.

The new test adds 2 focused PASS cases.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUnboundedRootBoundaryCurrentBaseTest.php`

Result: `1 test files, 47 assertions, 0 failures`.

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `65 test files, 3888 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-unbounded-root-boundary-currentbase.php --self-test`

Result: passed, with `malformed_document_xmp_rejected=true`,
`inner_rdf_title_not_promoted=true`, `trailing_xmp_decoy_excluded=true`,
`visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`,
and `executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpUnboundedRootBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-unbounded-root-boundary-currentbase.php` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3027 -> 3029`.
- `wordpressScenarios`: `2504 -> 2505`.
- `suiteProgress`: `2179 -> 2181` tracked PHP behavior tests.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` type/subtype validation,
duplicate/extra metadata operands, stream dictionary role operands, DTD/entity
fail-closed packets, xpacket begin/end priority, unquoted instruction handling,
internal begin/end instruction handling, malformed first xpacket handling,
namespace-wrapper skipping, UTF-16/declared encoding, RDF collection/resource
reference, associated-file XMP, PieceInfo XMP, OutputIntent, name-tree, xref,
CMap/filter, image, annotation, form, page geometry, or supplied-boundary table
behavior. The bounded behavior is only an unpacketed unbounded first Adobe
`xmpmeta` root before inner RDF or trailing-root fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, catalog metadata stream resolver, stream decoder, XML root token
walker, XMP review summarizer, trailer Info fallback decoder, text extractor,
focused test harness, and WordPress smoke renderer. GPU/model OCR,
pypdfium/PDFium rendering, PIL, Surya, Texify, Torch, Streamlit/FastAPI model
workers, live-service calls, and external PDF tools remain intentionally out of
scope.
