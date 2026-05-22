def migrate_posts(posts):
    for post in posts:
        if post.get("featured_media"):
            hydrate_featured_media(post)
        normalize_blocks(post)
        save_post(post)
