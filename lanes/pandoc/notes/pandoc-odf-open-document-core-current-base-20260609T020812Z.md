# Pandoc ODF OpenDocument Core Current Base

Micro-slice: `pandoc-odf-open-document-core-current-base-20260609T020812Z`
Base accepted HEAD: `ae05f994f04ccc78db62e7bd6dd42669f76246b1`

## Behavior

- Added bounded ODF/OpenDocument support for `style:text-properties` under `text:list-level-style-number`, `text:list-level-style-bullet`, and `text:list-level-style-image`.
- Reuses the existing ODF text-property and font-face parser so list marker metadata carries `fontName`, `fontFace`, `fontPitch`, `fixedPitch`, bold, italic, small-caps, underline, and strikeout fields.
- Carries parsed marker metadata onto list AST nodes as `listTextProperties`, reports `listTextPropertyCount`, and renders review-safe `data-odf-list-text-*` attributes through the existing WordPress list attribute path.
- Updated the WordPress ODF handoff smoke so image bullets now also prove list marker text-style metadata.

## Evidence

Red-first check after adding the focused test but before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2718 assertions, 1 failures
FAIL preserves ODT list level text properties for WordPress marker review
Expected: 'ListMono'
Actual: NULL
```

Baseline focused ODF reader evidence before adding the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2716 assertions, 0 failures
```

Final focused evidence:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php

php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2756 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php
1 test files, 103 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok
```

Expected lane movement:

- `phpPass`: `2124` -> `2125`
- `benchmarkDenominator.mapped`: `2551` -> `2552`
- `mappedOdfOpenDocumentCoreCases`: `13` -> `14`
- `odfOpenDocumentCoreAssertions`: `295` -> `335`
- Focused `OdfReaderTest.php`: `2716` -> `2756` assertions

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP ODF XML parsing, font-face declarations, text-property parsing, AST attributes, and WordPress block attribute passthrough. No package runner, office converter, external template engine, ZIP/unzip command, TeX/PDF engine, or online service is required.

## Non-Overlap

This does not repeat the accepted ODF image list-style metadata, list continuation, list headers, dropdown fields, or database subtotal-rules slices. The new behavior is specifically list-level `style:text-properties` marker metadata and its WordPress review attributes.

Root harness: not run - isolated micro-slice.
