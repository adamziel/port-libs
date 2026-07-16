# Pandoc Media Bag: Percent-Encoded Relative Resources

## Scope

- Added a bounded native PHP `MediaBag` lookup slice for AST image URLs such as `assets/review%20figure.png`.
- Resource maps may now supply the decoded safe relative key, for example `assets/review figure.png`, and the bag resolves it without fetching resources or touching the filesystem.
- Decoded lookup aliases are accepted only when the decoded path remains a safe relative media path, so encoded traversal such as `unsafe/%2e%2e/escape.png` is not resolved through a decoded `unsafe/../escape.png` resource key.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: `1 test files, 96 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: `42 test files, 58214 assertions, 0 failures`

## Boundaries

This does not add remote fetching, filesystem extraction, browser validation, Pandoc runner parity, or broad URL normalization. It is limited to safe decoded relative resource-map lookup and preserves hashed/placeholder handling for unsafe media sources.
