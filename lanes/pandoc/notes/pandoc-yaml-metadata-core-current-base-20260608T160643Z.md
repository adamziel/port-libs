# pandoc-yaml-metadata-core-current-base-20260608T160643Z

## Scope

Accepted base: `7bb9457c376694c6a19cdc4541a59964cc2f5d73`.

This slice keeps ownership inside `pandoc-yaml-metadata-core-*`: native PHP
MarkdownReader YAML/front-matter metadata parsing. It does not run Pandoc,
Cabal solver/build/test commands, Haskell runners, external YAML parsers,
online services, live provider tests, or live-service provider tests.

No lane rework note existed for this current `port-pandoc-*` session before
editing.

## Behavior

MarkdownReader now preserves per-member source-line provenance inside multiline
YAML flow maps and sequences. Duplicate-key diagnostics, typed-scalar
provenance, quoted-scalar provenance, and nested flow collection provenance now
point at the item line rather than the opener line of the containing flow
collection.

The red probe before implementation showed the existing gap: a duplicate key
inside a multiline flow map reported `sourceLine => 2`, the flow collection
opener, even though the overriding key was on line 4 or later. The red-first
test then failed on the same source-line mismatch.

## Evidence

Red-first focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result before implementation: `1 test files, 3798 assertions, 1 failures`.
Failure reason: expected multiline flow duplicate-key provenance on source line
`8`, but the parser reported source line `2`.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result after implementation: `1 test files, 3805 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Syntax checks passed for changed PHP files:

```sh
php -l lanes/pandoc/src/MarkdownReader.php
php -l lanes/pandoc/tests/MarkdownReaderTest.php
php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php
```

Status delta recorded:

- `lane-status.json` `phpPass`: `1695 -> 1696`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`
- focused MarkdownReader assertions: prior accepted focused file count
  `3791 -> 3805` (`+14`)

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This reuses the existing
MarkdownReader YAML/front-matter parser, scalar provenance metadata,
collection provenance metadata, MarkdownReaderTest coverage, and the WordPress
YAML metadata handoff example.

Follow-up remains bounded to native YAML metadata behavior such as YAML
directive provenance, additional tag families, merge precedence diagnostics, or
block-scalar edge cases. Full upstream Pandoc YAML reader parity and external
parser/Haskell runner execution remain out of scope for this slice.
