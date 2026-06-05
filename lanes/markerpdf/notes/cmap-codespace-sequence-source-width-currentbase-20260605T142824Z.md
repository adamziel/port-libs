# markerPDF CMap code-space sequence source-width fallback

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T142824Z`

Accepted base: `bf75562f447c1c8f603ede7bf5edd88ff3917f71`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This slice remains inside the native no-GPU searchable-PDF parser scope: CMap parsing, Type0 CID source widths, text grouping, and WordPress paragraph safety only.

Adobe Technical Note #5099 describes CMap code-space ranges as the valid input code space and `begincidrange` rows as range mappings equivalent to consecutive single character-code-to-CID mappings. This patch applies that boundary to multi-byte CMap ranges before descendant CIDFont `/W` widths are used.

Source links:

- https://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5099.CMapResources.pdf
- https://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5014.CIDFont_Spec.pdf

## Behavior

`PdfTextExtractor::parseCidRanges()` now receives the combined same-width CMap code-space ranges and advances CID offsets only across source codes valid for that code space. The fallback still preserves the previous numeric behavior when no same-width code-space range exists, which keeps malformed broad-code-space recovery tests intact.

The focused PDF uses:

- `begincodespacerange <3030> <3232>`;
- `begincidrange <3030> <3232> 100`;
- CIDFont widths `/W [100 102 1000 106 108 250]`;
- content operands `<303030313032>` and `<323032313232>`.

Before the fix, `<3230>` used raw numeric distance from `<3030>` and fell back to `/DW`, producing the second span bbox `[36,0,72,12]`. After the fix, only valid code-space source codes are counted, so `<3230>` maps to CID 106 and the second span bbox is `[36,0,45,12]`.

## Evidence

Red-first focused run after adding the test and before parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL uses code-space sequence order for multi-byte CID ranges before source-width fallback on current base
Expected second span bbox [36.0, 0.0, 45.0, 12.0]
Actual second span bbox [36.0, 0.0, 72.0, 12.0]
1 test files, 263 assertions, 1 failures
```

Focused run after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 266 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-sequence-source-width-currentbase.php
```

The smoke emits `codespace_sequence_cid_widths_applied=true`, `numeric_gap_default_width_excluded=true`, `text_lines_preserved=true`, `text_runs_preserved=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, CIDFont `/DW` fallback, ToUnicode metric-miss fallback, partial metric-miss repair, horizontal/vertical `TJ` gaps, odd hex padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, valid/late `usecmap` boundaries, high CID range expansion, notdef range/char fallback, broad-codespace precedence fixes, or Type3 CharProcs fallback boundaries.

The bounded behavior is specifically multi-byte CMap `begincidrange` CID offsets counted over valid code-space source sequences before source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping path, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
