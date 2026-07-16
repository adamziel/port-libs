# markerPDF named destinations indirect string alias boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T091259Z`
Session: `port-dev-markerpdf-named-destinations-20260608T091259Z`
Base accepted HEAD: `2f590527006a7bf856cee7965f504347f4283ae7`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable-PDF navigation extraction to pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this patch maps a native PDF name-tree boundary for catalog `/Names /Dests`: a name-tree value may be an indirect text string alias to another named destination. If that indirect string value is followed by a malformed non-string operand, the parser must preserve the alias value instead of reinterpreting the resolved string as a missing destination key.

## Behavior

- `PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, `PdfActionReviewExtractor`, and `PdfOutlineExtractor` now distinguish direct string tokens from indirect string values when resynchronizing malformed `/Names` arrays.
- Direct string key repair still works for accepted missing-value cases such as `(Missing Value Target) (Recovered Target) [page /XYZ ...]`.
- Indirect string alias values such as `(Indirect Alias) 12 0 R /StrayOperand` now preserve `Indirect Alias -> Actual Target`, while `/StrayOperand` remains unresolved and review-excluded.
- WordPress link promotion keeps indirect/direct alias annotations as local destinations resolving to the target view, keeps the malformed stray annotation unpromoted, and keeps destination/URI metadata out of visible PDF text.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringAliasBoundaryCurrentBaseTest.php
FAIL preserves indirect string alias values before malformed name-tree operands in WordPress destination metadata
Expected names: Actual Target, Indirect Alias, Direct Alias, LegacyTail
Actual names: Actual Target, LegacyTail
FAIL keeps malformed stray operands out of link promotion and visible WordPress text after indirect aliases
Expected local-destination actions for annotations 7 and 8; actual actions were empty.
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringAliasBoundaryCurrentBaseTest.php
PASS preserves indirect string alias values before malformed name-tree operands in WordPress destination metadata
PASS keeps malformed stray operands out of link promotion and visible WordPress text after indirect aliases
1 test files, 45 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionMapBoundaryCurrentBaseTest.php
60 test files, 1922 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-string-alias-currentbase.php
Exits 0 and emits destination_names=["Actual Target","Indirect Alias","Direct Alias","LegacyTail"], promoted_link_objects=[7,8,10], resolved_link_targets=["Actual Target","Actual Target"], stray_operand_promoted=false, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, executes_external_pdf_tools=false, and executes_pdf_actions=false.
```

## Delta

- Added `2` focused PHP PASS cases and `45` focused assertions.
- Added `1` WordPress smoke/example for native named-destination import review.
- `phpPass`: `3011 -> 3013`
- `wordpressScenarios`: `2493 -> 2494`

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed root/intermediate `/Limits`, child `/Kids` ordering, sparse direct string-key recovery, direct PDF-name key rejection, direct string/name/action aliases, alias cycles, page operand validation, view-mode validation, PDFDocEncoding byte limits, duplicate key handling, generation-exact bodies, object-stream/xref/trailer-root repair, outline/link action context, or PageLabels behavior. The bounded behavior is only indirect PDF string alias values in `/Names /Dests` leaf arrays immediately followed by malformed non-string operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, raw and parsed name-tree walkers, generation-aware object resolver, destination normalizer, metadata extractor, action-review map, outline extractor, link span promotion, and WordPress smoke renderer. Full upstream model/PDFium parity, live OCR, Surya/Texify/Torch, raster execution, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
