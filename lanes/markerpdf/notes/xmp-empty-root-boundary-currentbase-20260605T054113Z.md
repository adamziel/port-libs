# XMP Empty Root Boundary Current Base

Session: `port-dev-markerpdf-metadata-xmp-20260605T054113Z`

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T054113Z`

Base accepted HEAD: `4b80fbb617415ca3af053741139f8ed1fe4bccdf`

## Source Truth

Upstream markerPDF at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF document metadata separate from visible page text through the PDF parser/pdftext/PDFium boundary. In the native no-GPU PHP lane, catalog `/Metadata` XMP streams are the in-scope metadata source; XMP packet padding, empty wrappers, and stale appended XML must not leak into Gutenberg paragraphs or replace the current RDF metadata root.

This slice is bounded to XMP root selection inside a catalog `/Metadata` stream. It does not execute OCR, Surya/Texify/Torch, PDFium, Python, raster rendering, or external PDF tools.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpEmptyRootBoundaryCurrentBaseTest.php
```

Result before implementation:

- accepted metadata source was `["info"]` instead of `["xmp","info"]` after a DOM-loadable empty non-self-closing `x:xmpmeta` wrapper;
- rejected XML stream review had no `xmp_summary.field_names` for the later current RDF metadata root.

The failing run reported `1 test files, 14 assertions, 2 failures`.

## Implementation

`PdfMetadataExtractor` now continues past DOM-loadable XMP candidates that contain no document metadata. The bounded root scanner records empty `x:xmpmeta` wrappers, then keeps scanning until the first non-empty wrapper containing an RDF root, while still avoiding trailing decoy packets after the current metadata root.

The WordPress smoke fixture proves:

- empty non-self-closing XMP wrapper skipped;
- current catalog XMP title/description/authors/keywords promoted;
- trailing XMP decoy excluded from metadata;
- XMP text excluded from visible paragraphs;
- Python/model/external PDF tools not executed.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpEmptyRootBoundaryCurrentBaseTest.php
```

Passed: `1 test files, 40 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpEmptyRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpSelfClosingRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpEntityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `12 test files, 1325 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-empty-root-boundary-currentbase.php
```

Passed: emitted `empty_wrapper_skipped=true`, `packet_boundary_applied=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, XMP packet boundary logic, DOM XML parser with `LIBXML_NONET`, document metadata merger, and text extractor. GPU/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat direct catalog XMP extraction, packet trailing-padding boundaries, comment/DOCTYPE skipping, CDATA markers, DTD/entity fail-closed handling, BOM-less UTF-16 decoding, self-closing empty wrapper skipping, language alternatives, typed-node extraction, qualified-value extraction, nested qualifier suppression, PieceInfo/OutputIntent scope, encrypted metadata priority, xref-stream trailer metadata, or DCT/image stream-filter boundaries. The bounded new behavior is empty non-self-closing `x:xmpmeta` wrappers before the current RDF XMP root.
