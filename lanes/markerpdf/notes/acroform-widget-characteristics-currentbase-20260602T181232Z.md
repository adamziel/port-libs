# markerPDF AcroForm Widget Appearance Characteristics Review

Session: `port-dev-markerpdf-form35pdf-20260602T181232Z`
Micro-slice: `form-field-action-appearance-widget-currentbase-20260602T181232Z`
Base accepted HEAD: `babe129c590f2b2bc17296e92e8321e009789290`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates PDF text extraction and page rendering to `pdftext` and pypdfium boundaries; this lane mirrors that by preserving widget appearance dictionaries as review metadata instead of rendering widget captions/icons or treating appearance text as imported WordPress content.
- PDF AcroForm widget annotations can carry `/AP`, `/AS`, `/H`, `/A`, `/AA`, and `/MK` dictionaries. `/MK` appearance characteristics such as `/R`, `/BC`, `/BG`, `/CA`, `/RC`, `/AC`, `/TP`, `/I`, `/RI`, `/IX`, and `/IF` are non-executing review metadata for this native PHP port.

## Implemented

- `PdfAcroFormExtractor` now exposes widget `highlight_mode`, `highlight_mode_label`, and `appearance_characteristics` rows for page-referenced and detached AcroForm widgets.
- The `/MK` review row resolves direct and indirect dictionaries and preserves rotation, border/background colors, normal/rollover/alternate captions, text-position labels, icon object references, rollover/alternate icon references, and icon-fit scale/position/bounds metadata.
- The extractor records explicit false flags for appearance-value import, caption import, icon payload text exposure, appearance rendering, and action execution. Field `/V` remains authoritative for WordPress import text.
- The icon-fit parser uses dictionary-entry lookup so `/IF /A` array positions do not get shadowed by widget action `/A` dictionaries or other name operands in the widget body.

## Red-First Evidence

- Before the source change, the new fixture had no `appearance_characteristics` row in `PdfAcroFormExtractor::extractFields()` widget output, so `/MK` captions, colors, icons, and icon-fit metadata could not be asserted for AcroForm fields.
- During implementation, the focused test also caught the `/IF /SW /A [x y]` parsing ambiguity; the final extractor uses dictionary-entry parsing for icon-fit rows.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCharacteristicsCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-characteristics-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceCharacteristicsCurrentBaseTest.php` passed: `1 test files, 73 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php` passed: `9 test files, 1182 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-characteristics-currentbase.php` passed and emitted `source=native-pdf-acroform-widget-characteristics-review`, `highlight_mode_label=push`, `normal_caption=Approve import`, `icon_fit.scale_when=A`, `detached_widget_reviewed=true`, and all render/import/execution flags false.

## Status Delta

- Behavior tests move `633 -> 634`.
- Mapped markerPDF/PDF semantics move `461 -> 462 / 78`.
- New mapped inventory key: `mappedPdfAcroFormWidgetAppearanceCharacteristicsReviewBehaviors`.

## Non-Overlap

This does not repeat accepted `PdfAnnotationExtractor` page-widget `/MK` review, AcroForm selected `/AP` normal/rollover/down appearance review, AcroForm field/widget action chains, widget rich-text default-style/resource review, calculation-order widget appearances, signature/XFA widget review, or XFA-backed appearance-state review. The bounded behavior is AcroForm field widget `/MK` appearance-characteristics extraction in `PdfAcroFormExtractor`, including detached widgets that are not page annotations.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary resolver, AcroForm field/widget traversal, stream/text extraction boundaries, and existing non-executing action review. Full upstream markerPDF runner parity remains blocked by Python/pdftext/pypdfium/Surya/Texify/model and external OCR/rendering/runtime dependencies.
