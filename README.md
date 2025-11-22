# Threat Intelligence Portal

A lightweight static PHP interface for querying curated indicators of compromise (IoCs). Users can search for a specific URL, domain, IP address, or hash without ever seeing the full dataset.

## Running locally

1. Start PHP's built-in server from the project directory:
   ```bash
   php -S localhost:8000
   ```
2. Open `http://localhost:8000` in your browser.
3. Enter an IoC value in the search bar. If an exact match exists in `indicators.csv`, results appear with details, related data, and community notes. Otherwise, "Entry not found" is shown.

## Notes

- The CSV data is only used for lookup; the full list is never exposed in the UI.
- Add or modify IoC records in `indicators.csv` using the existing header row.
