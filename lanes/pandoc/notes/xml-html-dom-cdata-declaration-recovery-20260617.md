# XML/HTML DOM CDATA declaration recovery

## Scope

- Slice: `pandoc-xml-html-dom-cdata-declaration-recovery`
- Base: `origin/main` at `67e56fb1ae`
- Bead: `plib-9stmy`

## Implementation

- `Html5Dom`, `Html5DomFragment`, `XmlHtmlDom`, and `XmlHtmlDomFragment` now ignore closed XML CDATA sections during DTD/PI source preflight scans.
- Closed CDATA content remains parsed and serialized as inert text; live doctypes, live processing instructions, and unterminated CDATA inputs still fail.

## Verification

- `php -l` for touched source files and `XmlHtmlDomCdataDeclarationRecoveryTest.php`.
- Focused CDATA recovery test:
  - 1 file, 24 assertions, 0 failures.
- HTML/XML DOM cluster:
  - 7 files, 8007 assertions, 0 failures.
- Full Pandoc lane:
  - 236 files, 172834 assertions, 0 failures.

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.
