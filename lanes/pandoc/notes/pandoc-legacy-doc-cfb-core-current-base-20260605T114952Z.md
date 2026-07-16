# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T114952Z`
Base accepted HEAD: `1d3cff7c37d68f797f7ba98aef2dc3a8fe9e830c`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Extended `LegacyDocReader` OLE property-set parsing for bounded scalar
  `PropertyType` values commonly found in legacy Word summary streams:
  - `VT_UI2` unsigned 16-bit values.
  - `VT_UI4` unsigned 32-bit values.
  - `VT_I8` signed 64-bit values.
  - `VT_UI8` unsigned 64-bit values, using PHP integers when possible and
    decimal strings when the unsigned value exceeds PHP's signed integer
    range.
  - `VT_CLSID` GUID values formatted as stable lowercase CLSID strings.
- SummaryInformation and DocumentSummaryInformation metadata can now preserve
  unsigned Office counters and document-security flags instead of silently
  dropping them as unsupported `null` properties.
- User-defined custom document properties can now carry WordPress review
  provenance such as archive byte counts, max unsigned counters, signed
  migration offsets, reviewer tiers, and source GUIDs without exposing
  embedded payload bytes.
- Updated the WordPress legacy DOC handoff smoke so review packets assert
  unsigned byte-count metadata plus custom `Archive Bytes` and `Source Guid`
  provenance.

## Source Truth

Microsoft OLE Property Set Data Structures define property values as typed
records keyed by `PropertyType`, including `VT_UI2`, `VT_UI4`, `VT_I8`,
`VT_UI8`, and `VT_CLSID`. This slice ports that bounded scalar contract into
the native PHP legacy DOC metadata handoff. It does not attempt full OLE
storage semantics, blob/clipboard vector expansion, or external Office
conversion.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 469 assertions, 0 failures
```

Red-first check after adding expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
FAIL extracts legacy DOC unsigned integer 64-bit and CLSID OLE property scalars
Expected: 2147483650
Actual: NULL
1 test files, 470 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 475 assertions, 0 failures
```

Focused delta over the previous LegacyDocReader run: `469 -> 475` assertions
(`+6`) and `44 -> 45` focused PASS cases (`+1`).

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`LegacyDocReader`, `CompoundFileBinary`, OLE property-set parser,
WordPress block writer, focused PHP test harness, and WordPress legacy DOC
handoff example. Full upstream Pandoc runner parity remains gated on hydrating
and building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but OLE scalar property decoding
is not blocked by that runner.

## Non-Overlap

This does not repeat accepted CFB header parsing, MiniFAT stream extraction,
directory timestamps, CLSID/state-bit directory provenance,
SummaryInformation/DocumentSummaryInformation string/date/bool/vector parsing,
custom property dictionary parsing, encrypted FIB rejection, fExtChar direct
Unicode decoding, ObjectPool or macro inventory, bookmarks, footnote/endnote/
comment reference PLCs, section descriptors, stylesheet metadata, formatting
table ranges, field-code hyperlinks, FibRgLw97/PlcPcd subdocument bounds,
DOCX/ODT/EPUB package parsing, ZIP/OPC package behavior, archive streams,
table geometry, doctemplates, math/TeX, PDF engine handoff, charset handling,
YAML metadata, or Markdown/HTML/XML reader/writer behavior. It owns only
bounded OLE scalar value decoding for legacy DOC metadata review packets.

## Follow-Up

Keep property-set blob/clipboard/vector variants, deeper footnote/endnote/
comment body extraction, textbox subdocuments, FastSave piece-table edge
cases, full style/section application, and embedded object byte export policy
as separate bounded legacy DOC/CFB slices unless a concrete import fixture
requires them.
