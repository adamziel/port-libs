# Outline Metadata Key Operand Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260608T102651Z`
Base accepted HEAD: `316968fe851e07341d518253a84225941939f5fc`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF text and PDFium/pdftext document structure before OCR/model stages. Native PHP outline metadata therefore stays a review-only PDF parser boundary: outline `/Metadata` streams can be summarized, but malformed outline dictionary operands must not promote stream bytes, action tails, or hidden text into WordPress navigation or paragraph output.

PDF outline `/Metadata` values are single indirect stream references. A malformed private key-like operand after that reference, for example `/Metadata 9 0 R /Private /AA 21 0 R /Next ...`, leaves extra top-level operands before the next usable outline key. This patch treats that selected `/Metadata` entry as ambiguous and review-only rejected.

## Behavior

`PdfMetadataExtractor::documentOutlineMetadataMalformedOperandReview()` now folds malformed key-like operands into the existing outline-local `/Metadata` operand boundary. It preserves clean private dictionary keys, but when a key-like token consumes one value and still leaves extra top-level operands before the next dictionary key, the selected `/Metadata` reference is rejected with the existing `rejected_malformed_outline_*_metadata_operand` status.

The focused fixture covers both outline-root and outline-item metadata:

- Root `/Metadata 8 0 R /Private /A 20 0 R /First ...`
- Item `/Metadata 9 0 R /Private /AA 21 0 R /Next ...`

The valid outline titles, destinations, and page text are preserved. Hidden metadata stream payloads and hidden action payloads stay out of document metadata JSON, navigation review, and visible WordPress paragraphs.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataKeyOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects outline Metadata streams followed by malformed key-like operands
Expected: 'rejected_malformed_outline_root_metadata_operand'
Actual: 'reviewed_outline_root_metadata_stream'
FAIL keeps rejected key-operand outline Metadata out of navigation and WordPress text
Expected: 'rejected_malformed_outline_item_metadata_operand'
Actual: 'reviewed_outline_item_metadata_stream'

1 test files, 16 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataKeyOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects outline Metadata streams followed by malformed key-like operands
PASS keeps rejected key-operand outline Metadata out of navigation and WordPress text

1 test files, 62 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name '*OutlineMetadata*Test.php' | sort)
66 test files, 2796 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-key-operand-currentbase.php
=> exits 0; root_metadata_status=rejected_malformed_outline_root_metadata_operand; item_metadata_status=rejected_malformed_outline_item_metadata_operand; payloads_excluded=true; action_payloads_excluded=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline `/Metadata` direct non-scalar operands, duplicate `/Metadata` entries, ordinary tailed indirect references, stream-tail operands, duplicate stream Type/Subtype keys, generation-exact metadata streams, root metadata streams, trailer-root selection, outline `/Prev`/`Next`/`Parent`/`Last` traversal, PageLabels `/Nums` key operands, named destinations, annotations, xref repair, fonts, images, tables, OCR, or model execution. The bounded behavior here is only malformed key-like operands immediately following outline-local `/Metadata` entries.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/value tokenizer, outline metadata review path, outline navigation extractor, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, PDF action execution, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU lane rules.
