# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260609T021952Z`

Accepted base: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Behavior

`MarkdownReader` now applies YAML 1.2 integer-base rules to implicit plain
metadata scalars:

- `052` and `-052` parse as decimal `52` and `-52`, not YAML 1.1 legacy octal.
- `0o52` remains octal `42`.
- `0x2A` remains hexadecimal `42`.
- implicit `0b101010` remains a source string because binary integer notation
  is not implicit YAML 1.2 core schema.
- explicit `!!int 0b101010` keeps the existing reviewer-visible typed
  provenance and integer coercion.

The source-truth boundary is the YAML 1.2 core-schema change summarized by the
YAML 1.2.2 change notes: leading-zero octal was replaced by `0o` octal, and
binary/sexagesimal integer notation was dropped from the schema. See:
https://yaml.org/spec/1.2.2/ext/changes/

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.

Baseline focused run before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4375 assertions, 0 failures`.

Red-first focused run after adding the new YAML 1.2 integer-base test:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4377 assertions, 1 failures`; `052` still parsed as
legacy octal `42` instead of YAML 1.2 decimal `52`.

Final focused run:

```sh
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
```

Result: `1 test files, 4425 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
```

Result: `yaml metadata handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2130 -> 2131`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2557 -> 2558`.
- Added manifest counters:
  `mappedYamlMetadataSchemaIntegerBaseCases: 1` and
  `yamlMetadataSchemaIntegerBaseAssertions: 50`.

## Non-Overlap

This does not repeat accepted YAML 1.2 boolean behavior, YAML 1.2
sexagesimal handling, explicit typed scalar children, explicit integer base
coercion, invalid merge diagnostics, ordered-pair diagnostics, tagged keys,
quoted ambiguous field names, alias/anchor provenance, or writer-side YAML
scalar quoting. It owns only implicit plain-scalar integer-base policy under a
supported `%YAML 1.2` directive plus the directly coupled WordPress handoff
smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownReader` YAML/front-matter parser, existing scalar provenance records,
focused `MarkdownReaderTest.php` coverage, and
`wordpress-yaml-metadata-handoff.php`.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was executed.

## Follow-Up

Next YAML metadata work should stay non-overlapping: nested collection
source-span detail, writer-side ordered-pair emission, or metadata consumer
handoff are reasonable bounded follow-ups.
