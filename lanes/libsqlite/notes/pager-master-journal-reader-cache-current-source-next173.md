# Pager Master-Journal Reader Cache Current Source Next173

- Scope: pager master-journal reader-cache reuse after reading the current master journal bytes.
- Behavior: cache pages are reusable only when source id, epoch, current master-journal digest, and current master-journal membership still match the freshly read source. Image-matching pages with stale membership are invalidated and force reader reopen.
- Application path: copied `wp_options` readers can retain a shared schema page, refresh an `active_plugins` page, and reopen a stale roles reader when an attached users journal is newly present in the master journal.
- Non-overlap: avoids accepted next167 deleted-generation tickets and batch159 pager master-journal reader-cache behavior by focusing on fresh member-list/digest source fencing.
- Dependency closure: no new support component needed; this reuses lane-local pager master-journal member parsing and reader-cache ticket primitives.
