# JSON/native empty table section sidecars

`plib-ne43e` preserves empty `TableHead` and `TableFoot` helper constructors
when their Pandoc JSON/native payload carries inert sidecar keys outside `t`
and `c`.

Previously, `PandocJsonReader` dropped empty table head and foot sections when
they had no rows and no semantic attributes, even if their native helper payload
contained review/source sidecars. The reader now treats those sidecar-bearing
native payloads as content for AST retention. Rebuilt tables can therefore
round-trip unchanged helper payloads through `PandocJsonWriter` and
`NativeWriter`, while edited sections regenerate the helper constructor and drop
stale sidecar keys.

Validation:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Isolated PHP smoke for empty table-section sidecar retention through
  `PandocJsonReader`, `NativeReader`, `PandocJsonWriter`, and `NativeWriter`.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  confirmed the new `preserves empty table section native sidecars when
  rebuilding table wrappers` regression passes. The full focused file remains
  baseline-red with 11 unrelated existing failures.

No Pandoc, TeX, browser, office suite, zip/unzip, Node tooling, or external
validator was invoked.
