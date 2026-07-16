# CMap Source Width Notdef Range Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T083457Z`

Base: `3fbce78dff945c4108221de18bd13fb2feb4f8f0`

## Behavior

Native `PdfTextExtractor` now parses Type0 Encoding CMap `beginnotdefchar` and `beginnotdefrange` rows as fallback source-code to CID mappings before descendant CIDFont `/W` widths are applied. Explicit `begincidchar` and `begincidrange` rows still take precedence over notdef fallback rows.

This keeps searchable PDF text extraction aligned with the CMap source-width fallback boundary: WordPress paragraph gaps and styled-span bboxes use the PDF font's CID width data instead of raw source-byte default widths.

## Red/Green Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files / 202 assertions / 1 failure`. The new notdef-range case decoded text correctly, but the first styled span bbox was `[0.0, 0.0, 24.0, 12.0]` instead of `[0.0, 0.0, 48.0, 12.0]`.

Focused run after the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files / 206 assertions / 0 failures`.

Adjacent CMap/font-width family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(CMapSourceWidth|Font.*(Width|CMap|CID|Type0|Type3)).*Test\.php$' | sort)`

Result: `46 test files / 804 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-notdef-source-width-currentbase.php`

Result: emits `notdef_range_cid_widths_applied=true`, `word_gap_preserved=true`, `text_runs_preserved=true`, `raw_source_default_width_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final check: `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This slice stays within native searchable-PDF CMap/font width parsing. It does not touch OCR, Surya/Texify/Torch/model execution, runtime workers, metadata/XMP boundaries, xref repair, annotations, forms, images, page geometry, or table/equation handoff behavior.

## Dependency Closure

No new support component is needed. The existing native PHP PDF tokenizer, CMap parser, and CIDFont width parser are reused.
