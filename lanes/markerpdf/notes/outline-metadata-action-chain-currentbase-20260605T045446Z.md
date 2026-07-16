# markerPDF Outline Metadata Action Chain Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T045446Z`

Base accepted HEAD: `48fd42b5dca68647d1ddff43b51b8403b4c5825c`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable page text through `pdftext.dictionary_output(...)` and returns TOC metadata separately from page blocks in `marker/pdf/extract_text.py`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc()` projects PDF engine TOC rows as title, level, and page metadata. The native PHP no-GPU boundary therefore keeps outline/action structures as review metadata, not visible WordPress text.
- PDF outline action dictionaries may carry `/Next` action dictionaries or arrays. For WordPress import review, action-chain existence and types are useful metadata, but URI, file, JavaScript, and launch payload strings must not be promoted into document metadata or paragraphs.

## Implementation

- `PdfMetadataExtractor::document_outline.items[*]` now includes a payload-free summary for outline `/A /Next` chains:
  - `action_review_only`
  - `action_payload_included`
  - `executes_action`
  - `action_chain_count`
  - `action_chain_types`
  - `action_chain_objects`
  - `action_chain_has_next`
  - `action_chain_has_javascript`
  - `action_chain_has_launch`
- The chain walker handles direct/indirect actions, arrays, duplicate references, and cycles with a bounded depth guard.
- Action payload strings remain excluded from `document_outline` while the richer `PdfOutlineExtractor::getNavigationReviewMetadata()` path still carries action-review operands for dedicated review UIs.

## Evidence

Red-first focused run after adding the regression, before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionChainBoundaryCurrentBaseTest.php
=> 1 test files / 19 assertions / 2 failures
```

Failure: document-outline rows lacked `action_review_only` and `action_chain_count`.

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataActionChainBoundaryCurrentBaseTest.php
=> 1 test files / 40 assertions / 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfOutlineMetadata.*CurrentBaseTest\.php' | sort)
=> 14 test files / 607 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-action-chain-metadata-currentbase.php
=> action_chain_count=4; action_chain_types=[GoTo,URI,JavaScript,Launch]; metadata_payload_excluded=true; visible_text_excludes_outline_action_metadata=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

## Status Delta

- Added 1 focused PHP behavior test file.
- Added 1 WordPress smoke/example.
- `lane-status.json` `phpPass` moves `1435 -> 1436`.
- `UPSTREAM_TEST_MANIFEST.json` mapped behavior count moves `722 -> 723`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, dictionary/array readers, indirect reference resolver, document outline metadata extractor, navigation review extractor, and WordPress smoke path. No GPU, OCR, PDFium, Surya/Torch, Texify, Python model worker, live Streamlit/FastAPI service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted outline metadata color preservation, required-title gating, `/Parent`/`/Prev`/`/Last` traversal guards, generation-exact outline references, xref-owner/EOF/trailer-root boundaries, structure-element propagation, destination view normalization, PageLabels/transition/thread target enrichment, or the richer full action-review rows in `PdfOutlineExtractor`. The bounded behavior is only the payload-free `/A /Next` action-chain summary on document-level `PdfMetadataExtractor::document_outline` rows.
