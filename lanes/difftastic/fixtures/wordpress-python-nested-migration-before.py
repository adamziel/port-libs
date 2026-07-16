def migrate_posts(posts):
    for post in posts:
        normalize_blocks(post)
        save_post(post)
