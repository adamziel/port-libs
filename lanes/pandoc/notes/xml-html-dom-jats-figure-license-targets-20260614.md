# XML/HTML DOM JATS Figure License Targets

## Scope

- Added bounded JATS/BITS figure permission license target records for `license`, `license-ref`, and nested `ext-link` elements.
- Preserved figure/media linkage on target records with scope, figure id, media target, permission id, target kind, scheme, source position, and `payloadBytesExposed=false`.
- Added missing, duplicate, and unsafe license target diagnostics while keeping direct reader parity unsupported and media payload bytes blocked.

## Evidence

- `mappedXmlHtmlDomJatsFigurePermissionCases` moves 1 -> 2.
- `xmlHtmlDomJatsFigurePermissionAssertions` moves 50 -> 75.
- `phpPass` moves 3579 -> 3580; `phpFail` remains 0.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 file, 4187 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 84004 assertions, 0 failures.
