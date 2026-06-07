# Pandoc Table Geometry Source Header Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260607T053139Z`
Base: `9f18ba88ee76386e943df1faf4ad3dc5a3241d77`
Date: 2026-06-07 UTC

## Behavior

Implemented a bounded table-geometry handoff for explicit source HTML `headers`
attributes. `TableGeometry::headerAssociations()` already resolved source
header tokens into reviewer-visible resolved/unresolved records; this slice
reuses that native PHP association data to emit writer downgrade diagnostics
for non-HTML table writers:

- Markdown reports `markdown-source-headers-require-raw-html` with
  `raw-html-table-headers`.
- AsciiDoc reports `asciidoc-source-headers-review-required` with
  `source-header-reference-review`.
- LaTeX reports `latex-source-headers-review-required` with
  `table-header-reference-comments`.
- WordPress remains unchanged and preserves safe source `headers` attributes
  directly in table output.

The diagnostic records include referencing-cell counts, total/resolved/
unresolved source-header reference counts, unresolved ids, source override
counts, and serializable per-cell reference records.

## Verification

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1202 assertions, 0 failures
```

Final focused checks:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php

php -l lanes/pandoc/tests/TableGeometryTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php

php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1248 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1589 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

`git diff --check -- lanes/pandoc` passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP table
geometry association resolver, review-packet writer downgrade plumbing, and
WordPress example self-test. No Pandoc, Cabal, Haskell runner, Word,
LibreOffice, zip/unzip, external writer, browser renderer, online service,
live provider test, or live-service provider test was executed.

## Follow-Up

Keep future table-geometry work bounded to non-overlapping table writer/AST
handoff gaps such as caption placement edge cases, source-reader vertical
alignment metadata, or remaining writer-specific span diagnostics.
