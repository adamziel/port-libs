if status == "draft":
    queue_review(post)
else:
    publish(post)

try:
    sync_attachments(post)
except ValueError as error:
    log_error(error)
