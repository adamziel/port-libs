# Type3 CharProcs Glyph Array Value Boundary

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T152832Z`
Base: `d0b4b38f59138165173e2184c28cc1c5296bac2f`

## Behavior

This isolated markerPDF patch stays inside the native no-GPU searchable-PDF scope. It covers Type 3 `/CharProcs` dictionaries where a glyph entry value is array-wrapped, for example `/B [3 0 R]`.

PDF Type 3 `/CharProcs` entries are glyph program streams keyed by glyph name. The native fallback already rejects array-wrapped top-level `/CharProcs` dictionaries and malformed glyph-entry tails. This slice adds the missing per-glyph array-value boundary:

- array-valued glyph entries are rejected before Type 3 `d0`/`d1` metrics, so invalid glyph syntax cannot drive text grouping;
- `/Widths` fallback remains available for visible text grouping;
- stream references nested inside the invalid glyph array are still treated as Type 3 font-private payloads during fallback stream scanning, so glyph program text is not promoted to WordPress paragraphs.

## Red-First Evidence

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphArrayValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects array-valued Type3 CharProc glyph entries before WordPress text grouping on current base
Expected: array (0 => 'Bad Path')
Actual: array (0 => 'Aad Path')
FAIL keeps array-valued Type3 CharProc glyph streams private during fallback extraction on current base
Expected: array (0 => 'Visible fallback content')
Actual: array (0 => 'ARRAY WRAPPED GLYPH VALUE LEAK', 1 => 'Visible fallback content')
1 test files, 2 assertions, 2 failures
```

## Implementation

`PdfTextExtractor::charProcObjectReferencesFromDictionary()` now treats array-valued glyph entries as malformed for the strict Type 3 metrics path. In the fallback-exclusion path it recursively collects indirect references from array and dictionary values so malformed glyph wrappers still mark referenced glyph streams as private.

The fail-closed metrics rule is intentionally narrowed to arrays. Existing nested dictionary decoys such as `/Private << ... >>` remain ignored for metrics instead of invalidating the whole `/CharProcs` dictionary.

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphArrayValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects array-valued Type3 CharProc glyph entries before WordPress text grouping on current base
PASS keeps array-valued Type3 CharProc glyph streams private during fallback extraction on current base
1 test files, 14 assertions, 0 failures
```

Adjacent Type 3 family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProcs*Test.php' -o -name 'PdfFontType3CharProc*Test.php' -o -name 'PdfFontSimpleType3*Test.php' -o -name 'PdfFontCMapCidType3*Test.php' -o -name 'PdfImageXObjectType3CharProc*Test.php' -o -name 'PdfPageResourceDuplicateType3FontCurrentBaseTest.php' \) | sort)
Focused test run: 70 selected test files (root lock skipped)
70 test files, 851 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-glyph-array-value-currentbase.php
```

The smoke exits 0 and emits review flags:

- `glyph_array_value_rejected=true`
- `fallback_widths_preserve_word_gap=true`
- `page_charproc_payload_excluded=true`
- `fallback_content_preserved=true`
- `array_wrapped_glyph_stream_excluded=true`
- `valid_glyph_stream_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type 3 coverage for top-level array-wrapped `/CharProcs`, glyph-entry tail operands, duplicate glyph tail replacement, nested dictionary decoys, indirect dictionary generation, dictionary streams, non-stream glyph objects, FontMatrix width vectors, `d0`/`d1` duplicate metrics, resource fallback, image review, CMap CID mapping, or Type3/ToUnicode glyph-name fallback. The new boundary is specifically array-valued glyph entries inside an otherwise direct `/CharProcs` dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, object scanner, Type 3 `/CharProcs` parser, font-width fallback path, fallback stream privacy exclusion, and WordPress smoke renderer. OCR, Surya/Texify/Torch, model workers, PDFium rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
