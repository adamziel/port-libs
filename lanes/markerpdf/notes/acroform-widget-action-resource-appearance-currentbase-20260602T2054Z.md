# markerPDF AcroForm Widget Action Resource Appearance Current Base

Lane: `markerpdf`
Micro-slice: `acroform-widget-action-resource-appearance-currentbase`
Base accepted HEAD: `80b26cb32f832422d63cc3dde5915233477a3cd7`

## Source Truth

- Upstream Marker is a document-to-Markdown/JSON pipeline that formats forms and uses PDF text extraction before layout/cleanup. Source: `https://github.com/datalab-to/marker`.
- Upstream pdftext exposes structured PDF text and font metadata and can flatten PDF form fields. Source: `https://github.com/datalab-to/pdftext`.
- This native slice maps the PDF object boundary below that upstream pipeline: selected AcroForm widget appearance streams are Form XObjects with `/Resources`, and nested resource XObjects can carry action-looking `/A` and `/AA` dictionaries. WordPress import should review those action dictionaries but must not execute JavaScript, URI, Hide, SubmitForm, ResetForm, or appearance streams.

## Behavior

- `PdfAcroFormExtractor` now records `resource_xobject_reviews` on selected normal/rollover/down widget appearance streams.
- Each appearance resource XObject review includes resource name, object, type/subtype, BBox/Matrix, filters, action rows, action counts/types/objects, and explicit non-execution flags.
- Nested `/A`, `/AA`, and `/Next` action rows are parsed with the existing bounded action walker. Hide targets resolve through the widget-to-field map, so a resource XObject `/AA /D << /S /Hide /T [widget] >>` reports the field name.
- The field `/V` remains authoritative; selected appearance resources are review-only metadata and action payload text is not promoted into visible WordPress paragraphs.

## Non-Overlap

This does not repeat accepted AcroForm widget `/AS` current-value state, normal appearance stream selection, widget action-cycle review, field/widget submit-reset resource review, AcroForm default resource `/DA` font resolution, XFA signature-widget action review, or rich-text/action/default-resource review. The new behavior is specifically action dictionaries nested inside selected widget appearance resource XObjects.

## Verification

Red-first:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetActionResourceAppearanceCurrentBaseTest.php
```

Failed before implementation with missing `resource_xobject_reviews` on the selected appearance row.

After implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetActionResourceAppearanceCurrentBaseTest.php
```

Passed: `1 test files, 60 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetActionResourceAppearanceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php
```

Passed: `5 test files, 344 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-action-resource-appearance-currentbase.php
```

Passed: emitted `resource_xobject_action_count=3`, action types `JavaScript`, `URI`, and `Hide`, field value `Yes`, and all execution flags false.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, AcroForm field/widget extraction, stream filter decoding, resource dictionary parsing, and the existing bounded action walker. Full appearance rendering, JavaScript execution/sandboxing, form submission/reset/import execution, and signature validation remain out of scope and would require separate native renderer/action-sandbox/crypto components with fixtures before activation.
