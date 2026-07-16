# markerPDF Text Operator Spacing Bidi Edge

Slice: `text-operator-spacing-bidi-edge-currentbase-20260602T114800Z`
Session: `port-dev-markerpdf-text6pdf-20260602T114800Z`
Base accepted HEAD: `70687b15f678925b21355e5a0eaa81221be0a954`

## Source Truth

Upstream markerPDF text extraction delegates low-level PDF text extraction to
the pdftext dictionary-output path; native PHP parity therefore needs to honor
PDF text-state operators from the source content stream before WordPress
paragraph grouping. PDF `Tw` word spacing applies to source character code
0x20, not to spaces that appear inside a ToUnicode replacement string. This
slice extends the already accepted ToUnicode source-boundary work without
repeating the prior glyph-width fallback behavior.

## Red-First Boundary

Before the change, a one-glyph source string `<01>` mapped through ToUnicode to
`RLI + A B + PDI` was advanced as if the decoded internal space were a source
space. A double-quote text-showing operator with `Tw=18` then over-advanced the
first bidi run, so a following positioned glyph `<02>` mapped to `C` grouped as
`RLI + A B + PDI + C` instead of preserving the expected positioned word gap.

## Native Behavior Added

`PdfTextExtractor` now counts word-spacing source spaces from the operand's
source code segmentation when ToUnicode boundary data is available. The
horizontal and vertical advance paths both use that source-space count for `Tj`,
the double-quote text-showing operator, and `TJ` array text elements. If a font
has no source-boundary data, the extractor keeps the previous decoded-text
fallback.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - No syntax errors detected.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - No syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdf-text-operator-spacing-bidi-boundary.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `1 test files, 530 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-text-operator-spacing-bidi-boundary.php`
  - emitted `source_space_count_used_for_word_spacing=true`
  - emitted `following_positioned_word_gap_preserved=true`
  - emitted `bidi_isolate_controls_preserved=true`
  - emitted `decoded_tounicode_space_preserved=true`
  - emitted `executes_python_or_models=false`
  - emitted `executes_external_pdf_tools=false`
- `php tools/run-tests.php lanes/markerpdf/tests`
  - `64 test files, 3715 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
  - `markerpdf json ok`
- `git diff --check -- lanes/markerpdf`
  - passed with no output

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped semantics: `337 -> 338`
- `phpBehaviorTests`: `489 -> 490`
- Added WordPress smoke:
  `examples/wordpress-pdf-text-operator-spacing-bidi-boundary.php`

## Non-Overlap

This does not repeat accepted CMap source-width fallback, text-state spacing
baselines, TJ array comment parsing, Type0 CMap width segmentation, vertical
CIDFont metrics, text rendering mode visibility, or the prior bidi/surrogate
glyph advance boundary. The new behavior is specifically `Tw` / double-quote
word-spacing source-space counting when a ToUnicode replacement contains
decoded spaces and bidi isolate controls.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF parser,
ToUnicode CMap parser, text-operand source-code segmentation, text-state
operator handling, and WordPress paragraph smoke path. Full upstream
Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium,
Surya/model downloads, and the broader markerPDF runtime stack.
