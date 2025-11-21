const modal = document.getElementById('detailModal');
const closeModal = document.getElementById('closeModal');
const modalBody = document.getElementById('modalBody');
const modalSubtitle = document.getElementById('modalSubtitle');
const table = document.querySelector('#iocTable tbody');
const pagination = document.getElementById('pagination');
const exportBtn = document.getElementById('exportCsv');
let currentSort = { key: null, asc: true };
let currentPage = 1;
const pageSize = 8;

// If the indicators table isn't present (e.g., dashboard/about pages), only enable tag/category pivots.
if (!table) {
    attachTagFilters();
    attachCategoryFilters();
    if (exportBtn) {
        exportBtn.addEventListener('click', () => exportCsvData(indicatorData));
    }
    return;
}

function buildRows(data) {
    table.innerHTML = '';
    data.forEach((row) => {
        const tr = document.createElement('tr');
        tr.dataset.category = row._category || '';
        tr.dataset.meta = JSON.stringify(row);
        tr.innerHTML = `
            <td class="value-cell">${row['Value']}</td>
            <td>${row['Indicator Type']}</td>
            <td>${row['Threat Family']}</td>
            <td>${renderTags(row['Tags'])}</td>
            <td><span class="badge">${row['Confidence']}</span></td>
            <td>${row['Last Seen']}</td>
            <td>${row['Status']}</td>
        `;
        tr.addEventListener('click', () => showModal(row));
        table.appendChild(tr);
    });
}

function renderTags(tagString) {
    if (!tagString) return '';
    return tagString.split(',').map(t => t.trim()).filter(Boolean).map(tag => `<span class="tag clickable" data-tag="${tag}">${tag}</span>`).join('');
}

function showModal(meta) {
    modalBody.innerHTML = '';
    const entries = Object.entries(meta);
    entries.forEach(([key, value]) => {
        const wrap = document.createElement('div');
        wrap.className = 'row';
        wrap.innerHTML = `<div class="label">${key}</div><div>${value}</div>`;
        modalBody.appendChild(wrap);
    });
    modalSubtitle.textContent = `${meta['Indicator Type']} • ${meta['Threat Family']}`;
    modal.classList.add('show');
}

closeModal?.addEventListener('click', () => modal.classList.remove('show'));
modal?.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('show'); });

document.querySelectorAll('#iocTable th').forEach((th) => {
    th.addEventListener('click', () => {
        const key = th.dataset.sort;
        if (!key) return;
        currentSort.asc = currentSort.key === key ? !currentSort.asc : true;
        currentSort.key = key;
        const sorted = [...indicatorData].sort((a,b) => {
            const av = (a[key] || '').toString().toLowerCase();
            const bv = (b[key] || '').toString().toLowerCase();
            if (av === bv) return 0;
            return currentSort.asc ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1);
        });
        renderPage(sorted, 1);
    });
});

function renderPage(data, page) {
    currentPage = page;
    const enriched = data.map(row => ({ ...row, _category: categorize(row['Indicator Type']) }));
    const start = (page - 1) * pageSize;
    const subset = enriched.slice(start, start + pageSize);
    buildRows(subset);
    buildPagination(enriched.length);
    attachTagFilters();
    attachCategoryFilters();
}

function buildPagination(total) {
    pagination.innerHTML = '';
    const pages = Math.max(1, Math.ceil(total / pageSize));
    for (let i = 1; i <= pages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.className = i === currentPage ? 'active' : '';
        btn.addEventListener('click', () => renderPage(applyQuickFilters(indicatorData), i));
        pagination.appendChild(btn);
    }
}

function categorize(type) {
    const mapping = {
        'hashes': ['md5','sha1','sha256','hash'],
        'domains': ['domain'],
        'urls': ['url','uri'],
        'ip addresses': ['ip','ipv4','ipv6'],
        'emails': ['email'],
        'file names': ['filename','file name'],
        'registry keys': ['registry','regkey'],
        'behavior signatures': ['behavior','signature']
    };
    const t = (type || '').toLowerCase();
    for (const [category, keys] of Object.entries(mapping)) {
        if (keys.some(k => t.includes(k))) return capitalize(category);
    }
    return 'Other';
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function attachTagFilters() {
    document.querySelectorAll('.tag.clickable').forEach(tag => {
        tag.addEventListener('click', (e) => {
            e.stopPropagation();
            const value = tag.dataset.tag;
            const url = new URL(window.location.href);
            url.searchParams.set('page', 'indicators');
            url.searchParams.set('tag', value);
            window.location.href = url.toString();
        });
    });
}

function attachCategoryFilters() {
    document.querySelectorAll('.category-badge').forEach(badge => {
        badge.addEventListener('click', () => {
            const cat = badge.dataset.category;
            const url = new URL(window.location.href);
            url.searchParams.set('page', 'indicators');
            url.searchParams.set('category', cat);
            window.location.href = url.toString();
        });
    });
}

function applyQuickFilters(data) {
    const searchInput = document.querySelector('input[name="q"]');
    const q = (searchInput?.value || '').toLowerCase();
    const categoryParam = new URL(window.location.href).searchParams.get('category');
    return data.filter(row => {
        const matchSearch = !q || Object.values(row).join(' ').toLowerCase().includes(q);
        const matchCategory = !categoryParam || categorize(row['Indicator Type']) === categoryParam;
        return matchSearch && matchCategory;
    });
}

function exportCsvData(data) {
    const headers = Object.keys(data[0] || {});
    const csvRows = [headers.join(',')];
    data.forEach(row => {
        const vals = headers.map(h => `"${(row[h] || '').toString().replace(/"/g, '""')}"`);
        csvRows.push(vals.join(','));
    });
    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'indicators-export.csv';
    a.click();
    URL.revokeObjectURL(url);
}

if (exportBtn) {
    exportBtn.addEventListener('click', () => exportCsvData(indicatorData));
}

renderPage(indicatorData, 1);
attachTagFilters();
attachCategoryFilters();
