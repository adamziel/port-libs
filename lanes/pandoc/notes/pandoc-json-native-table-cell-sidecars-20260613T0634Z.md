# Pandoc JSON/native table cell sidecars

Date: 2026-06-13
Base: de537d8
Bead: plib-rt897

Adds a focused native PHP AST read/write regression for Pandoc JSON/native table `Cell` payloads without invoking Pandoc, Haskell, Node, browsers, validators, online services, or live provider tests.

Coverage:

- Reads tagged `Cell` `Attr` payloads into cell `id`, `classes`, and key-value `attributes`.
- Preserves full tagged cell `Attr`, alignment, `RowSpan`, and `ColSpan` sidecars through JSON and native writers when unchanged.
- Regenerates the edited cell `Attr` tuple after an attribute transition while dropping stale edited-cell wrapper/attr sidecars.
- Keeps unchanged alignment/span helper sidecars on the edited cell and preserves the neighboring cell payload byte-for-byte.

Counters:

- `mappedJsonNativeTableCellAttrPayloadCases`: 1
- `jsonNativeTableCellAttrPayloadAssertions`: 56
- `phpPass`: 3352 -> 3353
- upstream mapped denominator: 3312 -> 3313

Verification so far:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 file, 2215 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 75567 assertions, 0 failures
