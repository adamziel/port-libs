# markerpdf outline metadata stream Length boundary current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- PDF metadata streams use stream dictionaries, and `/Length` is the byte-count boundary for decoding stream content. In the native PHP no-GPU lane, outline root/item `/Metadata` streams must reject `/Length` values that are not one non-negative integer before XML summary, hashing, or WordPress navigation review promotion.
- This slice does not repeat accepted outline `/Filter`, `/DecodeParms`, duplicate `/Type`/`/Subtype`, `/Metadata` reference-tail, key-like operand, root traversal, destination/action, annotation, OCR, or model-worker behavior.

## Implementation

- `PdfMetadataExtractor::metadataStreamLengthOperandBoundaryReview()` now treats direct dictionary tail operands after `/Length` the same way it already treats tailed indirect helper objects: the stream is review-only rejected before decode.
- The review now exposes `length_operand_boundary=single_non_negative_integer` and `length_operand_boundary_rejected=true` for duplicate or malformed metadata stream `/Length` entries.
- Added focused outline fixtures for both root and item metadata streams:
  - item `/Metadata` stream with `/Length 9 0 R` where object `9` contains `<length> 10 0 R`;
  - root `/Metadata` stream with direct `/Length <length> 10 0 R`.
- Added a WordPress smoke that keeps safe outline TOC/navigation and visible page text while excluding tailed metadata payloads and helper action text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects outline item Metadata streams with indirect Length helpers that carry extra operands (lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'single_non_negative_integer'
Actual: NULL
FAIL rejects outline root Metadata streams with direct Length operands that carry trailing references (lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'rejected_malformed_metadata_stream_length_operand'
Actual: 'reviewed_outline_root_metadata_stream'

1 test files, 20 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects outline item Metadata streams with indirect Length helpers that carry extra operands
PASS rejects outline root Metadata streams with direct Length operands that carry trailing references

1 test files, 107 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataKeyOperandBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS rejects outline Metadata streams followed by malformed key-like operands
PASS keeps rejected key-operand outline Metadata out of navigation and WordPress text
PASS rejects outline root and item Metadata references with trailing operands
PASS keeps tailed outline Metadata operands out of navigation and visible WordPress text
PASS records outline root Metadata streams as review-only document outline metadata
PASS keeps outline root Metadata streams out of navigation rows and visible WordPress text
PASS excludes outline root Metadata streams from lightweight fallback WordPress text
PASS excludes every duplicate outline root Metadata stream from lightweight fallback WordPress text
PASS rejects outline item Metadata streams with indirect Length helpers that carry extra operands
PASS rejects outline root Metadata streams with direct Length operands that carry trailing references
PASS rejects outline item Metadata streams with indirect Filter helpers that carry extra operands
PASS rejects outline item Metadata streams with indirect DecodeParms helpers that carry extra operands
PASS keeps valid outline item Metadata streams review-only after stream operand checks
PASS carries outline root Metadata stream review into navigation metadata without payload text
PASS propagates malformed outline root Metadata operand review into navigation metadata

6 test files, 459 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-length-boundary-currentbase.php
exits 0 with root_length_status=rejected_malformed_metadata_stream_length_operand, item_length_status=rejected_malformed_metadata_stream_length_operand, root_length_boundary_rejected=true, item_length_boundary_rejected=true, metadata payload exclusion true, executes_python_or_models=false, executes_external_pdf_tools=false, and executes_pdf_actions=false.
```

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataStreamLengthOperandBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-length-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-length-boundary-currentbase.php
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted outline destination/action operand boundaries, `/Metadata` reference-tail boundaries, key-like metadata operands, stream `/Filter` or `/DecodeParms` operand checks, duplicate metadata stream type/subtype checks, root metadata navigation propagation, root traversal operand checks, or link/annotation string operand boundaries. The bounded behavior is only metadata stream `/Length` operand trust at outline root/item boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, dictionary token reader, stream decoder preflight, metadata review model, outline extractor navigation review path, and WordPress smoke output path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
