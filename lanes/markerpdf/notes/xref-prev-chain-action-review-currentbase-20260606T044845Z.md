# markerPDF xref Prev-chain action-review current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T044845Z`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes link annotation review through native PDF parsing before Markdown/WordPress conversion. Under the current no-GPU markerPDF lane rule this slice ports native PDF xref and action-review behavior only; OCR, Surya/Texify/Torch, live models, and external PDF tools remain out of scope.

PDF incremental updates use the latest `startxref` section as the current base and merge older sections through `/Prev`. If the latest xref stream still points an indirect action object at a valid but stale pre-`/Prev` direct-object offset, the action resolver must prefer the same object/generation direct object written in the current update span before promoting a WordPress link.

## Implementation

`PdfActionReviewExtractor` now reads xref-stream entries through a bounded `/Prev` chain and repairs current-update direct rows before selecting action dictionaries. For each latest in-use direct row:

- offsets that point at the same object/generation inside the current update span remain valid;
- offsets that point before the previous xref section, after the current xref stream, to the wrong owner, or to no direct object are repaired when a same object/generation body exists between `/Prev` and the current xref stream;
- previous xref-stream rows are inherited only when the latest section does not own the object number;
- free rows and compressed-object rows keep their existing behavior.

The focused fixture keeps the page and annotation rows valid, but deliberately leaves the latest action-object rows for `/A 8 0 R` and `/AA /E 9 0 R` pointing to their stale previous direct offsets. WordPress link promotion now selects the current URI and current additional-action review row while excluding the stale previous URI/JavaScript action payloads.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs action review xref-stream rows through Prev chain before WordPress link promotion
Values are not identical
Expected: 'https://example.com/current-prev-chain-action'
Actual: 'https://example.com/stale-prev-chain-action'

1 test files, 3 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs action review xref-stream rows through Prev chain before WordPress link promotion

1 test files, 15 assertions, 0 failures
```

Adjacent xref Prev-chain family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfXrefPrevChain.*Test\.php')
Focused test run: 16 selected test files (root lock skipped)
16 test files, 756 assertions, 0 failures
```

Action/link review family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(LinkAnnotation.*|.*Action.*|NamedDestination.*Action.*|JavaScriptActionInspector)Test\.php')
Focused test run: 80 selected test files (root lock skipped)
80 test files, 3810 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-action-review-currentbase.php
```

The smoke emits `current_uri_promoted=true`, `current_additional_action_reviewed=true`, `stale_prev_action_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted text, document metadata, embedded-file, page-review, outline, free-annotation, object-stream carrier, malformed `/Index`, stale explicit page/metadata offset, latest trailer `/Root`, `/Info null`, root-free, unsupported root-row, or hybrid xref table repair paths. The new behavior is specifically `PdfActionReviewExtractor` object selection for indirect action dictionaries when latest xref-stream rows point at stale direct offsets across `/Prev`.

## Dependency closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream decoder, `/Prev` chain parser, Flate stream decoder, action-review resolver, annotation/link extractors, Markdown postprocessor, and WordPress smoke renderer. Full upstream model parity remains intentionally unavailable under the no-GPU markerPDF scope: pdftext/PDFium runtime parity, live OCR, Surya/Texify/Torch, table/equation model workers, Streamlit/FastAPI workers, benchmark downloads, and external rendering/OCR helpers were not run.
