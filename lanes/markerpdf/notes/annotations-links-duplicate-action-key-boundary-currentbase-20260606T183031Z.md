# markerPDF annotations links duplicate action-key boundary current-base

Session: `port-dev-markerpdf-annotations-links-20260606T183031Z`
Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T183031Z`
Base accepted HEAD: `8e54b21f9fe69b8e0cb46c644ce6d3d23fb9b9ee`

## Source truth

- Upstream markerPDF routes searchable-PDF link/text extraction through pdftext/PDFium-style native PDF parser boundaries before OCR/model handoff. In this no-GPU PHP lane, Link annotation action dictionaries are parsed locally and must remain non-executing review metadata unless a safe direct primary URI is promoted to a WordPress span.
- PDF dictionaries can contain duplicate keys in malformed or incrementally repaired files. This slice preserves the existing local last-top-level-entry selection policy, matching accepted outline duplicate-key review behavior, while making duplicate Link action keys auditable.

## Implementation

- `PdfActionReviewExtractor` parsed dictionaries now retain duplicate-key counts, selected entry indexes, and a `last_top_level_entry` policy review row.
- `PdfAnnotationExtractor` exposes duplicate annotation action-key review metadata for duplicate top-level `/A`, `/Dest`, `/AA`, or `/PA` dictionary entries.
- `PdfLinkAnnotationExtractor` carries that review metadata onto promoted link rows and supplied pdftext spans, while selected action dictionaries also expose duplicate `/URI` and `/Next` review rows.
- `PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php` covers duplicate `/A`, duplicate `/Dest`, duplicate selected action `/URI`, and duplicate selected action `/Next`. The stale URI, Launch helper, JavaScript, and stale destination operands stay out of annotation/link review payloads and visible WordPress text.
- `wordpress-pdf-link-duplicate-action-key-currentbase.php` emits a WordPress paragraph smoke with current URI promotion plus local destination review metadata.

## Verification

Focused test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS reviews duplicate Link action keys while selecting the last top-level action target

1 test files, 42 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-link-duplicate-action-key-currentbase.php
```

Result summary: emitted `annotation_objects=[7,8]`, `promoted_link_objects=[7,8]`, `primary_link_uri=https://example.com/current-duplicate-action`, `local_destination_page=1`, `annotation_duplicate_action_keys=[A]`, `destination_duplicate_action_keys=[Dest]`, `action_dictionary_duplicate_keys=[URI,Next]`, `stale_duplicate_action_payload_excluded=true`, `annotation_payload_text_visible=false`, and all PDF action, JavaScript, Python/model, and external PDF tool execution flags false.

## Non-overlap

This does not repeat accepted page `/Annots` duplicate-key selection, annotation dictionary comment parsing, escaped annotation names, indirect `/Annots` arrays, indirect action subtype names, URI base resolution, previous URI `/PA`, primary `/A` array/scalar rejection, IsMap review, name-tree Limits, object-stream action dictionaries, generation exactness, link geometry, or outline duplicate-key review. The bounded behavior is duplicate-key review metadata for Link annotation action dictionaries and selected action dictionaries before WordPress link promotion.

## Dependency closure

No new support component is needed. This reuses the native PDF token parser, action review extractor, annotation extractor, link span promoter, supplied pdftext span model, Markdown merge path, and WordPress smoke path. No Python, pdftext, pypdfium/PDFium, Surya/Torch, Texify, OCR/model, Streamlit/FastAPI, JavaScript execution, PDF action execution, or external PDF-tool execution is introduced.
