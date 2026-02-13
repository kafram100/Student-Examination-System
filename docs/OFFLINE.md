# Offline Mode (Lecturer)

This project includes offline-first support for lecturer workflows. When the network drops, edits are stored locally and synced once the connection is back.

## What Works Offline
- Create and edit exams (no file uploads).
- Add and edit questions (no file uploads, no CSV import).
- See pending sync count and use the "Sync Now" button on the dashboard.

## What Does Not Work Offline
- Uploading files (assessment files, question files, student file submissions).
- CSV import for MCQ questions.
- Student exam attempts (students use the separate autosave/resume flow).

## How It Works
- Local storage uses IndexedDB via `js/offline-db.js`.
- Sync manager is implemented in `js/sync-manager.js`.
- Server endpoints are `api/sync.php` (push queued operations) and `api/fetch-data.php` (refresh cached data).
- Database tables auto-created by `db.php` are `sync_queue` and `sync_metadata`.

## UI Behavior
- The dashboard shows online/offline status.
- Pending sync count appears when there are queued changes.
- A "Sync Now" button appears when items are pending.

## Testing
1. Open the dashboard and create an exam while online.
2. Turn off your network and create another exam without uploading files.
3. You should see the pending sync count increase.
4. Turn the network back on and click "Sync Now".

## Troubleshooting
- If sync never completes, check browser console for errors and ensure you are logged in.
- Verify IndexedDB is enabled in your browser.
- Confirm `api/sync.php` and `api/fetch-data.php` are accessible.
