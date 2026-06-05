# markerPDF xref Prev-chain inherited Root free row current-base slice

Session: `port-dev-markerpdf-xref-prev-chain-20260605T082149Z`

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T082149Z`

Accepted base: `047062ffae599f2aed5868dc8e085f869923184a`

## Source truth

Native no-GPU PDF parser scope only. This slice follows PDF incremental-update xref authority: a later xref section owns rows it lists, and a type-0 row for an inherited trailer `/Root` object marks that object free. When the latest xref stream omits `/Root` but frees the previously inherited catalog object, markerPDF must not recover previous page-tree/content streams through rootless fallback scanning.

This is intentionally distinct from:

- `xref-prev-chain-latest-free-rows-currentbase-20260605T070501Z.md`, which keeps a current catalog live while freeing previous metadata/name-tree objects.
- `xref-prev-chain-sparse-latest-info-currentbase-20260605T033057Z.md`, which preserves valid trailer `/Info` inheritance when the latest trailer omits `/Info`.
- `xref-trailer-encrypt-prev-currentbase-20260602T1919Z.md`, which covers `/Encrypt` inheritance and `/Encrypt null`.

## Implementation

- `PdfTextExtractor` now detects the latest xref section at `startxref`, checks whether it omits a top-level `/Root`, resolves the inherited `/Root` through `/Prev`, and treats a current type-0 row for that inherited catalog object as an authoritative root clear.
- Root-cleared files now block both page-tree fallback and raw content-stream fallback, preventing stale previous-section text from being imported without a live catalog.
- The new fixture keeps previous page, XMP, `/Info`, and EmbeddedFiles objects available in the old xref table, then appends a latest xref stream with `/Prev`, `/Index [1 1]`, no `/Root`, and a free row for object `1 0`. Trailer `/Info` remains inherited separately and is asserted as `['info']`.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL blocks previous trailer root fallback when latest xref-stream frees inherited catalog
Expected: array (
)
Actual: array (
  0 => 'Stale inherited root free row page',
)
1 test files, 306 assertions, 1 failures
```

Focused run after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 322 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-free-currentbase.php
paragraphs_imported=0
stale_page_excluded=true
stale_xmp_excluded=true
stale_attachment_excluded=true
info_source_carried=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency closure

No new support component is needed. The slice reuses native PHP xref-stream decoding, trailer dictionary parsing, free-row ownership, metadata extraction, and embedded-file extraction. No OCR, Surya, Texify, Torch, Streamlit/FastAPI worker, GPU/model runner, external PDF tool, or online service was used.

## Next task

Continue with non-overlapping native PDF parser behavior: fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
