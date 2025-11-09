Mail client for repweb — setup and usage

Overview
--------
This module adds a simple IMAP/SMTP backed e-mail client to repweb. Incoming messages are fetched via IMAP and persisted to MariaDB; outgoing messages are sent using Symfony Mailer (SMTP).

Features
- Store incoming and outgoing mails in DB (`repweb_mig.mig_mail`).
- Attachments stored in `repweb_mig.mig_mail_attachment` as LONGBLOB.
- Tags stored as JSON (searchable via JSON_EXTRACT).
- Simple template + merge support (`mig_mail_template`).
- Send log & error handling in `mig_mail_sendlog`.

Database
--------
Run the SQL file `db/schema/repweb_mail.schema.sql` on your MariaDB server (schema `repweb_mig` must exist). Example:

  mysql -u root -p repweb < db/schema/repweb_mail.schema.sql

Dependencies
------------
- Symfony Mailer (should already be available in your Symfony app). Configure `MAILER_DSN` in `.env` / environment.
- PHP IMAP extension (for IMAP polling):
  - Rocky Linux 9: `sudo dnf install php-imap` then restart php-fpm/httpd
  - Debian/Ubuntu: `sudo apt install php-imap` and enable it in php.ini

Environment variables
- MAIL_IMAP_HOST (e.g. "imap.example.com:993/imap/ssl")
- MAIL_IMAP_USER
- MAIL_IMAP_PASS
- MAIL_IMAP_MAILBOX (optional, defaults to INBOX)
- MAILER_DSN (Symfony Mailer DSN; e.g. smtp://user:pass@smtp.example.com)

Commands
--------
- Poll IMAP and persist new messages:

    php bin/console mig:mail:poll-imap

API endpoints
-------------
- GET /api/mig/mail/ — list recent messages
- GET /api/mig/mail/{id} — view message
- POST /api/mig/mail/send — send a message (JSON)
- GET/POST /api/mig/mail/templates — list or create templates
- POST /api/mig/mail/templates/{id}/send — send template with merge map

Frontend
--------
A minimal React skeleton exists at `assets/react/mig/MailApp.tsx`. It uses a simple textarea as placeholder for a WYSIWYG editor. You can integrate Trumbowyg (jQuery) or a React-native WYSIWYG editor (Quill, TipTap, etc.). For Trumbowyg you'll need to add the JS/CSS and initialize the editor inside a React component.

The UI mounts at `/mig/mail` which renders `templates/mig/mail.html.twig`. The Encore entry `assets/react/mig/entry.tsx` looks for the container attribute `data-view="mail"` and swaps in the mail client automatically.

Security & notes
- Attachments stored as blobs—consider storing on disk or object storage for large volumes.
- JSON tag searches: use JSON_EXTRACT for queries. Example: `SELECT * FROM repweb_mig.mig_mail WHERE JSON_EXTRACT(tags, '$.AppCI') = '"Glob-Prisma"'`.
- Mail parsing in the example IMAP poller is intentionally simple; for production consider using a robust mail parsing library.

Next steps & improvements
- Add pagination and advanced search endpoints.
- Add streaming or chunked attachment download endpoints.
- Add UI for template creation and WYSIWYG integration (Trumbowyg or other).
- Add authorization checks on controller endpoints.

