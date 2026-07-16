# markerPDF xref object-stream zero-width member-index review

Micro-slice: `xref-object-stream-index-zero-width-member-review-currentbase-20260602T1629Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates page dictionaries to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text to pypdfium. Source: https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDFium's xref-stream parser reads the third type-2 xref field into `archive_obj_index`, and an empty zero-width third field returns `0`. It then passes that index to `CPDF_ObjectStream::ParseObject(...)`, which rejects the parse when the indexed object-stream header object number does not match the requested object. Source: https://pdfium.googlesource.com/pdfium/+/244491fa800131fe92684212ad55fae0da4bb82b/core/fpdfapi/parser/cpdf_parser.cpp and https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp

## Behavior

The PHP lane already had accepted current-base recovery for zero-width type-2 member indexes: when `/W [1 4 0]` omits the member index, `PdfTextExtractor` can recover the requested object by matching the object-stream header object number. This slice keeps that accepted WordPress import behavior but adds `PdfTextExtractor::extractXrefObjectStreamIndexReview()` so the recovery is review-visible.

The focused fixture builds an object stream with member `12` at index `0` and the current page object `4` at index `1`. The latest xref stream uses `/W [1 4 0]`, so a strict PDFium dependency interpretation would ask for member `0` for object `4` and reject it. The PHP import still recovers object `4` by object number, emits the current page text, and reports `selection_policy=recovered_by_header_object_number` plus `strict_dependency_would_reject=true`.

## Evidence

Focused xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 621 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-index-zero-width-member-review-currentbase.php
recovered_by_header_object_number=true
strict_dependency_would_reject_recovered_member=true
strict_zero_member_decoy_is_review_only=true
excluded_strict_zero_member_decoy_text=true
zero_width_index_entry_count=2
recovered_zero_width_member_count=1
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Status delta: behavior tests `558 -> 559`; mapped upstream/parser semantics `399 -> 400 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted explicit type-2 member-index selection, direct `/ObjStm` base preservation, omitted member-index text recovery, xref-stream `/Prev` generation repair, duplicate `/Index` first-row preservation, hybrid xref free-entry precedence, object-stream filter-chain operand recovery, or stream-owned xref/object owner boundaries.

The new behavior is specifically review metadata for zero-width type-2 member indexes when PHP current-base recovery selects a non-zero object-stream member by header object number while strict PDFium-style member-index parsing would reject that member.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref stream parser, object-stream decoder, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
