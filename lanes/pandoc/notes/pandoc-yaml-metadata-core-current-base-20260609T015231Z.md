# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T015231Z`

Accepted base: `21742a408faf47b66c5937f3cfd9d335c203497c`

## Behavior

`MarkdownReader` now records non-fatal `yaml-ordered-pair` diagnostics when
explicit YAML `!!omap` or `!!pairs` collections contain members that are not
single-pair mappings.

The parser still preserves the currently visible metadata for reviewer audit:

- multi-key mapping members are flattened into ordered key/value pairs;
- sequence or scalar members remain visible as key-only pairs when they can be
  normalized;
- diagnostics stay outside plain `meta` on the document attribute
  `yamlMetadataDiagnostics`.

Each invalid ordered-pair diagnostic includes:

- `reason: invalid-ordered-pair-member`
- `path`
- `explicitTag`
- `pairIndex`
- `valueKind`
- `expected: single-pair mapping`
- `memberCount` for multi-key mappings
- `sourceLine` when source-line tracking is active

The WordPress YAML metadata handoff smoke now checks the same invalid
`!!omap`/`!!pairs` examples and verifies that visible metadata is preserved.

## Evidence

Baseline focused run before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4318 assertions, 0 failures`.

Red-first behavior probe before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\MarkdownReader; $doc=(new MarkdownReader())->read("---\nordered: !!omap\n  - source: front\n    owner: Import Desk\n  - [bad, key]\n  - status: queued\nflow: !!pairs [{owner: Import Desk, role: editor}, [bad, key], status]\n...\n\n# Body\n"); var_export($doc->attr("yamlMetadataDiagnostics", [])); echo "\n";'
```

Result: empty diagnostics for malformed `!!omap`/`!!pairs` members.

Final focused run:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4349 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2089 -> 2090`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2501 -> 2502`.
- Added manifest counters:
  `mappedYamlMetadataOrderedPairDiagnosticCases: 1` and
  `yamlMetadataOrderedPairDiagnosticAssertions: 31`.

## Non-Overlap

This does not repeat accepted directive provenance, tag directive diagnostics,
alias diagnostics, duplicate key diagnostics, merge diagnostics, null-key
diagnostics, explicit collection tag provenance, block scalar handling,
explicit mapping keys, or writer-side YAML emission. It owns only diagnostics
for malformed members inside explicit `!!omap` and `!!pairs` ordered-pair
collections.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`MarkdownReader` YAML/front-matter parser, document metadata diagnostics,
`MarkdownReaderTest.php`, and `wordpress-yaml-metadata-handoff.php` smoke.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Next YAML metadata work should stay non-overlapping: writer-side ordered-pair
emission, richer tag URI review metadata, nested collection source-span
detail, or additional schema-version diagnostics are reasonable bounded
targets.
