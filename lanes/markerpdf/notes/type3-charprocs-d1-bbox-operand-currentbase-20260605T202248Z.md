# markerPDF Type3 CharProcs d1 bbox operand boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T202248Z`

Accepted base: `9aa35d009f07fabee9a32a57e5e751856e526db5`

## Source Truth

Upstream `sddai/markerPDF` remains pinned in the lane manifest at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the no-GPU markerPDF scope,
searchable PDF text reaches Marker through parser-backed pdftext/PDFium
extraction before OCR/layout/model stages. The native PHP fallback therefore
owns the PDF parser boundary for Type3 glyph programs.

PDF Type3 `d1` is the cache-device width operator and has six numeric operands:
`wx wy llx lly urx ury`. The existing native parser validated only `wx` and
`wy`, so malformed bbox operands after a valid width could still make the
CharProc width authoritative. That could create false WordPress word gaps even
though the glyph program was malformed.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now requires all six
`d1` operands to parse as finite numeric operands before returning the Type3
width vector. Valid `d1` metrics still drive thin glyph spacing; malformed
string or dictionary bbox operands fall back to the declared font widths or
descriptor fallback. CharProc payload text remains excluded from visible
WordPress paragraphs.

## Tests

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php
```

Result before source edit: `1 test files / 1 assertions / 1 failures`.
Malformed string and dictionary bbox operands were accepted as thin `d1`
metrics, producing `Bad BBox` and `Dict Gap`.

Focused after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files / 12 assertions / 0 failures`.

Adjacent focused gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php
```

Result: `4 test files / 38 assertions / 0 failures`.

Type3 family gate:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' \) | sort)
```

Result: `35 test files / 313 assertions / 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-d1-bbox-operand-currentbase.php
```

Result: emitted `valid_d1_bbox_metric_preserved=true`,
`string_bbox_d1_metric_rejected=true`,
`dictionary_bbox_d1_metric_rejected=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
stream decoder, content tokenizer, Type3 CharProc dictionary resolver, finite
numeric operand parser, font-width fallback path, text grouping, and WordPress
smoke renderer. No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR,
GPU/model execution, browser service, or external PDF tool was run.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, generation selection, indirect `/CharProcs`
dictionary selection, top-level `/CharProcs` lookup, nested dictionary parsing,
stream-filter fail-closed behavior, Type3 `FontMatrix` normalization,
extra-operand `d0`/`d1` rejection, marked-content operand/balance boundaries,
pre-metric paint rejection, graphics-state balance, Type3 CMap/CIDSet grouping,
image/filter boundaries, object-stream/xref repair, or OCR/model behavior. The
bounded behavior is specifically validating the four `d1` bbox operands before
WordPress text grouping.
