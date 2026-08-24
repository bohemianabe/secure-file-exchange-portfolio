# Asynchronous Processing

Symfony Messenger is used for work that should not block the original HTTP request.

Examples include:

- Email notifications
- Audit logging
- ZIP generation
- File-related notifications

## Workflow

HTTP Request
→ Persist initial state
→ Dispatch message
→ Return response
→ Worker processes message
→ Update final state

## Failure Handling

A Messenger failure listener detects messages that have exhausted their retries.

When a long-running job fails permanently:

1. The related application record is marked as failed.
2. An audit entry is created.
3. The user is no longer left with a permanently "processing" request.
4. The failure is logged for diagnosis.

This was added after diagnosing a production issue where a failed export could otherwise remain stuck indefinitely.
