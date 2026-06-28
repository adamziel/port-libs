# ODF font package role precedence

Hook: `plib-btlep`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Change

- Kept ODF font package members visible in rich `OdfReader` and compact
  `OpenDocumentPackage` media-resource review summaries as package-role
  precedence items, even when their MIME type is `font/*` rather than
  image/audio/video.
- Preserved font package bytes as metadata-only under
  `font-package-bytes-blocked`; font package members remain excluded from
  document media handoff and WordPress output.
- Corrected a compact font metadata fallback policy string from signature to
  font package byte blocking.

## Validation

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderFontPackageBytePolicyTest.php`
- Focused `OdfReaderFontPackageBytePolicyTest.php`
