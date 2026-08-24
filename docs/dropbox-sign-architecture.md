# Dropbox Sign Integration

## Purpose

The application integrates with Dropbox Sign to allow firms to create electronic signature requests from either uploaded PDFs or pre-approved document templates.

## Workflow

User selects or uploads PDF
→ Symfony validates the document
→ Application calls Dropbox Sign
→ Dropbox Sign returns an embedded claim URL
→ Browser launches embedded signing setup
→ Dropbox Sign sends webhook events
→ Application updates the SignatureRequest record

## Document Sources

The integration supports two document sources:

1. A PDF uploaded by the user
2. A template already stored by the application

Both sources are normalized to a local filesystem path before being passed to the Dropbox Sign client.

This keeps the lower-level integration independent from how the file entered the application.

## Webhooks

Webhook events are used to synchronize external signature state with local application state.

The application does not rely solely on browser redirects to determine whether a signature request has completed.

## Error Handling

External API failures are caught and translated into application-level exceptions. Users receive a generic error message while detailed information is retained in logs and audit metadata.

API credentials and production identifiers are excluded from this repository.
