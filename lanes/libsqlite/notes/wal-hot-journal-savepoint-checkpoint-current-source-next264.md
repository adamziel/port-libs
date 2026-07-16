# WAL hot-journal savepoint checkpoint current-source next264

Adds the after-current checkpoint receipt for the post-next263 database header sync. It reuses the admitted retry receipt seal and blocks stale header or hot-journal-visible receipts.
