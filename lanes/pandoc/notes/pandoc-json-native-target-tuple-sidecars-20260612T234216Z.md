# Pandoc JSON/native target tuple sidecars

Bead: `plib-gryw1`
Date: 2026-06-12 UTC
Area: JSON/native AST constructor coverage
Rebased base: `b30fb70127`

## Ship-readiness matrix

| Check | Evidence |
| --- | --- |
| Upstream format-related denominator | 252 upstream `.native` expected artifacts under `test/`, from `lanes/pandoc/notes/upstream-inventory.md` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`. |
| Local passing numerator | 45 focused JSON/native AST evidence cases after this slice. |
| Percent | 17.9%. |
| Local test evidence | `PandocJsonNativeAstTest.php` passed 1 file, 1968 assertions, 0 failures. |
| Remaining critical gaps | Broader upstream native/json fixture parity, unsupported constructor surfaces, and remaining table/citation/metadata round-trip edge cases beyond the bounded sidecar/rebuild coverage. |
| Verdict | Not shippable yet. One highest-impact native PHP gap was implemented and tested. |

## Implemented gap

`PandocJsonReader` now accepts Link/Image target tuples with inert sidecar
fields while normalizing the first two URL/title entries. `NativeReader` now
preserves the full target tuple instead of truncating it to URL/title.

`PandocJsonWriter` and `NativeWriter` now reuse compatible target tuples when
the normalized URL/title still match, including after unrelated Link/Image
attribute rebuilds. If the URL or title changes, both writers regenerate a bare
URL/title target tuple and drop stale sidecar provenance.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: 1 file, 1968 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 73988 assertions, 0 failures

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: 3294 -> 3295
- `phpFail`: 0
- `mappedJsonNativeTargetTupleSidecarCases`: 1
- `jsonNativeTargetTupleSidecarAssertions`: 24
