# Pandoc JSON Native Document Constructor - 20260612T033002Z

Bead: plib-sez1w

Scope: JSON/native AST constructor completeness for Pandoc's top-level tagged
document constructor.

The JSON/native readers now accept a bounded top-level `Pandoc` constructor of
the form `{"t":"Pandoc","c":[meta,blocks]}` and normalize it into the shared
document packet shape used by existing writers. The readers retain provenance
attributes for reviewer handoff:

- `documentConstructor: Pandoc`
- `documentNative`: the original tagged document payload

The JSON packet reader continues to normalize metadata through its existing
metadata path. The native reader preserves tagged native metadata while still
normalizing the top-level document constructor into blocks plus document attrs.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 test file, 1541 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 69905 assertions, 0 failures

No external Pandoc, office, TeX, browser, zip/unzip, Jupyter, Node, or external
validator tools were used.
