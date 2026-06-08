# Pandoc YAML Metadata Current-Base Slice

Slice: `pandoc-yaml-metadata-core-current-base-20260608T151113Z`
Base accepted HEAD: `4c862b32f8029fb79956472ec44c66aa3f81547c`

## Behavior

`MarkdownReader` now rejects Pandoc YAML metadata blocks when any nonblank literal/folded block-scalar content line is under-indented, not only when the first content line is under-indented. The invalid source remains visible as Markdown body and WordPress block output, matching the existing fail-closed behavior for malformed front matter.

## Evidence

Red-first focused run before the parser fix:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 3792 assertions, 1 failures`; the new late under-indented block-scalar case was accepted as metadata and swallowed the source line.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`

Result: `1 test files, 3803 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`

Result: `yaml metadata handoff self-test ok`.

## Non-Overlap

This slice does not repeat prior YAML coverage for omitted opening markers, directives, comments, anchors/aliases, merge keys, explicit tags, document markers inside valid block scalars, or first-line invalid block-scalar indentation. It covers a late under-indented content line after an initially valid block-scalar line.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MarkdownReader` YAML/front-matter parser and `WordPressBlockWriter`. No Pandoc binary, Cabal solver/build/test command, Haskell runner, external YAML parser, online service, live provider test, or live-service provider test was executed.
