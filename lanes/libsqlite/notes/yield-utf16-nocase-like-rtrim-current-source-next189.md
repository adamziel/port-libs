# UTF-16 NOCASE LIKE RTRIM current-source next189

- Slice: `encoding-utf16-nocase-like-rtrim-current-source-next189`.
- Behavior: adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16 `wp_options` option-name range scans using `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` with peer-window rowid tie-breaker diagnostics across current-source refresh.
- Focus: same-key RTRIM/NOCASE peers can resume only when the rowids before or at the yielded token are stable and the LIKE residual rowset is unchanged; ASCII-space padding-only rewrites keep the peer key but deleted/inserted peers or residual membership changes force a range restart.
- Application smoke: `examples/application-utf16-nocase-like-rtrim-current-source-next189.php`.
- Non-overlap: avoids accepted deleted-token resume next185, resume-boundary next186, escaped residual token next184, Unicode GLOB ranges, UTF-16 malformed insert guards, and current B-tree/JSON/VFS/WAL/planner surfaces.
- Dependency closure: no new support component needed; this reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and current-source peer-window replay diagnostics.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext189Test.php`: `1 test files, 65 assertions, 0 failures`.
  - `php -l` on changed PHP source/test/example files: no syntax errors.
  - `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next189.php`: self-test passed.
  - `git diff --check -- lanes/libsqlite`: passed.
  - Root harness: not run - isolated micro-slice.
