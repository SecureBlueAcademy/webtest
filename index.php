<?php
$csvPath = __DIR__ . '/indicators.csv';

function loadIndicators(string $path): array {
    if (!file_exists($path)) {
        return [];
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        fclose($handle);
        return [];
    }

    $indicators = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($headers)) {
            continue;
        }
        $record = array_combine($headers, $row);
        $ioc = trim($record['ioc'] ?? '');
        if ($ioc === '') {
            continue;
        }
        $indicators[$ioc] = $record;
    }

    fclose($handle);

    return $indicators;
}

function splitList(?string $value): array {
    if ($value === null || trim($value) === '') {
        return [];
    }

    $items = array_map('trim', explode('|', $value));
    return array_values(array_filter($items, static function ($item) {
        return $item !== '';
    }));
}

$indicators = loadIndicators($csvPath);
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$match = null;

if ($query !== '') {
    foreach ($indicators as $ioc => $data) {
        if ($ioc === $query) {
            $match = $data;
            break;
        }
    }
}

function renderValue(?string $value): string {
    return htmlspecialchars($value ?? '—', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threat Intelligence Portal</title>
    <style>
        :root {
            --bg: #0b1724;
            --panel: #132333;
            --accent: #66d9ef;
            --accent-strong: #36c3ff;
            --text: #e7f0f7;
            --muted: #9db2c4;
            --danger: #f56c6c;
            --border: #1f3346;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(54,195,255,0.1), transparent 25%),
                        radial-gradient(circle at 80% 10%, rgba(102,217,239,0.12), transparent 25%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        header {
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            display: inline-block;
        }

        main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 32px 60px;
        }

        .hero {
            margin-top: 40px;
            padding: 36px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 2rem;
        }

        .hero p {
            margin: 0 0 24px;
            color: var(--muted);
        }

        form.search {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        form.search input[type="text"] {
            flex: 1;
            min-width: 280px;
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #0f1c2a;
            color: var(--text);
            font-size: 1rem;
            outline: none;
        }

        form.search input[type="text"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 217, 239, 0.15);
        }

        form.search button {
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            border: none;
            border-radius: 10px;
            color: #04101c;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.08s ease, box-shadow 0.08s ease;
        }

        form.search button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(54, 195, 255, 0.3);
        }

        .results {
            margin-top: 28px;
        }

        .ioc-header {
            padding: 24px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
        }

        .ioc-header h2 {
            margin: 0 0 6px;
            font-size: 1.6rem;
            word-break: break-all;
        }

        .ioc-header .meta-line {
            color: var(--muted);
            margin: 0;
            font-size: 0.95rem;
        }

        .tags {
            margin-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tag {
            padding: 6px 10px;
            border-radius: 20px;
            background: rgba(102, 217, 239, 0.12);
            color: var(--accent);
            border: 1px solid rgba(102, 217, 239, 0.3);
            font-size: 0.9rem;
        }

        .tabs {
            margin-top: 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
        }

        .tab-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--border);
            background: #0f1f30;
        }

        .tab-button {
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            color: var(--muted);
            border: none;
            background: transparent;
            transition: background 0.12s ease, color 0.12s ease;
        }

        .tab-button.active {
            color: var(--text);
            background: #10263b;
            border-bottom: 2px solid var(--accent);
        }

        .tab-content {
            display: none;
            padding: 18px 20px 24px;
        }

        .tab-content.active {
            display: block;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .detail-card {
            padding: 12px 14px;
            background: #0f1c2a;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .detail-card .label {
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 4px;
        }

        .detail-card .value {
            font-weight: 600;
        }

        .list-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .pill-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pill {
            padding: 10px 12px;
            background: #0f1c2a;
            border-radius: 10px;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .muted {
            color: var(--muted);
        }

        .not-found {
            padding: 18px;
            border-radius: 12px;
            background: rgba(245, 108, 108, 0.12);
            border: 1px solid rgba(245, 108, 108, 0.4);
            color: #ffc6c6;
            margin-top: 18px;
        }

        @media (max-width: 640px) {
            header { padding: 16px 20px; }
            main { padding: 14px 20px 40px; }
            .hero { padding: 24px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand"><span class="dot"></span>Threat Intelligence Portal</div>
    </header>
    <main>
        <section class="hero">
            <h1>Search indicators of compromise</h1>
            <p>Look up URLs, domains, IPs, or hashes across curated intelligence without exposing the full dataset.</p>
            <form class="search" method="get" action="">
                <input type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search for URL, domain, IP, MD5, SHA1, SHA256" aria-label="Search for indicator">
                <button type="submit">Search</button>
            </form>
            <?php if ($query !== '' && $match === null): ?>
                <div class="not-found">Entry not found</div>
            <?php endif; ?>
        </section>

        <?php if ($match !== null): ?>
            <section class="results" aria-live="polite">
                <div class="ioc-header">
                    <h2><?= renderValue($match['ioc'] ?? ''); ?></h2>
                    <p class="meta-line">Exact match found in curated intelligence</p>
                    <?php $tags = splitList($match['tags'] ?? ''); if (!empty($tags)): ?>
                        <div class="tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="tag"><?= renderValue($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tabs">
                    <div class="tab-buttons" role="tablist">
                        <button class="tab-button active" data-tab="details" role="tab" aria-selected="true">Details</button>
                        <button class="tab-button" data-tab="related" role="tab" aria-selected="false">Related Data</button>
                        <button class="tab-button" data-tab="community" role="tab" aria-selected="false">Community</button>
                    </div>
                    <div class="tab-content active" id="details" role="tabpanel">
                        <div class="detail-grid">
                            <div class="detail-card">
                                <div class="label">Type</div>
                                <div class="value"><?= renderValue($match['type'] ?? ''); ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="label">Threat Name</div>
                                <div class="value"><?= renderValue($match['threat_name'] ?? ''); ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="label">Severity</div>
                                <div class="value"><?= renderValue($match['severity'] ?? ''); ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="label">First Seen</div>
                                <div class="value"><?= renderValue($match['first_seen'] ?? ''); ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="label">Last Seen</div>
                                <div class="value"><?= renderValue($match['last_seen'] ?? ''); ?></div>
                            </div>
                            <div class="detail-card">
                                <div class="label">Confidence</div>
                                <div class="value"><?= renderValue($match['confidence'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-content" id="related" role="tabpanel">
                        <?php $related = splitList($match['related_data'] ?? ''); ?>
                        <?php if (!empty($related)): ?>
                            <div class="list-section">
                                <?php foreach ($related as $item): ?>
                                    <div class="pill"><?= renderValue($item); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted">No related data available for this indicator.</p>
                        <?php endif; ?>
                    </div>
                    <div class="tab-content" id="community" role="tabpanel">
                        <?php $community = splitList($match['community'] ?? ''); ?>
                        <?php if (!empty($community)): ?>
                            <div class="list-section">
                                <?php foreach ($community as $note): ?>
                                    <div class="pill"><?= renderValue($note); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted">No community notes have been added yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script>
        document.querySelectorAll('.tab-button').forEach(function(button) {
            button.addEventListener('click', function() {
                var tabName = this.getAttribute('data-tab');

                document.querySelectorAll('.tab-button').forEach(function(btn) {
                    btn.classList.toggle('active', btn === button);
                    btn.setAttribute('aria-selected', btn === button ? 'true' : 'false');
                });

                document.querySelectorAll('.tab-content').forEach(function(content) {
                    content.classList.toggle('active', content.id === tabName);
                });
            });
        });
    </script>
</body>
</html>
