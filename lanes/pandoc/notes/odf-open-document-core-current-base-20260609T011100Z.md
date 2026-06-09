# ODF OpenDocument Core Current Base - Table Cell Annotations

Slice: `pandoc-odf-open-document-core-current-base-20260609T011100Z`

Accepted base: `09109401d59cee7a589aaf8125432abbe4aef718`

## Behavior

Native ODF/OpenDocument table-cell import now treats direct `office:annotation`
children of `table:table-cell` as review metadata, not visible cell content.

The reader preserves bounded annotation provenance on the `table_cell` AST node:

- annotation name from `office:name`
- author from `dc:creator`
- date from `dc:date`
- plain annotation text for review metadata
- annotation block count

WordPress table output receives safe `td` attributes for count, authors, dates,
and text-count, while the annotation comment body is kept out of visible
table-cell text.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before
the slice.

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2589 assertions, 0 failures
```

Red-first probe after adding the focused ODF table-cell annotation test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2590 assertions, 1 failures
Expected 'Ready' but got 'Ready Confirm imported source status.'
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2606 assertions, 0 failures
```

Coupled ODT reader regression:

```text
php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php
1 test files, 95 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Syntax and metadata checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed
```

## Dependency Closure

No new support component is needed. This reuses the native PHP `OdfReader`
DOM/XML package reader, existing annotation metadata extraction,
Pandoc-like AST table-cell attributes, and `WordPressBlockWriter` table output.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted ODF subtotal-rules, data-pilot, named
range/expression, tracked table-change, field, table-caption, covered-cell,
style-map, drawing-layer, chart/object, or notes-configuration work. The new
case is limited to direct table-cell annotations and their WordPress review
metadata handoff.

## Root Harness

Root harness not run - isolated micro-slice.
