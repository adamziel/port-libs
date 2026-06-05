# markerpdf font width advance terminal Tc current-base

Session: `port-dev-markerpdf-font-width-advance-20260605T050451Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T050451Z`

Base accepted HEAD: `735fe503b9c6b5bba2b618c4dcf8b897ba1ab080`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is a font-width advance boundary: terminal `Tc` character spacing after the last drawn glyph contributes to the current text position used by a following relative `Td`, but it does not enlarge the styled bbox drawn extent for the prior text run.

## Source Truth

PDF text state character spacing is applied when glyphs are shown and advances the text position after each glyph. Native extraction therefore needs separate notions of cursor advance and drawn glyph extent before WordPress paragraph grouping.

The fixture uses `/Widths [1000 1000 1000 1000]`, `6 Tc`, `<4142> Tj`, then relative `Td` before `<4344> Tj`. With a `42` unit relative move, the true current cursor ends at `36` units and the next text starts at `42`, which is a narrow intra-word gap. With a `48` unit move, the gap is large enough to preserve a space.

## Native Behavior Added

`PdfTextExtractor` now lets text-position advance helpers include terminal character spacing when updating current text position or decoding `TJ` gap state. Existing bbox/visible extent callers keep the default behavior that excludes terminal character spacing, so styled review geometry remains based on drawn glyph extents.

Plain-text line grouping now tracks cursor and drawn endpoints separately. Relative `Td` gap checks use the cursor endpoint that includes terminal `Tc`, while absolute `Tm` gap checks keep using the drawn endpoint so scoped `q/Q` character spacing does not collapse later visible gaps.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Before the source fix:

```text
FAIL keeps terminal character spacing in current text advance before relative Td gaps on current base
Expected: ['ABCD', 'AB CD']
Actual:   ['AB CD', 'AB CD']
1 test files, 170 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result after implementation:

```text
1 test files, 181 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
```

Result:

```text
6 test files, 970 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: the smoke emits `terminal_tc_uses_cursor_advance_for_td_gap=true`, `terminal_tc_larger_gap_still_preserved=true`, `terminal_tc_drawn_bbox_excludes_terminal_spacing=true`, `terminal_tc_false_gap_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
git diff --check -- lanes/markerpdf
```

Result: PHP lint reported no syntax errors for changed PHP files; both JSON files validated; `git diff --check -- lanes/markerpdf` reported no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1445 -> 1446`
- `wordpressScenarios`: `1366 -> 1367`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `15 PASS / 169 assertions -> 16 PASS / 181 assertions`
- Focused PASS case delta: `+1`

## Dependency Closure

No new support component is needed. The slice reuses the existing native content-stream text state, font width, text matrix, and styled-span extraction paths.

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, relative/scaled `Td`, text matrix vertical scaling, negative text matrix extent, text rise, `TJ` backtracking, unresolved width slots, text object reset, rotated/sheared text matrix bboxes, `/LastChar` width clipping, vertical Type0 `/W2` bboxes, CMap source-width fallback, Type3 CharProc widths, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight.

The new boundary is specifically terminal character spacing in current text-position advance before relative `Td` gap decisions while preserving drawn styled-span bbox extents.

## Next Task

Continue with native no-GPU searchable-PDF font/CMap boundary work, especially parser-level text-state interactions that affect WordPress paragraph grouping without launching model or raster backends.
