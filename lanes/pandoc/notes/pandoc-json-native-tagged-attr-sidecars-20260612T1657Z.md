# Pandoc JSON/native tagged Attr sidecars

## Slice

- Hook: `plib-wa2zk`
- Base: `origin/main` `fa8ea4e6a7`
- Scope: `lanes/pandoc`

## Coverage

This slice maps one JSON/native AST constructor completeness case for NativeReader-ingested tagged `Attr` helpers that include sidecar payloads beyond the canonical Pandoc attr tuple.

The new regression fixture builds a native `CodeBlock` packet with a tagged `Attr` helper whose first three values match the shared AST id/classes/key-value fields and whose fourth value is inert review metadata. When the code block text changes, both `PandocJsonWriter` and `NativeWriter` now preserve the tagged `Attr` helper while regenerating the edited constructor from shared AST fields. When the attr id itself changes, both writers regenerate a plain attr tuple and drop the stale tagged helper.

## Accounting

- `phpPass`: 3245 -> 3246
- `phpFail`: 0
- `mappedJsonNativeTaggedAttrSidecarCases`: 1
- `jsonNativeTaggedAttrSidecarAssertions`: 10
- Mapped denominator: 3266

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` (1 file, 1629 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 72337 assertions, 0 failures)

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
