# Architecture

## Overview

This project is a sanitized portfolio version of a multi-tenant secure file exchange platform built with PHP, Symfony, Doctrine ORM, MariaDB, and JavaScript.

The application separates users and data by firm account and uses role-based authorization for administrators, firm users, and client users.

## Application Flow

A typical request follows this pattern:

Browser
→ Symfony Controller
→ Application Service
→ Repository / Doctrine ORM
→ Database

For asynchronous work:

Controller
→ Messenger Message
→ Queue
→ Message Handler
→ Service
→ Database / External API

For third-party integrations:

Controller / Service
→ Dropbox Sign API
→ Webhook
→ Application State Update
→ Notification / Audit Log

## Main Components

### Controllers

Controllers handle HTTP concerns, validate access, delegate business logic to services, and return HTML or JSON responses.

### Services

Business logic is placed in dedicated services for concerns such as file handling, audit logging, PDF processing, notifications, and external API integrations.

### Persistence

Doctrine ORM is used for entities and relationships, while custom repositories contain query logic and aggregate queries.

### Security

Symfony Security and voters are used for resource-level authorization. Tenant-owned resources are checked against the current firm before access is granted.

### Background Processing

Symfony Messenger handles asynchronous operations such as notifications, audit logging, email delivery, and long-running file operations.

## Design Goals

- Keep controllers thin
- Separate infrastructure from business logic
- Enforce authorization at the resource level
- Keep external integrations behind service boundaries
- Make asynchronous failures observable and recoverable
