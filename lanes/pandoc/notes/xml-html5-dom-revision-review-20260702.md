# XML/HTML5 DOM Revision Review

- `<ins>` and `<del>` summaries now include a bounded revision review policy for `cite` and `datetime` metadata.
- Revision `cite` values are classified with the existing URL safety model and report empty, invalid, or unsafe issue codes without fetching targets.
- Revision `datetime` values retain normalized date/local/global datetime output and now expose issue records for empty or invalid source values.
