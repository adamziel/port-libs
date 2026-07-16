# Pandoc YAML Metadata Current-Base Nested Explicit Keys

Slice: `pandoc-yaml-metadata-core-current-base-20260607T152813Z`
Base accepted HEAD: `6a3ea0f4861660790e73a0b7403add52995f31fa`

## Behavior

`MarkdownReader` now preserves YAML explicit block keys whose key node is
itself an explicit mapping pair. This keeps WordPress import metadata such as
review-source keys, reviewer-owner keys, label sets, and reference metadata
stable:

```yaml
?
  ? source
  : uri
: https://example.test/import#nested-explicit-key
```

The parsed metadata key is now `{source: uri}` instead of the broken
`{source: null}` plus a folded scalar value.

The same indentation-aware collection is applied to block `!!set` keys, so
review label sets can carry structured explicit keys without leaking partial
scalar keys.

## Source-Truth Boundary

Pandoc YAML metadata blocks are YAML documents before they become document
metadata. This slice ports only the bounded native PHP support needed for
explicit-key parsing where nested `?` and `:` lines belong to the key node by
indentation. It does not claim full YAML parser parity.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Red-first direct probe before implementation parsed the nested explicit key
  as `{source: null}` with value `key : value`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3426 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- `php -l` passed for changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  and `lanes/pandoc/lane-status.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1523 -> 1524`.
- Manifest mapped denominator: `1943 -> 1944`.
- Added manifest inventory keys:
  - `mappedYamlMetadataNestedExplicitMappingKeyCases: 1`
  - `yamlMetadataNestedExplicitMappingKeyAssertions: 15`

## Dependency Closure

No new support component is needed. This slice reuses native
`MarkdownReader` YAML metadata parsing, AST metadata attributes,
`WordPressBlockWriter`, `MarkdownReaderTest.php`, and the existing
`wordpress-yaml-metadata-handoff.php` smoke.

## Non-Overlap

This avoids the accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffix, punctuation anchor, null-key, indented document-marker,
writer block-scalar, flow explicit-key, alias diagnostic-path, duplicate-key,
quoted ambiguous-field, top-level flow mapping document, and plain scalar
folding slices. The patch owns only nested explicit mapping-key collection in
block metadata maps and block set keys.

Suggested follow-up: keep multi-document stream policy, comment/source-location
provenance, directive/comment writer emission, and richer MetaValue fidelity as
separate bounded YAML metadata slices.
