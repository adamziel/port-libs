# SQLite UTF-16 NOCASE LIKE RTRIM Current Source Next156

## Behavior

- Adds `SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext156Plan` for a combined encoding/collation edge not covered by the accepted UTF-16 malformed guard, Unicode GLOB range, next143 LIKE ESCAPE, or next144 RTRIM dynamic-affinity slices.
- The planner decodes UTF-8/UTF-16LE/UTF-16BE `wp_options.option_name` bytes, builds RTRIM range candidates, and then applies an untrimmed ASCII-NOCASE LIKE residual.
- This preserves the SQLite distinction where a padded UTF-16 value can be admitted by an RTRIM candidate key but rejected by exact LIKE residual matching, while ASCII case variants still match and non-ASCII case variants do not fold.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext156Test.php`
  - `73 PASS lines`
  - `1 test files, 79 assertions, 0 failures`

## Application Smoke

- Added `examples/application-utf16-nocase-like-rtrim-current-source-next156.php` to show copied Application option-name rows moving between UTF-16 encodings while preserving RTRIM candidates and NOCASE LIKE matches.

## Non-Overlap

- Avoids accepted UTF-16 malformed text guard, Unicode GLOB range behavior, UTF-16 LIKE ESCAPE next143, RTRIM dynamic affinity next144, and batch148 RTRIM/GLOB/NOCASE affinity coverage.
- This slice is limited to combined RTRIM candidate range plus NOCASE LIKE residual behavior for UTF-16 current/next source invalidation.

## Dependency Closure

- No new support component is needed. The slice reuses native UTF-16 decode, existing LIKE pattern planning, RTRIM key normalization, and ASCII NOCASE residual matching.
