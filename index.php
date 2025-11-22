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

function getDetailFields(array $record): array {
    $type = strtolower(trim($record['type'] ?? ''));

    $common = [
        ['label' => 'Indicator Type', 'value' => $record['type'] ?? ''],
        ['label' => 'Threat Name', 'value' => $record['threat_name'] ?? ''],
        ['label' => 'Severity', 'value' => $record['severity'] ?? ''],
        ['label' => 'First Seen', 'value' => $record['first_seen'] ?? ''],
        ['label' => 'Last Seen', 'value' => $record['last_seen'] ?? ''],
    ];

    if (in_array($type, ['ip', 'ipv4', 'ipv6'], true)) {
        $specific = [
            ['label' => 'Geolocation', 'value' => $record['geo'] ?? ''],
            ['label' => 'ASN', 'value' => $record['asn'] ?? ''],
            ['label' => 'Provider', 'value' => $record['provider'] ?? ''],
            ['label' => 'Behavior', 'value' => $record['behavior'] ?? ''],
        ];
    } elseif ($type === 'domain') {
        $specific = [
            ['label' => 'Registrar', 'value' => $record['registrar'] ?? ''],
            ['label' => 'Domain Age', 'value' => $record['domain_age'] ?? ''],
            ['label' => 'Hosting Geo', 'value' => $record['geo'] ?? ''],
            ['label' => 'SSL Issuer', 'value' => $record['ssl_issuer'] ?? ''],
            ['label' => 'Behavior', 'value' => $record['behavior'] ?? ''],
        ];
    } elseif ($type === 'url') {
        $specific = [
            ['label' => 'HTTP Status', 'value' => $record['url_status'] ?? ''],
            ['label' => 'SSL Issuer', 'value' => $record['ssl_issuer'] ?? ''],
            ['label' => 'Hosting ASN', 'value' => $record['asn'] ?? ''],
            ['label' => 'Hosting Geo', 'value' => $record['geo'] ?? ''],
            ['label' => 'Behavior', 'value' => $record['behavior'] ?? ''],
        ];
    } else {
        $specific = [
            ['label' => 'File Name', 'value' => $record['file_name'] ?? ''],
            ['label' => 'File Type', 'value' => $record['file_type'] ?? ''],
            ['label' => 'File Size', 'value' => $record['file_size'] ?? ''],
            ['label' => 'IMPHASH', 'value' => $record['imphash'] ?? ''],
            ['label' => 'Behavior', 'value' => $record['behavior'] ?? ''],
        ];
    }

    return array_merge($common, $specific);
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

        .result-top {
            display: grid;
            grid-template-columns: minmax(200px, 240px) 1fr;
            gap: 16px;
            align-items: stretch;
        }

        .score-card {
            padding: 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 10px;
        }

        .score-ring {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: conic-gradient(var(--danger) calc(var(--percent) * 1%), #0f1c2a 0);
            display: grid;
            place-items: center;
            position: relative;
        }

        .score-ring::after {
            content: '';
            position: absolute;
            inset: 10px;
            background: var(--panel);
            border-radius: 50%;
        }

        .score-inner {
            position: relative;
            display: grid;
            place-items: center;
            gap: 2px;
        }

        .score-caption {
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 700;
        }

        .score-number {
            position: relative;
            font-size: 2.2rem;
            font-weight: 700;
            color: #f36c6c;
        }

        .score-total {
            position: relative;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .score-label {
            font-weight: 600;
            color: var(--muted);
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
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .list-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .related-group {
            padding: 12px 14px;
            background: #0f1c2a;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .related-group .label {
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-size: 0.8rem;
        }

        .community-card {
            padding: 12px 14px;
            background: #0f1c2a;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: grid;
            gap: 6px;
        }

        .community-title {
            font-weight: 700;
        }

        .community-note {
            color: var(--muted);
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
            word-break: break-word;
            overflow-wrap: anywhere;
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
            .result-top { grid-template-columns: 1fr; }
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
                <?php
                    $confidenceValue = isset($match['confidence']) ? (float) $match['confidence'] : 0;
                    $clampedConfidence = max(0, min(100, $confidenceValue));
                ?>
                <div class="result-top">
                    <div class="score-card">
                        <div class="score-ring" style="--percent: <?= $clampedConfidence; ?>;">
                            <div class="score-inner">
                                <div class="score-caption">Confidence</div>
                                <div class="score-number"><?= (int) round($clampedConfidence); ?></div>
                                <div class="score-total">/ 100</div>
                            </div>
                        </div>
                        <div class="score-label">Confidence Score</div>
                    </div>
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
                </div>

                <div class="tabs">
                    <div class="tab-buttons" role="tablist">
                        <button class="tab-button active" data-tab="details" role="tab" aria-selected="true">Details</button>
                        <button class="tab-button" data-tab="related" role="tab" aria-selected="false">Related Data</button>
                        <button class="tab-button" data-tab="community" role="tab" aria-selected="false">Community</button>
                    </div>
                    <div class="tab-content active" id="details" role="tabpanel">
                        <?php $detailFields = getDetailFields($match); ?>
                        <div class="detail-grid">
                            <?php foreach ($detailFields as $detail): ?>
                                <div class="detail-card">
                                    <div class="label"><?= renderValue($detail['label'] ?? ''); ?></div>
                                    <div class="value"><?= renderValue($detail['value'] ?? ''); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tab-content" id="related" role="tabpanel">
                        <?php
                            $relatedFiles = splitList($match['related_files'] ?? '');
                            $relatedIps = splitList($match['related_ips'] ?? '');
                            $relatedDomains = splitList($match['related_domains'] ?? '');
                            $relatedUrls = splitList($match['related_urls'] ?? '');
                        ?>
                        <div class="related-grid">
                            <div class="related-group">
                                <div class="label">Related Files &amp; Hashes</div>
                                <?php if (!empty($relatedFiles)): ?>
                                    <div class="pill-row">
                                        <?php foreach ($relatedFiles as $item): ?>
                                            <div class="pill"><?= renderValue($item); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">No file artifacts linked.</p>
                                <?php endif; ?>
                            </div>
                            <div class="related-group">
                                <div class="label">Related IPs</div>
                                <?php if (!empty($relatedIps)): ?>
                                    <div class="pill-row">
                                        <?php foreach ($relatedIps as $item): ?>
                                            <div class="pill"><?= renderValue($item); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">No IPs associated.</p>
                                <?php endif; ?>
                            </div>
                            <div class="related-group">
                                <div class="label">Related Domains</div>
                                <?php if (!empty($relatedDomains)): ?>
                                    <div class="pill-row">
                                        <?php foreach ($relatedDomains as $item): ?>
                                            <div class="pill"><?= renderValue($item); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">No domains associated.</p>
                                <?php endif; ?>
                            </div>
                            <div class="related-group">
                                <div class="label">Related URLs</div>
                                <?php if (!empty($relatedUrls)): ?>
                                    <div class="pill-row">
                                        <?php foreach ($relatedUrls as $item): ?>
                                            <div class="pill"><?= renderValue($item); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">No URLs associated.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-content" id="community" role="tabpanel">
                        <?php $community = splitList($match['community'] ?? ''); ?>
                        <?php if (!empty($community)): ?>
                            <div class="list-section">
                                <?php foreach ($community as $note): ?>
                                    <?php $parts = explode('::', $note, 2); ?>
                                    <div class="community-card">
                                        <div class="community-title"><?= renderValue($parts[0] ?? 'Community insight'); ?></div>
                                        <?php if (isset($parts[1])): ?>
                                            <div class="community-note"><?= renderValue($parts[1]); ?></div>
                                        <?php endif; ?>
                                    </div>
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
