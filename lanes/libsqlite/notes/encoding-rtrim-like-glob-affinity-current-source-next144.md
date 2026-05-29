# Encoding RTRIM LIKE/GLOB Affinity Current Source Next144

This slice adds `SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan`
for the intersection of accepted RTRIM current-source scans and accepted
LIKE/GLOB affinity predicates.

Behavior covered:

- dynamic LIKE/GLOB pattern columns over copied `wp_options` rows;
- SQLite text affinity for string, integer, real, boolean, and blob values;
- RTRIM expression keys that trim ASCII spaces but keep tabs distinct;
- UTF-8 / UTF-16LE / UTF-16BE byte evidence for current/next sources;
- current-source cursor invalidation for source, schema, encoding, value,
  pattern, bytes, residual result, malformed text, and matched-rowset changes.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNext144Test.php`
- `php lanes/libsqlite/examples/wordpress-encoding-rtrim-like-glob-affinity-current-source-next144.php --self-test`

Dependency closure: no new support component needed; this reuses native text
affinity, RTRIM collation, LIKE/GLOB residual matching, current-source
metadata, and UTF encoding helpers.

Non-overlap: this does not repeat accepted UTF-16 NOCASE LIKE next141,
Unicode GLOB ranges, malformed UTF-16 guards, next140 static RTRIM
option-name range scans, or next109 standalone LIKE/GLOB affinity predicates.
