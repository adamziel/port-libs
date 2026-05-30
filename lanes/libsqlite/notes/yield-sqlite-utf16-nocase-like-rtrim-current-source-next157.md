# UTF-16 NOCASE LIKE RTRIM Current Source Next157

## Scope

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a UTF-16-only current-source cursor plan for `rtrim(option_name) COLLATE NOCASE LIKE ...` over copied Application `wp_options` rows.

This intentionally avoids the accepted generic NOCASE/RTRIM LIKE next146 and UTF-16 RTRIM/GLOB/NOCASE slices by asserting the remaining byte-order-sensitive edge: rows must already be UTF-16LE/UTF-16BE, decoded text is compared through SQLite ASCII-only NOCASE LIKE semantics, RTRIM trims only ASCII spaces for the index key, and a LE/BE source rewrite invalidates an otherwise stable cursor.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext157Test.php
```

Example smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next157.php
```

Lint/checks:

```text
php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext157Test.php
php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next157.php
git diff --check -- lanes/libsqlite
```

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode validation, existing LIKE prefix planning, the existing NOCASE/RTRIM current-source cursor plan, and lane-local Application smoke coverage.

## Non-Overlap

Avoids accepted batch148 encoding coverage and recent next146/next148/next149 RTRIM/GLOB/NOCASE work by focusing only on UTF-16 source rows plus byte-order invalidation for `NOCASE LIKE` over `RTRIM` keys.
