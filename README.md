# Sentinel Threat Intel Portal

A static-friendly, production-ready threat intelligence workspace built with PHP, JavaScript, and CSS. All indicator data is powered by a single CSV file so you can deploy on Hostinger or any basic PHP host without additional services.

## Features
- Dark SOC-inspired UI with neon blue and purple accents.
- Dashboard analytics: totals, types, families, tags, confidence distribution, and recent indicators.
- Indicator repository with sorting, live search, filtering by type/tag/status/category, pagination, exports, and detail modals.
- Tag explorer and threat family rollups for rapid pivots.
- CSV-driven data pipeline—replace `indicators.csv` to refresh the platform with no code changes.
- Admin-only CSV upload with basic authentication and file safety checks (extension + MIME + `is_uploaded_file`).
- Fully responsive layout with smooth hover/animation states; no external frameworks.

## Running locally
1. Ensure PHP is available (e.g., `php -S localhost:8000`).
2. From the repository root, run: `php -S localhost:8000` and open http://localhost:8000.
3. Update `php/config.php` to set your site title, timezone, and admin password (replace the default `ChangeMe!2024`).

## Managing indicator data
- The platform reads from `indicators.csv` on every request. Updating the file instantly updates the UI.
- Keep the header row intact: `Indicator Type,Value,Threat Family,Source,First Seen,Last Seen,Confidence,Description,Tags,Status`.

## Admin upload flow
1. Navigate to **Upload** in the sidebar.
2. Authenticate with the admin password from `php/config.php`.
3. Upload a replacement CSV; the file is validated as CSV and atomically replaces `indicators.csv`.

## Notes
- No database is required or used.
- All interactivity (sorting, pagination, modal details, exports) is handled client-side, while PHP provides CSV parsing and server-side filtering support.

