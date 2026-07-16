# YAML Metadata Current-Base Slice 2026-06-09T045508Z

## Scope

Implemented a bounded native PHP YAML metadata diagnostic for invalid double-quoted escape sequences. `MarkdownReader` now records a non-fatal `yaml-scalar` diagnostic with the escaped token, metadata path, source line, and source scalar when a double-quoted YAML metadata scalar contains an unknown escape, malformed `\x`/`\u`/`\U` hex escape, or invalid Unicode code point.

The parsed metadata value still preserves the literal source escape text, so WordPress reviewer handoffs can continue while surfacing malformed front matter for audit.

## Focused Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4642 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4656 assertions, 0 failures
```

The focused test `records pandoc yaml invalid double quoted escape diagnostics without hiding metadata` adds 14 assertions over block scalars, flow sequence items, and nested citation metadata.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

passed locally after adding the invalid escaped source URI diagnostic handoff.

## Non-Overlap

This slice avoids the accepted YAML rows for duplicate set member diagnostics, undefined tag handles, reserved directives, document marker comments, tag directive URI suffixes, block scalar provenance, explicit collection tags, and scalar/core tag coercion. It only covers malformed double-quoted escape diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `MarkdownReader` YAML metadata parser, focused `MarkdownReaderTest.php`, and the existing WordPress YAML metadata handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner task requiring a hydrated Pandoc checkout and Haskell test executables.

## Next

A non-overlapping YAML follow-up could target directive boundary recovery, scalar style provenance in nested explicit-key contexts, or writer diagnostics for malformed scalar provenance.
