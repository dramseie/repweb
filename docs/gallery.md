# Photo Gallery Module

## Overview

The gallery module provides drag-and-drop image uploads, layout management, and NAS-backed storage for `/gallery`.

## Storage

- Base directory: `/mnt/photo` (configurable via `GALLERY_STORAGE_PATH` environment variable).
- Uploaded files are written under `gallery/<year>/<month>/` inside the NAS mount.
- File metadata is persisted to the `gallery_photo` table.

## API

| Method | Path | Description |
| ------ | ---- | ----------- |
| `GET` | `/api/gallery/photos` | List gallery photos with metadata and layout settings. |
| `POST` | `/api/gallery/photos` | Upload one or more images (`files[]`). |
| `PATCH` | `/api/gallery/photos/layout` | Persist drag/drop layout and ordering. |
| `PATCH` | `/api/gallery/photos/{id}` | Update title/caption metadata. |
| `DELETE` | `/api/gallery/photos/{id}` | Remove an image and NAS file. |

Image content is streamed via `GET /gallery/media/{id}` for authenticated users.

## Frontend

- React app mounts in `templates/gallery/index.html.twig` on `#gallery-root`.
- Provides drag-and-drop upload, inline editing, deletion, and grid layout tweaks.
- Layout persistence uses `react-grid-layout` with collision prevention.

## Deployment steps

1. Install JavaScript dependencies if needed (`npm install`).
2. Rebuild assets: `npm run build`.
3. Run Doctrine migration: `php bin/console doctrine:migrations:migrate`.
4. Ensure `/mnt/photo` is mounted with write permissions for the web runtime user.

## Configuration

- Override storage location via `GALLERY_STORAGE_PATH` in environment.
- Access to `/gallery` and related API routes requires authenticated users (`ROLE_USER`).
