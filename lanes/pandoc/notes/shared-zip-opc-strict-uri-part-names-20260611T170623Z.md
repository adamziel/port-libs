# Shared ZIP/OPC Strict URI Part Names 20260611T170623Z

- Bead: `plib-k0wcg`.
- Scope: Added `OpcPackagePath::canonicalPartNameFromStrictUri()` for URI-bearing OPC part-name fields and routed OPC content-type override XML validation through it.
- Guardrail: Strict URI normalization rejects raw URI whitespace/control bytes and empty or dot path segments before canonical package-name normalization, while preserving `%20` decoding and raw ZIP/package part lookup behavior.
- Verification: `php -l` passed for `OpcPackagePath.php`, `OpcContentTypes.php`, and `OpenPackagingConventionsTest.php`.
- Focused lane: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed 1 test file, 4103 assertions, 0 failures.
- Full lane: `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 64206 assertions, 0 failures.
- External tooling: no Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests invoked.
