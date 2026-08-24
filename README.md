# Secure File Exchange Platform

A sanitized portfolio version of a production-oriented, multi-tenant secure
file exchange and document workflow application built with Symfony.

The application demonstrates architecture and implementation patterns for
secure file management, multi-tenant authorization, asynchronous processing,
electronic signatures, real-time-style messaging, audit logging, and
third-party API integrations.

> This repository is a sanitized portfolio representation of a larger
> application. Proprietary business logic, production credentials, customer
> information, branding, and infrastructure details have intentionally been
> removed or replaced.

---

## Screenshots

![Dashboard](docs/screenshots/dashboard.png)

![File Manager](docs/screenshots/file-manager.png)

![Signature Templates](docs/screenshots/signature-template-gallery.png)

---

## Technology Stack

### Backend

- PHP 8.4
- Symfony 7.4
- Doctrine ORM
- MariaDB
- Symfony Messenger
- Symfony Scheduler
- Symfony Security
- Symfony Mailer
- Twig

### Frontend

- JavaScript
- Bootstrap
- AssetMapper
- AJAX
- DataTables
- Select2

### Integrations

- Dropbox Sign
- TOTP / email-based two-factor authentication
- QR code generation

### Infrastructure

- Docker
- Apache
- Composer
- PHPUnit

---

## Key Features

### Secure File Management

- Secure file upload and download
- Folder hierarchy
- File previews
- File expiration and retention
- Soft deletion and restoration
- Storage quota enforcement
- File access logging
- SHA-256 checksums
- ZIP export generation

### Multi-Tenant Authorization

The application separates data by firm account and supports multiple user
types with different permissions.

Authorization is enforced using Symfony Security and custom voters rather
than relying solely on UI-level restrictions.

### Electronic Signatures

Dropbox Sign integration supports:

- Uploaded PDF documents
- Predefined document templates
- Embedded signature request configuration
- Signature status tracking
- Webhook-driven updates

### Asynchronous Processing

Symfony Messenger handles background work including:

- Email delivery
- Audit logging
- File notifications
- ZIP generation

Failed background jobs are detected after retries are exhausted so
application state can be updated appropriately.

### Audit Logging

Important application actions are recorded through a centralized audit
logging service.

Audit events capture information such as:

- Action
- Resource
- User
- Timestamp
- Success/failure state
- Relevant metadata

### Messaging

The application includes an in-app messaging system with:

- Conversations
- Multiple participants
- Unread tracking
- Online presence
- Notifications
- Email digests

---

## Architecture

The application follows a layered Symfony architecture:

Browser
↓
Controller
↓
Service
↓
Repository / Doctrine
↓
Database

Long-running operations are moved outside the HTTP request lifecycle:

Controller
↓
Messenger Message
↓
Queue
↓
Message Handler
↓
Application Service

Third-party integrations are isolated behind dedicated service classes.

For additional details, see:

- `docs/architecture.md`
- `docs/security.md`
- `docs/async-processing.md`
- `docs/dropbox-sign-integration.md`

---

## Project Structure

```text
src/
├── Command/
├── Controller/
├── DropboxSign/
├── Entity/
├── Enum/
├── EventListener/
├── EventSubscriber/
├── Form/
├── Logging/
├── Message/
├── MessageHandler/
├── RemoteEvent/
├── Repository/
├── Scheduler/
├── Security/
├── Service/
├── Twig/
├── Utils/
└── Webhook/
```
