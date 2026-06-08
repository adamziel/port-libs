# markerpdf xref Prev chain damaged Prev decoy-section current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T161523Z`

Accepted base: `6d62356c079257585a6b72bfc581ed5e99b83966`

## Source truth

- Upstream markerPDF delegates searchable-PDF object and text recovery to PDF parser dependencies before any OCR/model work. In the native no-GPU PHP lane, xref-chain repair must therefore select the correct catalog/page/content, metadata, and EmbeddedFiles graph before Gutenberg paragraph import.
- In incremental PDFs, `/Prev` is a byte offset to the previous xref section. When that offset is slightly damaged but still points into the real earlier xref section, repair should stay near the declared-offset neighborhood. Falling back to the latest valid xref before the current section can incorrectly select an unrelated unlinked decoy xref section appended between the real base and the current update.

## Behavior

The shared damaged backward `/Prev` fallback now:

- keeps exact valid `/Prev` offsets unchanged;
- keeps self/forward `/Prev` repair behavior unchanged by falling back to the latest valid section before the current section;
- for invalid backward `/Prev` offsets, first repairs to the latest valid xref section before the declared damaged offset, and only then falls back to the older broad repair.

The focused fixture contains a real base xref table, an unlinked later decoy xref table with its own Root/Info/EmbeddedFiles graph, and a current xref stream whose `/Prev` points inside the base xref body while omitting `/Root`. Text, XMP, Info, catalog language, and attachment extraction now inherit the real base Root and apply current rows, excluding both previous and decoy graphs.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDamagedPrevDecoySectionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs damaged Prev to its declared-offset xref neighborhood before unlinked decoy sections
Expected: ['Current damaged Prev decoy-section page', 'Declared Prev neighborhood repaired']
Actual: ['Unlinked decoy xref-section page']
1 test files, 4 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDamagedPrevDecoySectionCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs damaged Prev to its declared-offset xref neighborhood before unlinked decoy sections

1 test files, 32 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfXrefPrevChain|PdfPageReviewXrefPrevChain|MarkerAppPreviewXrefPrevChain|PdfOutlineXrefStreamPrevChain' | sort)
Focused test run: 33 selected test files (root lock skipped)
...
33 test files, 1334 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-damaged-prev-decoy-section-currentbase.php --self-test
```

The smoke exits 0 and reports `current_text_selected=true`, `current_xmp_selected=true`, `current_info_selected=true`, `current_catalog_language_selected=true`, `current_attachment_selected=true`, `attachment_preflight_selected=true`, `decoy_section_excluded=true`, `previous_section_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted duplicate `/Prev`, indirect/compressed `/Prev` helpers, forward `/Prev` repair, damaged middle `/Prev` repair with direct-object decoys, omitted current rows, duplicate free rows, root-null/root-free boundaries, xref-stream `/W` or `/Index` helper resolution, object-stream carrier repair, or CMap/filter slices. The bounded behavior is specifically invalid backward `/Prev` repair in the presence of an unlinked valid xref section after the declared damaged offset.

## Dependency closure

No new support component is needed. The slice reuses native PHP object scanning, xref table/stream decoding, trailer inheritance, metadata extraction, EmbeddedFiles extraction, free-object mapping, action-review xref selection, and the WordPress smoke renderer. OCR, Surya/Texify/Torch, PDFium, Python model execution, raster rendering, live services, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
