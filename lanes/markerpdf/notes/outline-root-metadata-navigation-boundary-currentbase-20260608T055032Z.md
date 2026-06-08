# markerPDF outline root metadata navigation boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T055032Z`

Accepted base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Source truth

- Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable-PDF outline/navigation and document metadata through parser surfaces before downstream conversion. Under the current no-GPU scope, the PHP lane owns native parser review metadata and must keep XMP/outline payloads separate from visible WordPress text.
- PDF outline root dictionaries may carry `/Metadata` streams. The existing native metadata extractor already treats root outline metadata as review-only and rejects malformed top-level operands. This slice propagates that already-redacted root review summary into the composite navigation payload used by WordPress imports.

## Behavior

`PdfOutlineExtractor::getNavigationReviewMetadata()` now emits `outline_root_review` when `PdfMetadataExtractor` found a root `/Outlines /Metadata` review summary. The bridge copies only structural root fields and the existing `metadata_stream_review`; it does not decode new payloads, promote XMP text, or change outline item traversal.

The new focused test covers:

- a valid root outline `/Metadata` stream whose hash and byte count are review metadata only;
- a malformed root outline `/Metadata 8 0 R 10 0 R` operand boundary whose trailing reference is recorded while both root and trailing XMP payloads remain hidden.

## Red first

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries outline root Metadata stream review into navigation metadata without payload text (lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php)
Condition is not true
FAIL propagates malformed outline root Metadata operand review into navigation metadata (lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php)
Condition is not true

1 test files, 11 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRootMetadataNavigationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries outline root Metadata stream review into navigation metadata without payload text
PASS propagates malformed outline root Metadata operand review into navigation metadata

1 test files, 63 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-metadata-navigation-currentbase.php
```

Passed with `root_metadata_status=rejected_malformed_outline_root_metadata_operand`, `metadata_operand_count=2`, `trailing_reference_object_numbers=[10]`, `visible_text_excludes_outline_root_metadata=true`, `payload_included=false`, `visible_text_source=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline item metadata propagation, root metadata stream review in `PdfMetadataExtractor`, tailed item/root `/Metadata` operand rejection, outline action review, destination aliases, title/style/color metadata, xref/trailer-root selection, PageLabels, AcroForm, annotations, attachments, encryption, image filters, CMaps, OCR, model, or external PDF execution. The bounded behavior is only root outline `/Metadata` review propagation into composite navigation metadata.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, metadata extractor, outline extractor, stream-filter decoder, navigation review payload, and WordPress smoke path. Full upstream markerPDF model/OCR/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.

Root harness: not run - isolated micro-slice.
