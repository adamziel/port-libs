# WAL hot-journal savepoint checkpoint current-source next265

Adds the after-current WAL-index salt receipt after the next264 header receipt. It blocks when the WAL-index salt is not synchronized to the same current-source generation.
