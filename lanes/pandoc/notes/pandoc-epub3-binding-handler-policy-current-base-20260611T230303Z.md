# EPUB3 Binding Handler Policy Provenance

Bead: `plib-40lwn`

Base: current `origin/main` `67332814bf`

## Summary

EPUB3 package ingestion now preserves OPF media-type binding handler policy for
remote and encrypted handlers in the full `EpubReader` handoff.

- Remote binding handlers are reported as `external-binding-handler` instead of
  looking like missing package parts with empty part names.
- Binding items now carry `handlerExternal` and `handlerEncryption`, so review
  queues can see target policy, encryption role, review policy, and byte
  exposure policy without exposing handler bytes.
- Binding policy metadata propagates through `bindings`,
  `importReport['bindings']`, and document binding attrs.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4058 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66934 assertions, 0 failures
- `git diff --check -- lanes/pandoc`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

## Accounting

- Adds one focused `EpubReaderTest.php` PASS case with 30 assertions.
- `phpPass` moves from 3137 to 3138; `phpFail` remains 0.
- Adds `mappedEpubBindingHandlerPolicyCases = 1`.
- Adds `epubBindingHandlerPolicyAssertions = 30`.

## Boundaries

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests were invoked.
