# pdftext dictionary layout/order direct-key marker conflict current-base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T120911Z`
Base: `dd4c8f52a083993fe65f949b8efa73cb4fa61848`
Lane: `markerpdf`

## Source truth

- Upstream `marker/pdf/extract_text.py` at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` calls `pdftext.extraction.dictionary_output(fname, page_range=page_range, ...)` after resolving the selected `start_page`/`max_pages`, so supplied native dictionaries need selected-page alignment before conversion.
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker/layout/order.py` zips selected `pages` with `order_results` and then sorts blocks by the assigned page order bboxes. A stale skipped-page sidecar should therefore fail page identity selection before it can reorder the selected page.
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented behavior

Native source-page keyed layout/order sidecar maps now preserve the object-map
key as selector-only page identity even when the payload also carries page
metadata such as `selected_page_index`. The map key and inner marker must both
agree with the selected pdftext dictionary page; otherwise the artifact is
excluded before layout/order assignment.

This prevents stale skipped-page artifacts from:

- reordering selected pdftext dictionary blocks through a matching inner
  `selected_page_index`;
- adding `layout`/`order` supplied-boundary metadata during WordPress import;
- leaking stale sidecar payload text or internal selector markers downstream.

## Red-first evidence

Before the selector fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMarkerConflictBoundaryCurrentBaseTest.php`

failed with:

- selected-page block order reversed by the stale keyed order artifact;
- WordPress converter metadata reporting `supplied_boundaries` as
  `['layout', 'order']` from stale keyed artifacts.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMarkerConflictBoundaryCurrentBaseTest.php`
  - `1 test files, 28 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfTextDictionaryLayoutOrder.*CurrentBaseTest\\.php$' | sort)`
  - `14 test files, 1318 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-direct-key-marker-conflict-currentbase.php`
  - exits `0`; reports `supplied_artifacts_excluded=true`,
    `stale_payload_excluded=true`, and
    `source_order_preserved_without_matching_order=true`

## Dependency closure

No new support component is required. This reuses the existing native PHP
pdftext dictionary, supplied-boundary artifact selector, layout annotator,
layout orderer, and WordPress supplied-document converter paths. GPU OCR,
Surya/Torch execution, raster rendering, live model workers, and external PDF
tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-overlap

This patch does not repeat accepted supplied-range slicing, direct source-key
map assignment, singleton key mismatch, outer metadata direct-payload key
conflict, duplicate page-key, JSON artifact, or page-id/page-map behavior. It
only adds the missing conflict boundary where a direct source-keyed artifact has
a stale object-map key and an inner selected-page marker that otherwise matches
the trimmed page.
