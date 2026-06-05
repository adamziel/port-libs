# xref Prev Chain Object-Stream Metadata Current Base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T040718Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T040718Z`
Base accepted HEAD: `9bdcc4412b1e3929aacccdfe68ef298910f9c004`

## Source Truth

Upstream markerPDF delegates searchable-PDF object/xref parsing to pdftext/PDFium. In native PHP no-GPU scope, this lane owns the parser boundary. PDF xref stream type-2 entries select generation-zero ordinary objects from a referenced `/ObjStm` carrier, and incremental updates can combine that with `/Prev` chains to supersede stale previous-section catalog, metadata, Info, and EmbeddedFiles dictionaries.

## Behavior

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now expand xref-selected object-stream member dictionaries after the live xref `/Prev` chain has selected current objects. The expansion is bounded to selected direct `/ObjStm` carriers, requires explicit member index/object-number agreement when the xref row provides an index, rejects ambiguous non-explicit duplicate object-number members, and skips top-level stream objects so XMP and EmbeddedFile payload streams remain direct objects.

The focused fixture creates a previous classic xref table with stale page text, XMP/Info/catalog metadata, and attachments, then a current xref stream update whose catalog, Info, EmbeddedFiles name-tree, and FileSpec dictionaries live inside an object stream while current XMP and EmbeddedFile streams remain direct. The current object-stream dictionaries now win across the `/Prev` chain, and stale previous-section metadata/attachment/text is excluded.

## Evidence

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-object-stream-metadata-currentbase.php`  
  Result: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php`  
  Result: `1 test files, 25 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php`  
  Result: `3 test files, 268 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`  
  Result: `5 test files, 1523 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-object-stream-metadata-currentbase.php`  
  Result: exits 0 and reports current object-stream XMP/Info/catalog language/attachment/text selected, stale previous metadata/attachment/text excluded, and no Python/models/external PDF tools.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat earlier xref `/Prev` damaged-offset repair, indirect `/Prev`, compressed `/Prev`, hybrid companion xref merge, sparse latest Info, object-stream owner/free-entry, text extraction object-stream parity, or lightweight attachment object-stream FileSpec preflight. The new behavior is the full metadata plus embedded-file review path selecting current object-stream dictionaries from xref-stream type-2 rows across an incremental `/Prev` chain.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP xref table/stream `/Prev` walker, direct object scanner, Flate stream decoder, object-stream member table parser, metadata extractor, embedded-file extractor, and WordPress smoke path. OCR, Surya, Texify, Torch, PDFium execution, raster rendering, and live model workers remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native parser fidelity: xref repair edges that affect page geometry, forms/annotation review, font/CMap selection, image/filter metadata, and security preflight without launching model or OCR paths.
