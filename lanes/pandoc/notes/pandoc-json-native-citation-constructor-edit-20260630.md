# Pandoc JSON/native citation constructor edit slice - 2026-06-30

## Scope

- Area: JSON/native AST constructor completeness.
- Bounded change: preserve tagged `Citation` record wrappers when a citation record is edited, regenerating the inner record payload while retaining the constructor wrapper provenance.
- No external Pandoc, citeproc, TeX, browser, office, ZIP, Node, or validator processes are invoked.

## Behavior

- `PandocJsonWriter` now treats tagged `Citation` helper records like the existing edited `Attr`, `Target`, `Caption`, and scalar helper paths.
- Unchanged tagged citation records are still reused exactly.
- Edited tagged citation records keep the outer `Citation` wrapper and wrapper sidecars, while the stale inner record payload is replaced with the regenerated `citationId`, affixes, mode, note number, and hash.
- Untagged citation record sidecars still drop when edited, preserving the existing stale-sidecar safety behavior.

## Validation

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Focused result: 1 file, 6,207 assertions, 0 failures.
