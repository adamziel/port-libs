# ODF OpenDocument Core Current Base: Form-Control Handoff

Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T104314Z`

Base accepted HEAD: `c6b8bdd91e9129ca076584776bb76e4fcded4d0c`

## Implementation

- Added bounded native ODF `office:forms` parsing in `OdfReader`.
- Mapped `draw:control` references in paragraphs, inline frames, block frames, and top-level block positions into inert AST placeholders.
- Preserved review metadata for resolved controls: control id, type, form name, control name, label, implementation, value/current-value/current-state, linked/source cell ranges, tab index, href, disabled/printable flags, and frame name/size.
- Preserved unresolved control references as explicit `odf-missing-form-control` placeholders instead of dropping content.
- Added import-report counters for resolved and missing form controls.
- Updated the WordPress ODF example to expose a checkbox control placeholder without invoking office tooling.

## Red-First Evidence

Before the implementation, the new form-control test failed as expected:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 613 assertions, 1 failures`.

The failure showed the reader was not producing the expected form-control block/inline placeholders.

## Focused Verification

Final focused test run:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`

Result: `2 test files, 747 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

Syntax checks:

- `php -l lanes/pandoc/src/OdfReader.php` passed.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` passed.

Metadata and diff hygiene:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " valid\n"; }'` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `842 -> 843`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1301 -> 1302`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 257`.
- Focused `OdfReaderTest.php` coverage moved from `612` to `652` assertions.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP ODF DOM/XML reader, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, office automation, zip/unzip, Cabal build, Haskell runner, online sanitizer, or online conversion service was executed.

## Non-Overlap And Follow-Up

This patch does not touch accepted ODF sequence-field, bibliography-mark, soft-page-break, annotations, image/frame, section, list, table, or media-metadata behavior.

Follow-up ODF slices should keep full form action/submission semantics, calculated fields, live widgets, validation, scripting, database bindings, chart objects, richer style cascades, and export-to-office form controls separate and bounded.

Root harness status: not run - isolated micro-slice.
