# CMap Encoding Reference Tail Source Width Fallback Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T093313Z`
Base: `4dbf82d1f44792f3016d59fe05bb989279c7a2f9`
Date: 2026-06-08 UTC

## Behavior

Type0 font `/Encoding` values that resolve through an indirect reference must not
carry trailing top-level operands before CID CMap source-width fallback. A
malformed value such as `/Encoding 3 0 R 9` now fails closed for the CID CMap
encoding map, so WordPress/searchable text extraction keeps using ToUnicode,
raw source bytes, and default source widths instead of decoy CMap CID widths.

This preserves visible text and span geometry for valid ToUnicode mappings while
preventing false word gaps from malformed CMap dictionaries. The fixture keeps a
decoy Encoding CMap that would split `JoinSafe` into `Join Safe` if accepted.

## Red-First Evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapEncodingReferenceTailSourceWidthCurrentBaseTest.php`

Result:

`1 test files, 1 assertions, 1 failures`

The failed assertion extracted `Join Safe` instead of `JoinSafe`.

## Passing Evidence

After the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapEncodingReferenceTailSourceWidthCurrentBaseTest.php`

Result:

`1 test files, 11 assertions, 0 failures`

Adjacent CMap/font width family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(CMap|Font).*Width.*CurrentBaseTest\.php$' | sort)`

Result:

`61 test files, 1912 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-encoding-reference-tail-source-width-currentbase.php`

Result: exits 0 with `malformed_encoding_reference_tail_rejected=true`,
`false_word_gap_excluded=true`, `safe_tounicode_text_preserved=true`,
`decoy_cmap_widths_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`,
`nul_bytes_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: 3019 to 3020
- `wordpressScenarios`: 2498 to 2499
- Focused PASS cases added: 1
- Focused assertions added: 11

## Non-Overlap

This does not repeat valid direct/indirect Encoding CMap handling, zero-padded
source-width fallback, UseCMap inheritance, declared-count handling, malformed
CID range rows, simple-font Widths, Type3 CharProc widths, W2 vertical metrics,
or the previous indirect associated-file array boundary slice. It only gates a
malformed Type0 `/Encoding` reference tail before CID CMap width lookup.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
PDF text dictionary/CMap parser and the existing top-level operand boundary
helper. No Python, GPU, OCR, model worker, raster renderer, external PDF tool,
or live service is required.
