# YAML metadata core current-base: indented block scalar values and keys

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T061649Z`
Base accepted HEAD: `54e4f08a09f2e83c9a94575366cb4582953b41b9`

## Behavior

`MarkdownReader` now parses YAML metadata block scalar nodes when the block
scalar header appears as the indented value node instead of on the same line as
the mapping key. This covers source packets such as:

```yaml
review:
  note:
    |-
      Preserve front matter
      Keep source audit
```

The same slice also supports literal and folded block scalar explicit mapping
keys:

```yaml
? |-
  source:key
: metadata value
?
  >-
    source
    label
: folded key value
```

Explicit block-scalar keys are normalized to their parsed scalar value and
record `yaml-explicit-key-scalar` provenance with literal/folded style, chomp
mode, source line, and content span. Generic block-scalar provenance remains
reserved for metadata values, so key parsing does not emit a duplicate
value-provenance entry.

## Evidence

Red-first focused coverage before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4782 assertions, 1 failures
```

Final focused coverage:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 4820 assertions, 0 failures
```

The new focused test `maps pandoc yaml indented block scalar values and explicit
scalar keys` adds 1 PHP PASS case and 40 focused assertions.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

## Non-Overlap

This avoids the accepted YAML rows for invalid double-quoted escape diagnostics,
explicit typed block scalars, nested typed sequence/mapping child scalars, TAG
directive provenance, duplicate set member diagnostics, undefined tag handles,
reserved directives, document marker comments, tag directive URI suffixes,
explicit collection tags, and scalar/core tag coercion. It only covers indented
block scalar value nodes and block scalar explicit mapping keys.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external YAML parser, external template engine, external converter,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Dependency Closure

No new native support component is needed. This reuses the native PHP
Markdown/YAML metadata parser, existing scalar provenance records, focused
`MarkdownReaderTest.php`, and the existing WordPress YAML metadata handoff
example. Full upstream runner parity remains a separate upstream-runner
dependency task.

## Follow-Up

A non-overlapping YAML follow-up could target malformed block scalar explicit
key diagnostics, directive boundary recovery, or YAML writer round-trip
provenance for complex keys.
