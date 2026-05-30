# encoding-collation-affinity-like-current-source-next234

## Behavior

Adds a focused current-source plan for SQLite `LIKE_DOESNT_MATCH_BLOBS`
behavior over Application `wp_options.option_value` scans:

- implicit `LIKE` / `GLOB` skips `SQLiteBlobValue` rows even when the bytes
  look like matching text;
- explicit `CAST(... AS TEXT)` admits well-formed BLOB bytes into the same
  residual matcher;
- source/cookie invalidation records storage-class, byte, text, BLOB-skip, and
  matched-rowset changes between current and next sources;
- malformed text and malformed explicitly-cast BLOB bytes remain blockers
  instead of silently entering the matcher.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php`
  - `1 test files, 90 assertions, 0 failures`
  - 90 focused PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-blob-like-glob-affinity-current-source-next234.php --self-test`
  - validates implicit BLOB exclusion and explicit CAST admission for copied
    `wp_options` values.

## Non-Overlap

This slice avoids accepted UTF-16 NOCASE/LIKE/RTRIM, Unicode GLOB range,
malformed UTF-16 insert guard, scalar-only cast/collation, and existing
current-source GLOB cast clusters. It targets the separate BLOB storage-class
admission rule for LIKE/GLOB residuals.

## Dependency Closure

No new support component is needed. The plan reuses native scalar storage
classification, `SQLiteBlobValue`, existing LIKE/GLOB residual matching, and
current-source invalidation diagnostics.
