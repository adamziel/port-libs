# Pandoc YAML Metadata Current-Base Plain Scalar Folding

Slice: `pandoc-yaml-metadata-core-current-base-20260607T105539Z`
Base accepted HEAD: `7024533eae898cea81b789321e8a4eb61cd2cb35`

## Behavior

`MarkdownReader` now preserves line breaks around more-indented continuation
lines inside YAML plain multiline metadata scalars while retaining the existing
blank-line folding behavior for ordinary plain scalars.

This keeps WordPress review packet metadata such as reviewer logs and imported
outline/list snippets from flattening into a single sentence:

```yaml
review:
  note:
    Queue log
      source: wp-export.xml
      status: pending
    Ready.
```

The parsed value is now:

```text
Queue log
  source: wp-export.xml
  status: pending
Ready.
```

## Source-Truth Boundary

Pandoc YAML metadata blocks delegate this front-matter shape to YAML plain
scalar folding. Ordinary continuation lines fold to spaces, blank-line behavior
stays compatible with the accepted reader tests, and more-indented continuation
lines remain line-separated.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this lane before implementation.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3399 assertions, 0 failures`.
- Red-first probe: a direct `MarkdownReader` metadata probe flattened
  more-indented lines into spaces before the source change.
- First focused implementation run caught a compatibility regression where
  blank-line plain scalar folding became too literal; the plain-scalar fold
  logic was corrected before handoff.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 3411 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed with `yaml metadata handoff self-test ok`.
- `php -l` passed for changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  and `lanes/pandoc/lane-status.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1489 -> 1490`.
- Manifest mapped denominator: `1908 -> 1909`.
- Added manifest inventory keys:
  - `mappedYamlMetadataPlainMoreIndentedScalarCases: 1`
  - `yamlMetadataPlainMoreIndentedScalarAssertions: 12`

## Dependency Closure

No new support component is needed. The slice reuses the native
`MarkdownReader` YAML front-matter parser, existing plain-scalar normalization,
`MarkdownReaderTest.php`, and `wordpress-yaml-metadata-handoff.php`.

## Non-Overlap

This does not repeat accepted YAML directive provenance, malformed `%TAG`,
URI-tag suffix, punctuation anchor, null-key, indented document-marker,
writer block-scalar, flow explicit-key, alias diagnostic-path, duplicate-key,
quoted ambiguous-field, or top-level flow mapping document slices.

Suggested follow-up: stay in non-overlapping YAML parser/writer gaps such as
multi-document stream policy, source-location/comment diagnostics,
directive/comment emission, or richer MetaValue fidelity.
