# Xref Object-Stream Metadata Offset Boundary Current Base

- Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T055723Z`
- Session: `port-dev-markerpdf-object-xref-20260605T055723Z`
- Accepted base: `53ebd321947feaf9182f7b52290f5f26750e7000`
- Scope: native PHP parser behavior only; no OCR, model, GPU, Python, or external PDF-tool execution.

## Source Truth

Upstream markerPDF relies on PDF parser backends for object stream and xref expansion before text and metadata are surfaced. In the current no-GPU port scope, the bounded source-truth contract is that xref type-2 object-stream entries must resolve to real compressed object member boundaries, not arbitrary bytes inside another member payload. This patch reuses the existing native xref stream/object stream decoder and aligns metadata expansion with the text extractor's `invalid_object_stream_member_offset` review behavior.

## Behavior

`PdfMetadataExtractor` now rejects xref-selected object-stream metadata members when the selected member offset lands inside a compressed literal string, comment, array, or dictionary payload. The focused fixture points the catalog xref entry at bytes inside another object stream member's literal string containing a fake catalog language `zz-ZZ`; after the patch, WordPress metadata import keeps the current visible page and excludes the fake catalog language instead of promoting that nested literal string as the catalog.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php
```

Result: `1 test files, 5 assertions, 1 failures` because metadata still contained fake catalog language `zz-ZZ` from inside the compressed literal string.

## Final Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php
```

Result: `1 test files, 18 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamMetadataOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php
```

Result: `5 selected test files, 951 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-metadata-offset-boundary-currentbase.php
```

Result: smoke output reports `uses_current_visible_page=true`, `rejects_literal_offset_catalog_metadata=true`, `excludes_fake_catalog_language=true`, `invalid_member_offset_rejection_count=1`, `selection_policy=invalid_object_stream_member_offset`, and both model/external-tool flags false.

## Non-Overlap

This slice does not repeat accepted literal/comment object-stream text offset guards, type-2 index review, stream-member rejection, unfiltered object-stream member rejection, skipped object-stream header index handling, duplicate offset handling, current-carrier repair, `/Prev` generation rebuild, or classic xref linearized startxref boundary work. It is the metadata extractor counterpart for xref-selected catalog member offsets that land inside another compressed member's literal string.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP xref stream parser, object stream decoder, metadata extractor, text review helper, and lane WordPress smoke. GPU/model parity and live OCR remain intentionally out of scope under the current markerPDF no-GPU directive.
