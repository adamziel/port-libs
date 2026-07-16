# markerPDF object-stream xref parser current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260606T002914Z`
Session: `port-dev-markerpdf-object-xref-20260606T002914Z`
Accepted base: `a0f9a4e8486a1870b3b58c910a9dc3a6b97dbb35`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through `marker/pdf/extract_text.py::get_text_blocks()` and `pdftext.extraction.dictionary_output(...)`, with `naive_get_text()` delegated to PDFium. Under the current no-GPU markerPDF scope, this lane owns native PDF parser boundaries that feed WordPress review metadata before OCR/layout/model handoff.

PDF object streams carry generation-zero object bodies selected by object-stream headers and xref type-2 rows. Member offsets are relative to the first object byte and must point at top-level object boundaries, not inside another member's literal string, comment, array, hex string, or nested dictionary. The text, metadata, and attachment paths already had equivalent guards; this slice closes the same boundary for the AcroForm review path.

## Behavior

Before the source fix, `PdfAcroFormExtractor` expanded direct `/ObjStm` members by slicing at header offsets without validating that the selected offset was a top-level token boundary. A malicious or malformed `/Fields [30 0 R]` entry could therefore bind to a fake field dictionary whose object-stream offset pointed inside another valid field's literal string.

`PdfAcroFormExtractor` now:

- validates every selected object-stream member offset before extracting an AcroForm field/widget dictionary;
- ignores malformed later offsets when computing the end of a valid earlier member;
- preserves existing valid compressed AcroForm field/widget recovery;
- keeps field values and malformed decoys out of visible WordPress text.

## Evidence

Red-first focused probe before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands object-stream AcroForm field and widget dictionaries before WordPress review
FAIL rejects AcroForm object-stream member offsets inside literal strings before WordPress review
Expected: [compressed.current, compressed.review.status]
Actual: [compressed.current, compressed.offset.decoy, compressed.review.status]
1 test files, 34 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands object-stream AcroForm field and widget dictionaries before WordPress review
PASS rejects AcroForm object-stream member offsets inside literal strings before WordPress review
1 test files, 50 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-currentbase.php
```

The smoke reports `literal_offset_decoy_excluded=true`, `object_stream_field_dictionaries_recovered=true`, `object_stream_widget_dictionaries_recovered=true`, `field_values_review_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted object-stream type-2 member index repair, plus-signed headers, header comments in attachment metadata, text/metadata member-offset guards, xref `/Prev` object-stream carrier repair, stream-member rejection, duplicate object-number/offset guards, or object-stream filter-owner behavior. The bounded behavior is only AcroForm object-stream member offset validation before form review metadata extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF direct-object scanner, object-stream decoder, AcroForm field/widget extractor, and WordPress smoke path. Full upstream parity remains gated by live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, benchmark/model downloads, Streamlit/FastAPI runtimes, and external OCR/rendering helpers; none were executed for this no-GPU PHP slice.
