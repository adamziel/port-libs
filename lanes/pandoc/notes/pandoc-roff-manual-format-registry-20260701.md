# Pandoc Roff/Manual Format Registry

## Summary

Mapped a bounded direct-format registry accounting slice for Pandoc
roff/manual formats.

- `man` is tracked as an upstream input-output token.
- `mdoc` is tracked as an upstream input-only token.
- `ms` is tracked as an upstream output-only token.
- `.ms` and `.roff` infer `ms`; numeric manual sections and suffixed section
  forms such as `.3p` and `.5ssl` infer `man`.
- Review packets expose unsupported input/output surfaces, extension pattern
  metadata, and parity summary counts while registering no native PHP
  roff/manual reader or writer implementation.

Direct-format parity remains conservative: all present roff/manual directions
are explicitly unsupported, absent directions are not applicable, and no Pandoc,
roff renderer, Cabal/Haskell runner, TeX/browser engine, office suite, Node
tooling, zip/unzip command, external validator, or live service was invoked.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed with 1 test file, 360 assertions, 0 failures.
