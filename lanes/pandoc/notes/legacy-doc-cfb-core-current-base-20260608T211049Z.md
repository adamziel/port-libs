# Legacy DOC CFB MiniFAT Allocation Hygiene

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260608T211049Z`
Base accepted HEAD: `26bbd2b7e4199c593e970e19e2909436056056d0`

## Source Truth

Compound File Binary MiniFAT entries are bounded by the root mini stream. Mini-sector entries outside the declared root mini-stream extent must not be treated as valid allocated chain entries, and MiniFAT chains must not point outside that declared mini stream.

This is distinct from the already accepted MiniFAT cutoff and regular FAT allocation preflights: this slice validates MiniFAT allocation hygiene before any legacy Word stream lookup can silently ignore allocated tail entries.

## Implementation

- `CompoundFileBinary` now validates MiniFAT allocation state after resolving the root mini-stream sectors.
- MiniFAT entries beyond the root mini-stream mini-sector count must be `FREESECT`.
- MiniFAT regular pointers must remain within the root mini-stream mini-sector count.
- MiniFAT reserved markers other than `FREESECT` and `ENDOFCHAIN` are rejected.
- Allocated MiniFAT entries inside the root mini stream must be owned by a stream chain.
- Existing test/example CFB fixture builders now pad unused MiniFAT entries with `FREESECT`, matching the stricter parser contract.

## Evidence

Red-first baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1663 assertions, 0 failures
```

Red-first failure after adding the MiniFAT-tail mutation test:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1664 assertions, 1 failures
Expected exception RuntimeException was not thrown
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1666 assertions, 0 failures

php -l lanes/pandoc/src/CompoundFileBinary.php
No syntax errors detected in lanes/pandoc/src/CompoundFileBinary.php

php -l lanes/pandoc/tests/LegacyDocReaderTest.php
No syntax errors detected in lanes/pandoc/tests/LegacyDocReaderTest.php

php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-legacy-doc-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, ": valid\n"; }'
lanes/pandoc/lane-status.json: valid
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json: valid

php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test
legacy doc handoff self-test ok

git diff --check -- lanes/pandoc
```

## Status Delta

- `lane-status.json` `phpPass`: `1854 -> 1855`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2282 -> 2283`
- Legacy DOC CFB core cases: `7 -> 8`
- Legacy DOC CFB core assertions: `64 -> 67`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `CompoundFileBinary` parser, `LegacyDocReader`, focused legacy DOC tests, and the existing WordPress legacy DOC handoff example. No Pandoc, Word, LibreOffice, zip/unzip, TeX/PDF engines, Haskell binaries, online services, or live provider tests were used.

## Non-Overlap

This does not repeat the accepted MiniFAT cutoff, surplus DIFAT, directory start-sector/tree, regular FAT allocation preflight, FibRgLw97, field-table, ASK/FILLIN, captions, RouteSlip, custom-property dictionary, or hyperlink field-code rendering work. It owns only MiniFAT allocation entries beyond/outside the root mini stream and unreferenced mini-sector hygiene before stream lookup.

## Follow-Up

Next high-signal legacy DOC/CFB slices can target mail-merge metadata, deeper master-document subdocument classification, or another bounded CFB header/allocation invariant that is not already covered by MiniFAT cutoff/allocation, surplus DIFAT, directory start-sector/tree, or regular FAT allocation preflight.
