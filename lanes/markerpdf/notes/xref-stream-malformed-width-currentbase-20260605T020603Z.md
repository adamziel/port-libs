# markerPDF xref-stream malformed width current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T020603Z`
Base: `acb802419160de9958bfd37cbf73eb0342fb23ad`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level xref/object-stream parsing to pdftext/PDFium. The native no-GPU PHP boundary therefore has to parse PDF 1.5 xref streams before WordPress paragraph rendering, without Python, models, PDFium execution, or external PDF tools.

PDF xref-stream `/W` values are byte counts for row fields. Negative widths are malformed and must not be decoded as row data. Current base already kept visible text empty for the focused malformed stream, but it misclassified `/W [-1 4 1]` as seven type-0 free entries because row decoding continued with a negative type-field width.

## Implementation

`PdfTextExtractor` now validates xref-stream `/W` arrays before row decoding:

- missing, incomplete, negative, or all-zero `/W` arrays are treated as malformed xref-stream structure;
- startxref-selected xref streams with malformed `/W` fail closed before fallback object scanning;
- review metadata reports `malformed_xref_stream_width_entries` with the selected xref offset, selected generation, raw width array, parsed widths, malformed indexes, and `owner_policy`;
- negative-width rows are no longer decoded into bogus type-0 free-owner entries.

The focused fixture builds a latest xref stream with `/W [-1 4 1]`, type-2 rows pointing at an object stream, and stale direct/object-stream text decoys. After the patch WordPress-visible text remains empty, stale direct/object-stream text stays excluded, and review metadata reports `negative_xref_stream_field_width` instead of fake free rows.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed negative xref-stream W byte widths before object-stream fallback text

1 test files, 20 assertions, 0 failures
```

Adjacent xref/object-stream parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 176 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-malformed-width-currentbase.php
```

The smoke emits:

- `malformed_xref_stream_width_count=1`
- `malformed_width_owner_policy=negative_xref_stream_field_width`
- `malformed_width_indexes=[0]`
- `rejected_before_row_decode=true`
- `bogus_free_entries_excluded=true`
- `visible_text_empty=true`
- `stale_object_stream_text_excluded=true`
- `stale_direct_text_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat accepted xref-stream indirect `/W` and `/Index` operand resolution, zero-width `/W` generation/index repair, malformed sparse `/Index` current-offset repair, xref-stream `/Prev` object-stream operand recovery, object-stream header comments, skipped zero object-number header rows, incomplete object-stream headers, offset-order body slicing, explicit type-2 member-index selection, duplicate member-offset rejection, stream-member rejection, object-stream carrier generation repair, hybrid xref table/free precedence, stream-owned xref/startxref rejection, or stream-filter DecodeParms/filter-owner recovery.

The new behavior is specifically malformed negative xref-stream `/W` field-width validation before xref row decoding and object-stream fallback text import.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref xref-stream parser, Flate stream decoder, object-stream expander, page-tree walker, content-token extractor, and WordPress smoke path. GPU/model/OCR, pdftext, pypdfium2/PDFium, PIL, Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU markerPDF slice.
