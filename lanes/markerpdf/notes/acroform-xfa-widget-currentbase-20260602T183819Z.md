## AcroForm XFA Widget Current-Base

Slice: `acroform-xfa-widget-currentbase`
Session: `port-dev-markerpdf-form36pdf-20260602T1829Z`
Base: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

### Source Truth

- Upstream markerPDF source truth remains the pinned `sddai/markerPDF` inventory in `UPSTREAM_TEST_MANIFEST.json`: marker/pdf extraction treats PDF/XFA/form payloads as conversion inputs and review metadata, not as executable JavaScript, signing, rendering, or Python/model work in this native PHP lane.
- PDF parser source truth: `/XFA` is stored on the AcroForm dictionary as an XDP packet/string/stream, while AcroForm field `/V`, `/DV`, widget `/AS`, and widget `/AP` remain the PDF-side current/default/appearance state. This slice maps that boundary into native PHP review rows.

### Behavior

- `PdfAcroFormExtractor` now records XFA dataset leaf values as bounded `data_path_values` rows with preview, byte length, and SHA-256.
- Fields with XFA packets now receive `xfa_widget_review` metadata summarizing matched XFA template/data paths, current/default AcroForm state, widget appearance state, selected appearance objects, checked button export values, and non-execution flags.
- XFA dataset values never replace AcroForm `/V`, `/DV`, widget `/AS`, or visible WordPress text.

### Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php
FAIL ... Undefined array key "xfa_widget_review" ... Undefined array key "data_path_values"
1 test files, 5 assertions, 1 failures
```

Passing:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php
1 test files, 59 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
9 test files, 1168 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-widget-currentbase.php
passed; emitted 1584 bytes of WordPress review markup
```

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormXfaWidgetCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-xfa-widget-currentbase.php
all reported no syntax errors
```

```text
php -r '... JSON_THROW_ON_ERROR ...' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
both markerPDF JSON files decoded successfully
```

```text
git diff --check -- lanes/markerpdf
passed
```

### Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object parser, AcroForm field traversal, XFA packet stream decoding, widget appearance review, and native PDF text extractor. It does not execute Python, pdftext, pypdfium, XFA JavaScript, form actions, signing, model workers, or external PDF tools.

### Non-Overlap

This does not repeat accepted XFA signature widget state/action review, rollover/down appearance review, rich-text widget resource/action review, AcroForm current/default value-state review, or parser stream-owner work. The added behavior is ordinary field/widget current-base review that correlates XFA dataset values with AcroForm `/V`, `/DV`, and widget `/AS` without letting XFA become the import state.
