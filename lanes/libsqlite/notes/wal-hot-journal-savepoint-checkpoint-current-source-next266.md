# WAL hot-journal savepoint checkpoint current-source next266

Adds the after-current reader-mark release receipt after the next265 WAL-index salt receipt. It blocks pinned reader marks before the final checkpoint seal.
