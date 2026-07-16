# Pandoc JSON/native tagged Citation records

Date: 2026-06-15
Bead: plib-etm7y

## Scope

JSON and native readers now accept tagged `Citation` record wrappers inside `Cite` citation-record lists, including constructor contents shaped as either a direct record object or a single-wrapped record object. The shared AST still exposes normalized citation fields while retaining the original tagged wrapper in `citationNative`.

JSON and native writers preserve unchanged tagged `Citation` record wrappers during Cite regeneration. When citation fields are edited, writers regenerate canonical plain citation records and drop stale wrapper sidecars.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 5152 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 87740 assertions, 0 failures

## Accounting

- `phpPass`: `3704 -> 3705`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3728 -> 3729`
- `mappedJsonNativeConstructorCompletenessCases`: `49 -> 50` in lane status; `47 -> 48` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1177 -> 1219` in lane status; `1134 -> 1176` in the upstream manifest
- `mappedJsonNativeCitePayloadCases`: `4 -> 5` in lane status and upstream manifest
- `jsonNativeCitePayloadAssertions`: `183 -> 225` in lane status and upstream manifest
- `mappedJsonNativeTaggedCitationRecordCases`: `1`
- `jsonNativeTaggedCitationRecordAssertions`: `42`
