# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T073633Z`

Accepted base: `fec32e3bcafef623558ed5434ff9aa7044adaa71`

## Behavior

`MarkdownReader` now records non-fatal diagnostics when a YAML explicit mapping
key is written as a block scalar but its content is not indented enough to be a
valid block scalar.

The malformed explicit key/value pair is skipped, while valid sibling metadata
continues to parse. This keeps WordPress review packets importable and makes
the bad source shape visible to reviewer tooling instead of silently dropping
it.

Each diagnostic includes:

- `type: yaml-explicit-key`
- `reason: invalid-block-scalar-indentation`
- the block scalar `indicator`
- the original key `source`
- the invalid `contentLine`
- required and actual indentation columns
- `sourceLine` and `contentSourceLine`
- `parentPath` for nested metadata maps

## Evidence

Red-first behavior probe before the implementation:

```sh
php -r 'require "tools/bootstrap.php"; $md=implode("\n", ["---", "title: Invalid explicit key **Packet**", "? |-", "bad:key", ": root metadata value", "review:", "  ? >-", "  owner", "  : Import Desk", "  status: queued", "...", "", "# Body"]); $doc=(new PortLibs\Pandoc\MarkdownReader())->read($md); var_export($doc->attr("yamlMetadataDiagnostics", [])); echo "\n";'
```

Result: empty diagnostics for malformed block-scalar explicit keys.

Final focused run:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4990 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2506 -> 2507`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2881 -> 2882`.
- Added manifest counters:
  `mappedYamlMetadataInvalidExplicitKeyBlockScalarCases: 1` and
  `yamlMetadataInvalidExplicitKeyBlockScalarAssertions: 19`.

## Non-Overlap

This does not repeat accepted YAML block placement, invalid ordinary block
scalar body fallback, directives, comments, anchors, aliases, merge keys,
explicit scalar/core tags, ordered-pair diagnostics, duplicate keys, null keys,
collection provenance, explicit key parsing, valid explicit-key block scalar
provenance, binary scalar diagnostics, or writer-side YAML emission. It owns
only diagnostics for malformed block-scalar explicit mapping keys.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`MarkdownReader` YAML/front-matter parser, metadata diagnostics surface,
Pandoc-like AST, `MarkdownReaderTest.php`, and
`wordpress-yaml-metadata-handoff.php` smoke.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine,
TeX/PDF engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Next YAML metadata work should stay non-overlapping: writer-side provenance for
complex YAML keys, downstream WordPress metadata review display, or
multi-document stream provenance edge cases are reasonable bounded targets.
