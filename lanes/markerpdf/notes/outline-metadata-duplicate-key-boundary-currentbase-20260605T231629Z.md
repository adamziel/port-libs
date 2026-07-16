# Outline Metadata Duplicate-Key Boundary Current Base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T231629Z`
Base: `0e5ef6de4af738adb4c175e82b284d04992b9f2e`

## Source Truth

- Upstream markerPDF uses pdftext/pypdfium-provided outline/bookmark metadata as navigation/review context, not page body text.
- Native PHP parser boundary: PDF dictionaries may contain duplicate top-level keys. For outline item `/Metadata`, the current top-level selected entry is review metadata only; nested dictionaries and literal-string decoys must not be counted as declarations or promoted to root XMP/document metadata.

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now receives top-level `/Metadata` declaration provenance.
- The selected metadata stream remains the last top-level `/Metadata` value already used by the dictionary reader.
- Review metadata now records `declared_entry_count`, `duplicate_entries`, and zero-based `selected_entry_index` when outline item `/Metadata` is present.
- Payload bytes remain excluded; only length, hash, dictionary labels, filters, and XMP packet summary are exposed.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php
```

Result before source edit: `1 test files, 34 assertions, 1 failures`; failed on missing `declared_entry_count`.

After source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php
```

Result: `1 test files, 47 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php
```

Result: `1 test files, 28 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*.php
```

Result: `34 test files, 1352 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-duplicate-key-boundary-currentbase.php
```

Result: emitted a WordPress paragraph/navigation preview with `metadata_declared_entry_count=2`, `metadata_selected_entry_index=1`, `metadata_selected_object=9`, payload exclusion true, and no Python/model/external PDF execution.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary parser, stream decoder, outline metadata extractor, and WordPress review example path. GPU/model OCR, Surya, Texify, Torch, pypdfium, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat existing outline root/count/parent/prev/last/xref/stale-object, `/SE`, action-chain, color, scalar-title, or single `/Metadata` stream coverage. The added boundary is specifically duplicate top-level outline item `/Metadata` declaration provenance with nested/literal decoy exclusion.
