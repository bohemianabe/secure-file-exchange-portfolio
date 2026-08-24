# Security

## Authentication

The application uses Symfony Security with separate access rules for administrative, firm, and client users.

Additional authentication controls include:

- Two-factor authentication
- Trusted devices
- Login throttling
- Role-based access control

## Authorization

Authorization is enforced with Symfony voters rather than relying only on route-level roles.

Examples include access checks for:

- Files
- Clients
- Conversations
- Storage-plan features
- Signature-document functionality

## Multi-Tenant Isolation

Firm-owned resources are always evaluated against the authenticated user's firm. A user from one firm must not be able to access another firm's files, clients, conversations, or signature requests.

## File Security

Uploaded files are stored outside the public web root. Files are served through authorized controller routes rather than direct public URLs.

Additional protections include:

- MIME validation
- File-size limits
- UUID-based public identifiers
- Soft deletion
- Access logging
- Expiration and retention rules

## Secrets

Production credentials and secrets are provided through environment variables and are intentionally excluded from this repository.
