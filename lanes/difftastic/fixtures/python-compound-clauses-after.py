if status == "draft":
    queue_review(post)
elif status == "private":
    queue_private(post)
else:
    publish(post)

try:
    sync_attachments(post)
except ValueError as error:
    log_error(error)
finally:
    cleanup_temp(post)
