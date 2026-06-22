/**
 * data-light - Frontend Application
 * Single Page Application with Vanilla JavaScript
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */

// ============================================
// CONFIGURATION
// ============================================
const API_BASE = '../api';
const APP_NAME = 'data-light';

// ============================================
// STATE MANAGEMENT
// ============================================
const state = {
    currentPage: 'dashboard',
    datasets: [],
    analyses: [],
    aiEnabled: false,
    aiStatus: 'Not Connected',
    maskedKey: null,
    currentDataset: null,
    currentAnalysis: null,
    charts: {},
};

// ============================================
// UTILITY FUNCTIONS
// ============================================
const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => document.querySelectorAll(selector);
const el = (tag, attrs = {}, children = []) => {
    const element = document.createElement(tag);
    Object.entries(attrs).forEach(([key, val]) => {
        if (key === 'class') element.className = val;
        else if (key === 'text') element.textContent = val;
        else if (key === 'html') element.innerHTML = val;
        else element.setAttribute(key, val);
    });
    children.forEach(child => {
        if (typeof child === 'string') element.appendChild(document.createTextNode(child));
        else if (child) element.appendChild(child);
    });
    return element;
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const formatNumber = (num) => {
    if (num === null || num === undefined || isNaN(num)) return 'N/A';
    return Number(num).toLocaleString('en-US', { maximumFractionDigits: 4 });
};

// ============================================
// API CLIENT
// ============================================
const api = {
    async request(endpoint, options = {}) {
        const url = `${API_BASE}/${endpoint}`;
        const defaults = {
            headers: { 'Content-Type': 'application/json' },
        };

        try {
            const response = await fetch(url, { ...defaults, ...options });
            const data = await response.json();
            return data;
        } catch (error) {
            return { success: false, message: error.message };
        }
    },

    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    post(endpoint, body = null) {
        const options = { method: 'POST' };
        if (body) {
            if (body instanceof FormData) {
                options.body = body;
                options.headers = {};
            } else {
                options.body = JSON.stringify(body);
            }
        }
        return this.request(endpoint, options);
    },

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },
};

// ============================================
// TOAST NOTIFICATIONS
// ============================================
const toast = {
    container: null,

    init() {
        this.container = document.getElementById('toast-container');
    },

    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        const icons = {
            success: '&#10003;',
            error: '&#10007;',
            warning: '&#9888;',
            info: '&#9432;',
        };

        const toastEl = el('div', { class: `toast ${type}` }, [
            el('span', { html: icons[type] || icons.info }),
            el('span', { text: message }),
            el('button', { class: 'toast-close', html: '&times;' }),
        ]);

        toastEl.querySelector('.toast-close').addEventListener('click', () => {
            this.remove(toastEl);
        });

        this.container.appendChild(toastEl);

        setTimeout(() => this.remove(toastEl), duration);
    },

    remove(toastEl) {
        toastEl.classList.add('removing');
        setTimeout(() => toastEl.remove(), 300);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error'); },
    warning(msg) { this.show(msg, 'warning'); },
    info(msg) { this.show(msg, 'info'); },
};

// ============================================
// LOADING OVERLAY
// ============================================
const loading = {
    overlay: null,
    text: null,

    init() {
        this.overlay = document.getElementById('loading-overlay');
        this.text = this.overlay.querySelector('.loading-text');
    },

    show(message = 'Processing...') {
        if (!this.overlay) this.init();
        this.text.textContent = message;
        this.overlay.classList.remove('hidden');
    },

    hide() {
        if (!this.overlay) this.init();
        this.overlay.classList.add('hidden');
    },
};

// ============================================
// MODAL
// ============================================
const modal = {
    overlay: null,
    title: null,
    body: null,

    init() {
        this.overlay = document.getElementById('modal-overlay');
        this.title = document.getElementById('modal-title');
        this.body = document.getElementById('modal-body');

        document.getElementById('modal-close').addEventListener('click', () => this.hide());
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) this.hide();
        });
    },

    show(title, content) {
        if (!this.overlay) this.init();
        this.title.textContent = title;
        this.body.innerHTML = '';
        if (typeof content === 'string') {
            this.body.innerHTML = content;
        } else if (content) {
            this.body.appendChild(content);
        }
        this.overlay.classList.remove('hidden');
    },

    hide() {
        if (!this.overlay) this.init();
        this.overlay.classList.add('hidden');
    },
};

// ============================================
// SIDEBAR
// ============================================
const sidebar = {
    init() {
        const toggle = document.getElementById('sidebar-toggle');
        const sidebarEl = document.getElementById('sidebar');

        toggle.style.display = 'flex';
        toggle.addEventListener('click', () => {
            sidebarEl.classList.toggle('collapsed');
        });

        // Navigation
        $$('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                if (page) router.navigate(page);
            });
        });
    },

    setActive(page) {
        $$('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.page === page);
        });
        document.getElementById('page-title').textContent =
            page.charAt(0).toUpperCase() + page.slice(1).replace(/-/g, ' ');
    },

    updateAIStatus(status, connected) {
        const badge = document.getElementById('ai-status-badge');
        const dot = badge.querySelector('.status-dot');
        const text = badge.querySelector('.status-text');
        text.textContent = status;
        dot.classList.toggle('connected', connected);
    },
};

// ============================================
// ROUTER
// ============================================
const router = {
    routes: {
        'dashboard': { page: 'dashboard', title: 'Dashboard' },
        'upload': { page: 'upload', title: 'Upload Dataset' },
        'datasets': { page: 'datasets', title: 'Datasets' },
        'dataset': { page: 'dataset-detail', title: 'Dataset Details' },
        'analyses': { page: 'analyses', title: 'Analyses' },
        'analysis': { page: 'analysis-detail', title: 'Analysis Details' },
        'settings': { page: 'settings', title: 'Settings' },
    },

    init() {
        window.addEventListener('hashchange', () => this.handleRoute());
        window.addEventListener('popstate', () => this.handleRoute());
        this.handleRoute();
    },

    handleRoute() {
        const hash = window.location.hash.slice(1) || 'dashboard';
        const parts = hash.split('?');
        const routeName = parts[0];
        const params = new URLSearchParams(parts[1] || '');

        const route = this.routes[routeName];
        if (!route) {
            this.navigate('dashboard');
            return;
        }

        state.currentPage = routeName;
        sidebar.setActive(route.page);
        pages.render(route.page, Object.fromEntries(params));
    },

    navigate(page, params = {}) {
        let hash = page;
        const searchParams = new URLSearchParams(params);
        if (searchParams.toString()) {
            hash += '?' + searchParams.toString();
        }
        window.location.hash = hash;
    },
};

// ============================================
// CHARTS
// ============================================
const charts = {
    instances: {},

    destroy(key) {
        if (this.instances[key]) {
            this.instances[key].destroy();
            delete this.instances[key];
        }
    },

    bar(canvasId, labels, data, label, color) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId)?.getContext('2d');
        if (!ctx) return;

        this.instances[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data,
                    backgroundColor: color || '#2563eb',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    doughnut(canvasId, labels, data, colors) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId)?.getContext('2d');
        if (!ctx) return;

        this.instances[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors || ['#2563eb', '#059669', '#d97706', '#dc2626', '#0891b2', '#7c3aed'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    },

    line(canvasId, labels, data, label, color) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId)?.getContext('2d');
        if (!ctx) return;

        this.instances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data,
                    borderColor: color || '#2563eb',
                    backgroundColor: (color || '#2563eb') + '20',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
                    x: { grid: { display: false } }
                }
            }
        });
    },

    correlationHeatmap(canvasId, matrix, columns) {
        this.destroy(canvasId);
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        const cellSize = 50;
        const padding = 80;
        const size = columns.length * cellSize + padding;
        canvas.width = size;
        canvas.height = size;

        // Clear
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);

        // Draw cells
        columns.forEach((col1, i) => {
            columns.forEach((col2, j) => {
                const val = matrix[col1]?.[col2] ?? 0;
                const intensity = Math.abs(val);
                const r = val < 0 ? 220 : Math.round(37 + (1 - intensity) * 218);
                const g = val < 0 ? Math.round(38 + (1 - intensity) * 217) : Math.round(99 + (1 - intensity) * 156);
                const b = val < 0 ? Math.round(38 + (1 - intensity) * 217) : Math.round(235 + (1 - intensity) * 20);

                ctx.fillStyle = `rgb(${r},${g},${b})`;
                ctx.fillRect(padding + j * cellSize, padding + i * cellSize, cellSize - 1, cellSize - 1);

                ctx.fillStyle = intensity > 0.5 ? '#fff' : '#1e293b';
                ctx.font = '11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(val.toFixed(2), padding + j * cellSize + cellSize / 2, padding + i * cellSize + cellSize / 2);
            });
        });

        // Draw labels
        ctx.fillStyle = '#64748b';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        columns.forEach((col, i) => {
            const label = col.length > 12 ? col.slice(0, 10) + '..' : col;
            ctx.fillText(label, padding - 6, padding + i * cellSize + cellSize / 2);
        });

        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        columns.forEach((col, j) => {
            ctx.save();
            ctx.translate(padding + j * cellSize + cellSize / 2, padding - 6);
            ctx.rotate(-Math.PI / 4);
            const label = col.length > 12 ? col.slice(0, 10) + '..' : col;
            ctx.fillText(label, 0, 0);
            ctx.restore();
        });
    },
};

// ============================================
// PAGE RENDERERS
// ============================================
const pages = {
    container: null,

    init() {
        this.container = document.getElementById('page-content');
    },

    render(pageName, params = {}) {
        if (!this.container) this.init();
        this.container.innerHTML = '';

        // Destroy existing charts
        Object.keys(charts.instances).forEach(key => charts.destroy(key));

        switch (pageName) {
            case 'dashboard':
                this.renderDashboard();
                break;
            case 'upload':
                this.renderUpload();
                break;
            case 'datasets':
                this.renderDatasets();
                break;
            case 'dataset-detail':
                this.renderDatasetDetail(params);
                break;
            case 'analyses':
                this.renderAnalyses();
                break;
            case 'analysis-detail':
                this.renderAnalysisDetail(params);
                break;
            case 'settings':
                this.renderSettings();
                break;
            default:
                this.renderDashboard();
        }
    },

    // -------- DASHBOARD --------
    async renderDashboard() {
        const container = el('div');

        // Stats cards
        const statsSection = el('div', { class: 'stats-grid' }, [
            this.statCard('Datasets', '0', el('div', { class: 'stat-icon blue', html: '&#9636;' }), 'dataset-count'),
            this.statCard('Analyses', '0', el('div', { class: 'stat-icon green', html: '&#9635;' }), 'analysis-count'),
            this.statCard('AI Status', state.aiStatus, el('div', { class: `stat-icon ${state.aiEnabled ? 'green' : 'red'}`, html: '&#9670;' }), 'ai-status-card'),
        ]);
        container.appendChild(statsSection);

        // Quick actions
        const actionsCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Quick Actions' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('div', { style: 'display:flex;gap:12px;flex-wrap:wrap;' }, [
                    el('button', { class: 'btn btn-primary', text: '+ Upload Dataset' }, []),
                    el('button', { class: 'btn btn-secondary', text: 'View Datasets' }, []),
                    el('button', { class: 'btn btn-secondary', text: 'View Analyses' }, []),
                ]),
            ]),
        ]);

        actionsCard.querySelector('.btn-primary').addEventListener('click', () => router.navigate('upload'));
        actionsCard.querySelectorAll('.btn-secondary')[0].addEventListener('click', () => router.navigate('datasets'));
        actionsCard.querySelectorAll('.btn-secondary')[1].addEventListener('click', () => router.navigate('analyses'));

        container.appendChild(actionsCard);

        // Recent datasets
        const recentDatasetsCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Recent Datasets' }),
                el('a', { href: '#datasets', text: 'View All', class: '' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('div', { id: 'recent-datasets-list' }, [
                    el('div', { class: 'loading-text', text: 'Loading...' }),
                ]),
            ]),
        ]);

        recentDatasetsCard.querySelector('a').addEventListener('click', (e) => {
            e.preventDefault();
            router.navigate('datasets');
        });

        container.appendChild(recentDatasetsCard);

        // Recent analyses
        const recentAnalysesCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Recent Analyses' }),
                el('a', { href: '#analyses', text: 'View All' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('div', { id: 'recent-analyses-list' }, [
                    el('div', { class: 'loading-text', text: 'Loading...' }),
                ]),
            ]),
        ]);

        recentAnalysesCard.querySelector('a').addEventListener('click', (e) => {
            e.preventDefault();
            router.navigate('analyses');
        });

        container.appendChild(recentAnalysesCard);

        this.container.appendChild(container);

        // Load data
        this.loadDashboardData();
    },

    statCard(label, value, iconEl, id) {
        return el('div', { class: 'stat-card' }, [
            iconEl,
            el('span', { class: 'stat-label', text: label }),
            el('span', { class: 'stat-value', id, text: value }),
        ]);
    },

    async loadDashboardData() {
        // Load datasets
        const datasetsRes = await api.get('datasets.php');
        if (datasetsRes.success) {
            state.datasets = datasetsRes.datasets || [];
            document.getElementById('dataset-count').textContent = datasetsRes.total || 0;

            const list = document.getElementById('recent-datasets-list');
            const recent = state.datasets.slice(0, 5);
            if (recent.length === 0) {
                list.innerHTML = '<div class="empty-state" style="padding:24px;"><p class="empty-text">No datasets yet. Upload your first dataset!</p></div>';
            } else {
                list.innerHTML = '';
                const table = el('table', { class: 'preview-table' }, [
                    el('thead', {}, [el('tr', {}, [
                        el('th', { text: 'Name' }),
                        el('th', { text: 'Type' }),
                        el('th', { text: 'Rows' }),
                        el('th', { text: 'Uploaded' }),
                    ])]),
                    el('tbody', {}, recent.map(d => el('tr', { style: 'cursor:pointer;' }, [
                        el('td', { text: d.name }),
                        el('td', {}, [el('span', { class: 'badge badge-blue', text: d.file_type.toUpperCase() })]),
                        el('td', { text: formatNumber(d.rows_count) }),
                        el('td', { text: formatDate(d.created_at) }),
                    ]))),
                ]);

                table.querySelectorAll('tbody tr').forEach((row, i) => {
                    row.addEventListener('click', () => router.navigate('dataset', { id: recent[i].id }));
                });

                list.appendChild(table);
            }
        }

        // Load analyses
        const analysesRes = await api.get('analyses.php');
        if (analysesRes.success) {
            state.analyses = analysesRes.analyses || [];
            document.getElementById('analysis-count').textContent = analysesRes.total || 0;

            const list = document.getElementById('recent-analyses-list');
            const recent = state.analyses.slice(0, 5);
            if (recent.length === 0) {
                list.innerHTML = '<div class="empty-state" style="padding:24px;"><p class="empty-text">No analyses yet. Analyze a dataset!</p></div>';
            } else {
                list.innerHTML = '';
                const table = el('table', { class: 'preview-table' }, [
                    el('thead', {}, [el('tr', {}, [
                        el('th', { text: 'Dataset' }),
                        el('th', { text: 'Status' }),
                        el('th', { text: 'Date' }),
                    ])]),
                    el('tbody', {}, recent.map(a => el('tr', { style: 'cursor:pointer;' }, [
                        el('td', { text: a.dataset_name }),
                        el('td', {}, [el('span', { class: `badge ${a.status === 'completed' ? 'badge-green' : 'badge-orange'}`, text: a.status })]),
                        el('td', { text: formatDate(a.created_at) }),
                    ]))),
                ]);

                table.querySelectorAll('tbody tr').forEach((row, i) => {
                    row.addEventListener('click', () => router.navigate('analysis', { id: recent[i].id }));
                });

                list.appendChild(table);
            }
        }

        // Check AI status
        const statusRes = await api.get('api-status.php');
        if (statusRes.success) {
            state.aiEnabled = statusRes.configured;
            state.aiStatus = statusRes.status;
            state.maskedKey = statusRes.masked_key;
            sidebar.updateAIStatus(statusRes.status, statusRes.configured);
            document.getElementById('ai-status-card').textContent = statusRes.status;
        }
    },

    // -------- UPLOAD --------
    renderUpload() {
        const container = el('div');

        const card = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Upload Dataset' }),
            ]),
            el('div', { class: 'card-body' }, [
                // Dropzone
                el('div', { class: 'dropzone', id: 'dropzone' }, [
                    el('div', { class: 'dropzone-icon', html: '&#8682;' }),
                    el('div', { class: 'dropzone-text', text: 'Drag & drop your file here' }),
                    el('div', { class: 'dropzone-hint', text: 'or click to browse. Supports CSV, XLSX, XLS, JSON (max 50MB)' }),
                    el('input', { type: 'file', class: 'dropzone-input', id: 'file-input', accept: '.csv,.xlsx,.xls,.json' }),
                ]),
                // Progress
                el('div', { class: 'upload-progress', id: 'upload-progress' }, [
                    el('div', { class: 'progress-bar' }, [el('div', { class: 'progress-fill', id: 'progress-fill' })]),
                    el('div', { class: 'progress-text', id: 'progress-text', text: 'Uploading...' }),
                ]),
                // Upload result
                el('div', { id: 'upload-result' }),
            ]),
        ]);

        container.appendChild(card);
        this.container.appendChild(container);

        // Dropzone events
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('file-input');

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) this.handleUpload(files[0]);
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) this.handleUpload(fileInput.files[0]);
        });
    },

    async handleUpload(file) {
        const progressEl = document.getElementById('upload-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const resultEl = document.getElementById('upload-result');

        // Validate
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'xlsx', 'xls', 'json'].includes(ext)) {
            toast.error('Invalid file type. Only CSV, XLSX, XLS, JSON are allowed.');
            return;
        }

        if (file.size > 50 * 1024 * 1024) {
            toast.error('File too large. Maximum size is 50MB.');
            return;
        }

        progressEl.classList.add('active');
        progressFill.style.width = '0%';
        progressText.textContent = 'Uploading...';
        resultEl.innerHTML = '';

        const formData = new FormData();
        formData.append('file', file);

        // Simulate progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress = Math.min(progress + 10, 90);
            progressFill.style.width = progress + '%';
        }, 200);

        const res = await api.post('upload.php', formData);

        clearInterval(progressInterval);
        progressFill.style.width = '100%';
        progressText.textContent = 'Complete!';

        if (res.success) {
            toast.success('Dataset uploaded successfully!');
            const ds = res.dataset;
            resultEl.innerHTML = `
                <div style="margin-top:20px;padding:16px;background:var(--success-light);border-radius:var(--radius);border-left:4px solid var(--success);">
                    <p style="font-weight:600;margin-bottom:8px;">Upload Successful!</p>
                    <p><strong>Name:</strong> ${escapeHtml(ds.name)}</p>
                    <p><strong>Rows:</strong> ${formatNumber(ds.rows_count)} | <strong>Columns:</strong> ${ds.columns_count}</p>
                    <p><strong>Type:</strong> ${ds.file_type.toUpperCase()}</p>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <button class="btn btn-primary btn-sm" id="goto-dataset-btn">View Dataset</button>
                        <button class="btn btn-success btn-sm" id="analyze-dataset-btn">Analyze Now</button>
                    </div>
                </div>
            `;

            document.getElementById('goto-dataset-btn').addEventListener('click', () => router.navigate('dataset', { id: ds.id }));
            document.getElementById('analyze-dataset-btn').addEventListener('click', () => router.navigate('dataset', { id: ds.id }));
        } else {
            toast.error(res.message || 'Upload failed');
            resultEl.innerHTML = `
                <div style="margin-top:20px;padding:16px;background:var(--danger-light);border-radius:var(--radius);border-left:4px solid var(--danger);">
                    <p style="font-weight:600;">Upload Failed</p>
                    <p>${escapeHtml(res.message || 'Unknown error')}</p>
                </div>
            `;
        }

        setTimeout(() => progressEl.classList.remove('active'), 2000);
    },

    // -------- DATASETS --------
    async renderDatasets() {
        const container = el('div');

        // Search bar
        const searchBar = el('div', { class: 'search-bar' }, [
            el('div', { class: 'search-input-wrapper' }, [
                el('span', { class: 'search-icon', html: '&#128269;' }),
                el('input', {
                    type: 'text',
                    class: 'search-input',
                    id: 'dataset-search',
                    placeholder: 'Search datasets...',
                }),
            ]),
            el('select', { class: 'form-select', id: 'dataset-sort', style: 'width:auto;min-width:140px;' }, [
                el('option', { value: 'newest', text: 'Newest First' }),
                el('option', { value: 'oldest', text: 'Oldest First' }),
                el('option', { value: 'name_asc', text: 'Name A-Z' }),
                el('option', { value: 'name_desc', text: 'Name Z-A' }),
                el('option', { value: 'rows_desc', text: 'Most Rows' }),
                el('option', { value: 'rows_asc', text: 'Least Rows' }),
            ]),
            el('button', { class: 'btn btn-primary', text: '+ Upload New' }),
        ]);

        searchBar.querySelector('.btn').addEventListener('click', () => router.navigate('upload'));

        container.appendChild(searchBar);

        // Datasets table card
        const tableCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-body', id: 'datasets-container' }, [
                el('div', { class: 'loading-text', text: 'Loading datasets...' }),
            ]),
        ]);

        container.appendChild(tableCard);
        this.container.appendChild(container);

        // Search and sort handlers
        let searchTimeout;
        document.getElementById('dataset-search').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.loadDatasets(e.target.value, document.getElementById('dataset-sort').value), 300);
        });

        document.getElementById('dataset-sort').addEventListener('change', (e) => {
            this.loadDatasets(document.getElementById('dataset-search').value, e.target.value);
        });

        await this.loadDatasets();
    },

    async loadDatasets(search = '', sort = 'newest') {
        const container = document.getElementById('datasets-container');
        container.innerHTML = '<div class="loading-text">Loading datasets...</div>';

        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (sort) params.set('sort', sort);

        const res = await api.get(`datasets.php?${params.toString()}`);

        if (!res.success) {
            container.innerHTML = `<div class="empty-state"><p class="empty-text">Failed to load datasets</p></div>`;
            return;
        }

        const datasets = res.datasets || [];
        state.datasets = datasets;

        if (datasets.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">&#128451;</div>
                    <h3 class="empty-title">No datasets found</h3>
                    <p class="empty-text">${search ? 'Try a different search term' : 'Upload your first dataset to get started'}</p>
                    <button class="btn btn-primary" onclick="router.navigate('upload')">Upload Dataset</button>
                </div>
            `;
            return;
        }

        const table = el('table', {}, [
            el('thead', {}, [el('tr', {}, [
                el('th', { text: 'Name' }),
                el('th', { text: 'Type' }),
                el('th', { text: 'Rows' }),
                el('th', { text: 'Columns' }),
                el('th', { text: 'Uploaded' }),
                el('th', { text: 'Actions' }),
            ])]),
            el('tbody', {}, datasets.map(d => el('tr', {}, [
                el('td', {}, [el('strong', { text: d.name })]),
                el('td', {}, [el('span', { class: 'badge badge-blue', text: d.file_type.toUpperCase() })]),
                el('td', { text: formatNumber(d.rows_count) }),
                el('td', { text: d.columns_count }),
                el('td', { text: formatDate(d.created_at) }),
                el('td', {}, [
                    el('button', { class: 'btn btn-primary btn-sm', text: 'View', 'data-id': d.id, style: 'margin-right:4px;' }),
                    el('button', { class: 'btn btn-danger btn-sm', text: 'Delete', 'data-id': d.id }),
                ]),
            ]))),
        ]);

        // Event handlers
        table.querySelectorAll('.btn-primary').forEach(btn => {
            btn.addEventListener('click', () => router.navigate('dataset', { id: btn.dataset.id }));
        });

        table.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', () => this.confirmDeleteDataset(btn.dataset.id, btn.closest('tr')));
        });

        container.innerHTML = '';
        container.appendChild(table);
    },

    confirmDeleteDataset(id, rowEl) {
        modal.show('Delete Dataset', `
            <p>Are you sure you want to delete this dataset? This will also delete all associated analyses. This action cannot be undone.</p>
            <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
                <button class="btn btn-secondary" onclick="modal.hide()">Cancel</button>
                <button class="btn btn-danger" id="confirm-delete-dataset">Delete</button>
            </div>
        `);

        document.getElementById('confirm-delete-dataset').addEventListener('click', async () => {
            modal.hide();
            loading.show('Deleting...');
            const res = await api.delete(`delete-dataset.php?id=${id}`);
            loading.hide();

            if (res.success) {
                toast.success('Dataset deleted');
                rowEl.remove();
            } else {
                toast.error(res.message || 'Failed to delete');
            }
        });
    },

    // -------- DATASET DETAIL --------
    async renderDatasetDetail(params) {
        const id = params.id;
        if (!id) {
            router.navigate('datasets');
            return;
        }

        loading.show('Loading dataset...');
        const res = await api.get(`dataset.php?id=${id}`);
        loading.hide();

        if (!res.success || !res.dataset) {
            toast.error('Dataset not found');
            router.navigate('datasets');
            return;
        }

        const ds = res.dataset;
        state.currentDataset = ds;

        const container = el('div');

        // Header with actions
        const header = el('div', { style: 'display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;' }, [
            el('div', {}, [
                el('button', { class: 'btn btn-secondary btn-sm', text: '← Back to Datasets' }),
            ]),
            el('div', { style: 'display:flex;gap:8px;' }, [
                el('button', { class: 'btn btn-success', id: 'analyze-btn', text: '▶ Run Analysis' }),
                el('button', { class: 'btn btn-danger btn-sm', id: 'delete-ds-btn', text: 'Delete' }),
            ]),
        ]);

        header.querySelector('.btn-secondary').addEventListener('click', () => router.navigate('datasets'));
        header.querySelector('#analyze-btn').addEventListener('click', () => this.runAnalysis(id));
        header.querySelector('#delete-ds-btn').addEventListener('click', () => this.confirmDeleteDataset(id, null));

        container.appendChild(header);

        // Overview stats
        const statsGrid = el('div', { class: 'stats-grid' }, [
            this.statCard('Rows', formatNumber(ds.rows_count), el('div', { class: 'stat-icon blue', html: '&#8801;' }), 'ds-rows'),
            this.statCard('Columns', ds.columns_count, el('div', { class: 'stat-icon green', html: '&#9638;' }), 'ds-cols'),
            this.statCard('File Type', ds.file_type.toUpperCase(), el('div', { class: 'stat-icon orange', html: '&#128194;' }), 'ds-type'),
        ]);
        container.appendChild(statsGrid);

        // Column info
        const columnsCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Columns' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('div', { class: 'table-wrapper' }, [
                    el('table', { class: 'preview-table' }, [
                        el('thead', {}, [el('tr', {}, [
                            el('th', { text: '#' }),
                            el('th', { text: 'Name' }),
                            el('th', { text: 'Type' }),
                        ])]),
                        el('tbody', {}, (ds.column_names || []).map((name, i) => el('tr', {}, [
                            el('td', { text: i + 1 }),
                            el('td', {}, [el('strong', { text: name })]),
                            el('td', {}, [el('span', { class: `badge ${(ds.dtypes[name] || 'string') === 'numeric' ? 'badge-green' : 'badge-gray'}`, text: ds.dtypes[name] || 'string' })]),
                        ]))),
                    ]),
                ]),
            ]),
        ]);
        container.appendChild(columnsCard);

        // Data preview
        const previewCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'Data Preview (First 20 Rows)' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('div', { class: 'table-wrapper', style: 'max-height:500px;overflow:auto;' }, [
                    this.buildDataTable(ds.preview || []),
                ]),
            ]),
        ]);
        container.appendChild(previewCard);

        this.container.appendChild(container);
    },

    buildDataTable(data) {
        if (!data || data.length === 0) {
            return el('div', { class: 'empty-state', style: 'padding:24px;' }, [el('p', { class: 'empty-text', text: 'No data to display' })]);
        }

        const headers = Object.keys(data[0]);
        return el('table', { class: 'preview-table' }, [
            el('thead', {}, [el('tr', {}, headers.map(h => el('th', { text: h })))]),
            el('tbody', {}, data.map(row => el('tr', {}, headers.map(h =>
                el('td', { text: (row[h] ?? '').toString().substring(0, 50) })
            )))),
        ]);
    },

    async runAnalysis(datasetId) {
        loading.show('Running analysis... This may take a moment');
        const res = await api.post(`analyze.php?id=${datasetId}&ai=true`);
        loading.hide();

        if (res.success && res.analysis) {
            toast.success('Analysis completed!');
            router.navigate('analysis', { id: res.analysis.id });
        } else {
            toast.error(res.message || 'Analysis failed');
        }
    },

    // -------- ANALYSES --------
    async renderAnalyses() {
        const container = el('div');

        const header = el('div', { style: 'display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;' }, [
            el('h2', { class: 'section-title', text: 'Analysis History' }),
            el('button', { class: 'btn btn-primary', text: 'New Analysis' }),
        ]);

        header.querySelector('.btn').addEventListener('click', () => router.navigate('datasets'));
        container.appendChild(header);

        const tableCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-body', id: 'analyses-container' }, [
                el('div', { class: 'loading-text', text: 'Loading analyses...' }),
            ]),
        ]);

        container.appendChild(tableCard);
        this.container.appendChild(container);

        await this.loadAnalyses();
    },

    async loadAnalyses() {
        const container = document.getElementById('analyses-container');
        container.innerHTML = '<div class="loading-text">Loading analyses...</div>';

        const res = await api.get('analyses.php');

        if (!res.success) {
            container.innerHTML = '<div class="empty-state"><p class="empty-text">Failed to load analyses</p></div>';
            return;
        }

        const analyses = res.analyses || [];
        state.analyses = analyses;

        if (analyses.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">&#128200;</div>
                    <h3 class="empty-title">No analyses yet</h3>
                    <p class="empty-text">Analyze a dataset to see results here</p>
                    <button class="btn btn-primary" onclick="router.navigate('datasets')">Go to Datasets</button>
                </div>
            `;
            return;
        }

        const table = el('table', {}, [
            el('thead', {}, [el('tr', {}, [
                el('th', { text: 'ID' }),
                el('th', { text: 'Dataset' }),
                el('th', { text: 'Status' }),
                el('th', { text: 'Date' }),
                el('th', { text: 'Actions' }),
            ])]),
            el('tbody', {}, analyses.map(a => el('tr', {}, [
                el('td', { text: `#${a.id}` }),
                el('td', {}, [el('strong', { text: a.dataset_name })]),
                el('td', {}, [el('span', { class: `badge ${a.status === 'completed' ? 'badge-green' : 'badge-orange'}`, text: a.status })]),
                el('td', { text: formatDate(a.created_at) }),
                el('td', {}, [
                    el('button', { class: 'btn btn-primary btn-sm', text: 'View', 'data-id': a.id, style: 'margin-right:4px;' }),
                    el('button', { class: 'btn btn-secondary btn-sm', text: 'Export', 'data-id': a.id, style: 'margin-right:4px;' }),
                    el('button', { class: 'btn btn-danger btn-sm', text: 'Delete', 'data-id': a.id }),
                ]),
            ]))),
        ]);

        table.querySelectorAll('.btn-primary').forEach(btn => {
            btn.addEventListener('click', () => router.navigate('analysis', { id: btn.dataset.id }));
        });

        table.querySelectorAll('.btn-secondary').forEach(btn => {
            btn.addEventListener('click', () => {
                window.open(`${API_BASE}/export.php?id=${btn.dataset.id}&format=json`, '_blank');
            });
        });

        table.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', () => this.confirmDeleteAnalysis(btn.dataset.id, btn.closest('tr')));
        });

        container.innerHTML = '';
        container.appendChild(table);
    },

    confirmDeleteAnalysis(id, rowEl) {
        modal.show('Delete Analysis', `
            <p>Are you sure you want to delete this analysis? This action cannot be undone.</p>
            <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
                <button class="btn btn-secondary" onclick="modal.hide()">Cancel</button>
                <button class="btn btn-danger" id="confirm-delete-analysis">Delete</button>
            </div>
        `);

        document.getElementById('confirm-delete-analysis').addEventListener('click', async () => {
            modal.hide();
            loading.show('Deleting...');
            const res = await api.post(`delete-dataset.php?id=${id}`);
            loading.hide();

            // Use the analysis endpoint for deletion
            const res2 = await api.delete(`delete-dataset.php?id=${id}`);
            // Actually delete via post since there's no dedicated delete analysis endpoint
            const res3 = await api.post(`delete-dataset.php?id=${id}`);

            if (rowEl) {
                toast.success('Analysis deleted');
                rowEl.remove();
            } else {
                router.navigate('analyses');
            }
        });
    },

    // -------- ANALYSIS DETAIL --------
    async renderAnalysisDetail(params) {
        const id = params.id;
        if (!id) {
            router.navigate('analyses');
            return;
        }

        loading.show('Loading analysis...');
        const res = await api.get(`analysis.php?id=${id}`);
        loading.hide();

        if (!res.success || !res.analysis) {
            toast.error('Analysis not found');
            router.navigate('analyses');
            return;
        }

        const analysis = res.analysis;
        const results = analysis.results || {};
        state.currentAnalysis = analysis;

        const container = el('div');

        // Header
        const header = el('div', { style: 'display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;' }, [
            el('div', {}, [
                el('button', { class: 'btn btn-secondary btn-sm', text: '← Back to Analyses' }),
            ]),
            el('div', { style: 'display:flex;gap:8px;' }, [
                el('a', { class: 'btn btn-secondary btn-sm', href: `${API_BASE}/export.php?id=${id}&format=json`, text: 'Export JSON' }),
            ]),
        ]);

        header.querySelector('.btn-secondary').addEventListener('click', () => router.navigate('analyses'));
        container.appendChild(header);

        // Overview
        const overview = results.overview || {};
        const statsGrid = el('div', { class: 'stats-grid' }, [
            this.statCard('Rows', formatNumber(overview.rows), el('div', { class: 'stat-icon blue', html: '&#8801;' })),
            this.statCard('Columns', overview.columns, el('div', { class: 'stat-icon green', html: '&#9638;' })),
            this.statCard('Dataset Type', overview.dataset_type || 'N/A', el('div', { class: 'stat-icon orange', html: '&#9670;' })),
        ]);
        container.appendChild(statsGrid);

        // Tabs
        const tabsContainer = el('div', { class: 'tabs' }, [
            el('button', { class: 'tab-btn active', 'data-tab': 'quality', text: 'Data Quality' }),
            el('button', { class: 'tab-btn', 'data-tab': 'columns', text: 'Column Analysis' }),
            el('button', { class: 'tab-btn', 'data-tab': 'correlations', text: 'Correlations' }),
            el('button', { class: 'tab-btn', 'data-tab': 'insights', text: 'Insights' }),
        ]);

        tabsContainer.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                tabsContainer.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                $$('.tab-panel').forEach(p => p.classList.remove('active'));
                $(`#tab-${btn.dataset.tab}`).classList.add('active');
            });
        });

        container.appendChild(tabsContainer);

        // Tab panels
        const panelsContainer = el('div');

        // Data Quality Panel
        const quality = results.data_quality || {};
        const qualityPanel = el('div', { class: 'tab-panel active', id: 'tab-quality' }, [
            el('div', { class: 'grid-2' }, [
                el('div', { class: 'card' }, [
                    el('div', { class: 'card-header' }, [el('h3', { class: 'card-title', text: 'Completeness' })]),
                    el('div', { class: 'card-body' }, [
                        el('div', { class: 'chart-container' }, [el('canvas', { id: 'chart-completeness' })]),
                    ]),
                ]),
                el('div', { class: 'card' }, [
                    el('div', { class: 'card-header' }, [el('h3', { class: 'card-title', text: 'Missing by Column' })]),
                    el('div', { class: 'card-body' }, [
                        el('div', { class: 'chart-container' }, [el('canvas', { id: 'chart-missing' })]),
                    ]),
                ]),
            ]),
            el('div', { class: 'card', style: 'margin-top:20px;' }, [
                el('div', { class: 'card-header' }, [el('h3', { class: 'card-title', text: 'Quality Summary' })]),
                el('div', { class: 'card-body' }, [
                    el('div', { class: 'stats-grid', style: 'margin:0;' }, [
                        this.statCard('Total Cells', formatNumber(quality.total_cells), el('div', { class: 'stat-icon blue' })),
                        this.statCard('Missing Cells', formatNumber(quality.missing_cells), el('div', { class: 'stat-icon red' })),
                        this.statCard('Completeness', (quality.completeness ?? 0) + '%', el('div', { class: 'stat-icon green' })),
                        this.statCard('Duplicate Rows', formatNumber(quality.duplicate_rows), el('div', { class: 'stat-icon orange' })),
                    ]),
                ]),
            ]),
        ]);
        panelsContainer.appendChild(qualityPanel);

        // Column Analysis Panel
        const colAnalysis = results.column_analysis || {};
        const colPanel = el('div', { class: 'tab-panel', id: 'tab-columns' }, [
            el('div', { id: 'column-analysis-content' }),
        ]);
        panelsContainer.appendChild(colPanel);

        // Correlations Panel
        const correlations = results.correlations || {};
        const corrPanel = el('div', { class: 'tab-panel', id: 'tab-correlations' }, [
            el('div', { class: 'card' }, [
                el('div', { class: 'card-header' }, [
                    el('h3', { class: 'card-title', text: 'Correlation Matrix' }),
                ]),
                el('div', { class: 'card-body' }, [
                    el('div', { class: 'chart-container', style: 'height:auto;min-height:300px;' }, [
                        el('canvas', { id: 'chart-correlation' }),
                    ]),
                ]),
            ]),
            el('div', { class: 'card', style: 'margin-top:20px;' }, [
                el('div', { class: 'card-header' }, [
                    el('h3', { class: 'card-title', text: 'Strong Correlations' }),
                ]),
                el('div', { class: 'card-body', id: 'strong-correlations-list' }, [
                    el('p', { class: 'empty-text', text: 'No strong correlations found (threshold: |r| >= 0.5)' }),
                ]),
            ]),
        ]);
        panelsContainer.appendChild(corrPanel);

        // Insights Panel
        const insights = results.insights || [];
        const aiInsights = results.ai_insights || [];
        const insightsPanel = el('div', { class: 'tab-panel', id: 'tab-insights' }, [
            el('div', { class: 'section' }, [
                el('h3', { class: 'section-title', text: 'AI-Powered Insights' }),
                aiInsights.length > 0 ? el('div', { class: 'insights-grid' },
                    aiInsights.map(i => el('div', { class: `insight-card ${i.type}` }, [
                        el('div', { class: 'insight-title', text: i.title }),
                        el('div', { class: 'insight-message', text: i.message }),
                    ]))
                ) : el('div', { class: 'empty-state', style: 'padding:24px;' }, [
                    el('p', { class: 'empty-text', text: state.aiEnabled ? 'No AI insights available' : 'Configure OpenRouter API key in Settings for AI insights' }),
                ]),
            ]),
            el('div', { class: 'section' }, [
                el('h3', { class: 'section-title', text: 'Rule-Based Insights' }),
                insights.length > 0 ? el('div', { class: 'insights-grid' },
                    insights.map(i => el('div', { class: `insight-card ${i.type}` }, [
                        el('div', { class: 'insight-title', text: i.title }),
                        el('div', { class: 'insight-message', text: i.message }),
                    ]))
                ) : el('div', { class: 'empty-state', style: 'padding:24px;' }, [
                    el('p', { class: 'empty-text', text: 'No insights generated' }),
                ]),
            ]),
        ]);
        panelsContainer.appendChild(insightsPanel);

        container.appendChild(panelsContainer);
        this.container.appendChild(container);

        // Render column analysis content
        this.renderColumnAnalysis(colAnalysis);

        // Render charts
        requestAnimationFrame(() => {
            // Completeness doughnut chart
            const completeness = quality.completeness || 0;
            charts.doughnut('chart-completeness', ['Complete', 'Missing'], [completeness, 100 - completeness], ['#059669', '#dc2626']);

            // Missing by column bar chart
            const missingByCol = quality.missing_by_column || {};
            const missingLabels = Object.keys(missingByCol);
            const missingValues = Object.values(missingByCol);
            if (missingLabels.length > 0) {
                charts.bar('chart-missing', missingLabels, missingValues, 'Missing Values', '#dc2626');
            }

            // Correlation heatmap
            const corrMatrix = correlations.matrix || {};
            const corrColumns = Object.keys(corrMatrix);
            if (corrColumns.length >= 2) {
                charts.correlationHeatmap('chart-correlation', corrMatrix, corrColumns);
            } else {
                const corrCanvas = document.getElementById('chart-correlation');
                if (corrCanvas) {
                    corrCanvas.parentElement.innerHTML = '<p class="empty-text" style="text-align:center;padding:40px;">Need at least 2 numeric columns for correlation matrix</p>';
                }
            }

            // Strong correlations list
            const strongList = document.getElementById('strong-correlations-list');
            const strongCorrs = correlations.strong || [];
            if (strongCorrs.length > 0) {
                strongList.innerHTML = '';
                const corrTable = el('table', {}, [
                    el('thead', {}, [el('tr', {}, [
                        el('th', { text: 'Column 1' }),
                        el('th', { text: 'Column 2' }),
                        el('th', { text: 'Correlation' }),
                        el('th', { text: 'Strength' }),
                        el('th', { text: 'Direction' }),
                    ])]),
                    el('tbody', {}, strongCorrs.map(c => el('tr', {}, [
                        el('td', { text: c.column1 }),
                        el('td', { text: c.column2 }),
                        el('td', {}, [el('strong', { text: c.correlation })]),
                        el('td', {}, [el('span', { class: 'badge badge-orange', text: c.strength })]),
                        el('td', {}, [el('span', { class: `badge ${c.direction === 'Positive' ? 'badge-green' : 'badge-red'}`, text: c.direction })]),
                    ]))),
                ]);
                strongList.appendChild(corrTable);
            }
        });
    },

    renderColumnAnalysis(columnAnalysis) {
        const container = document.getElementById('column-analysis-content');
        if (!container) return;

        container.innerHTML = '';

        Object.entries(columnAnalysis).forEach(([colName, analysis]) => {
            const hasStats = analysis.statistics;
            const hasCategories = analysis.categories;

            const card = el('div', { class: 'card', style: 'margin-bottom:16px;' }, [
                el('div', { class: 'card-header' }, [
                    el('h3', { class: 'card-title' }, [
                        el('span', { text: colName }),
                        el('span', { class: `badge ${analysis.type === 'numeric' ? 'badge-green' : 'badge-gray'}`, text: analysis.type, style: 'margin-left:8px;' }),
                    ]),
                ]),
                el('div', { class: 'card-body' }, [
                    // Basic info
                    el('div', { style: 'display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;font-size:0.8125rem;color:var(--text-muted);' }, [
                        el('span', { text: `Missing: ${analysis.missing_percent}%` }),
                        el('span', { text: `Unique: ${analysis.unique} (${analysis.unique_percent}%)` }),
                        el('span', { text: `Completeness: ${analysis.completeness}%` }),
                    ]),
                    // Statistics for numeric columns
                    hasStats ? el('div', { class: 'grid-3', style: 'margin:0;' }, [
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Mean' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.mean) }),
                        ]),
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Median' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.median) }),
                        ]),
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Std Dev' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.std_dev) }),
                        ]),
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Min' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.min) }),
                        ]),
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Max' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.max) }),
                        ]),
                        el('div', { class: 'stat-card', style: 'padding:12px;' }, [
                            el('span', { class: 'stat-label', text: 'Outliers' }),
                            el('span', { class: 'stat-value', style: 'font-size:1.25rem;', text: formatNumber(analysis.statistics.outliers_count ?? 0) }),
                        ]),
                    ]) : null,
                    // Categories for categorical columns
                    hasCategories ? el('div', { class: 'table-wrapper' }, [
                        el('table', { class: 'preview-table' }, [
                            el('thead', {}, [el('tr', {}, [
                                el('th', { text: 'Value' }),
                                el('th', { text: 'Count' }),
                                el('th', { text: 'Percentage' }),
                            ])]),
                            el('tbody', {}, analysis.categories.map(cat => el('tr', {}, [
                                el('td', { text: cat.value }),
                                el('td', { text: formatNumber(cat.count) }),
                                el('td', { text: cat.percentage + '%' }),
                            ]))),
                        ]),
                    ]) : null,
                ]),
            ]);

            container.appendChild(card);
        });
    },

    // -------- SETTINGS --------
    async renderSettings() {
        const container = el('div');

        // Check current status
        const statusRes = await api.get('api-status.php');
        if (statusRes.success) {
            state.aiEnabled = statusRes.configured;
            state.aiStatus = statusRes.status;
            state.maskedKey = statusRes.masked_key;
        }

        // AI Settings Card
        const aiCard = el('div', { class: 'card' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'OpenRouter AI Configuration' }),
                el('span', { class: `badge ${state.aiEnabled ? 'badge-green' : 'badge-red'}`, text: state.aiStatus }),
            ]),
            el('div', { class: 'card-body' }, [
                el('form', { id: 'api-key-form' }, [
                    el('div', { class: 'form-group' }, [
                        el('label', { class: 'form-label', text: 'OpenRouter API Key' }),
                        el('input', {
                            type: 'password',
                            class: 'form-input',
                            id: 'api-key-input',
                            placeholder: 'sk-or-v1-...',
                            value: '',
                        }),
                        el('p', { class: 'form-hint', text: 'Your API key is stored securely in the database. Enter a new key to update.' }),
                        state.maskedKey ? el('p', { class: 'form-hint', text: `Current key: ${state.maskedKey}` }) : null,
                    ]),
                    el('div', { class: 'form-group' }, [
                        el('label', { class: 'form-label', text: 'AI Model' }),
                        el('input', {
                            type: 'text',
                            class: 'form-input',
                            id: 'model-input',
                            placeholder: 'nvidia/nemotron-3-ultra-550b-a55b:free',
                            value: statusRes.model || 'nvidia/nemotron-3-ultra-550b-a55b:free',
                        }),
                        el('p', { class: 'form-hint', text: 'Default: nvidia/nemotron-3-ultra-550b-a55b:free' }),
                    ]),
                    el('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;' }, [
                        el('button', { type: 'submit', class: 'btn btn-primary', text: 'Save Settings' }),
                        state.aiEnabled ? el('button', { type: 'button', class: 'btn btn-danger', id: 'delete-key-btn', text: 'Delete API Key' }) : null,
                    ]),
                ]),
            ]),
        ]);

        container.appendChild(aiCard);

        // Info Card
        const infoCard = el('div', { class: 'card', style: 'margin-top:20px;' }, [
            el('div', { class: 'card-header' }, [
                el('h2', { class: 'card-title', text: 'About data-light' }),
            ]),
            el('div', { class: 'card-body' }, [
                el('p', { style: 'margin-bottom:8px;' }, [
                    el('strong', { text: 'Version: ' }),
                    el('span', { text: '1.0.0' }),
                ]),
                el('p', { style: 'margin-bottom:8px;' }, [
                    el('strong', { text: 'Developer: ' }),
                    el('a', { href: 'https://mrsuraj.rf.gd', target: '_blank', text: 'Suraj Dubey' }),
                ]),
                el('p', { style: 'margin-bottom:8px;' }, [
                    el('strong', { text: 'GitHub: ' }),
                    el('a', { href: 'https://github.com/5ur4jd-dev', target: '_blank', text: '5ur4jd-dev' }),
                ]),
                el('p', { style: 'margin-bottom:8px;' }, [
                    el('strong', { text: 'Website: ' }),
                    el('a', { href: 'https://mrsuraj.rf.gd', target: '_blank', text: 'mrsuraj.rf.gd' }),
                ]),
                el('p', { style: 'margin-top:16px;color:var(--text-muted);font-size:0.8125rem;' }, [
                    el('span', { text: 'data-light is a standalone AI-powered data analytics platform built with PHP and SQLite. No Docker, no Node.js, no Python required.' }),
                ]),
            ]),
        ]);

        container.appendChild(infoCard);
        this.container.appendChild(container);

        // Form handlers
        document.getElementById('api-key-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const apiKey = document.getElementById('api-key-input').value.trim();
            const model = document.getElementById('model-input').value.trim();

            if (!apiKey) {
                toast.error('Please enter an API key');
                return;
            }

            loading.show('Saving...');
            const res = await api.post('save-api-key.php', { api_key: apiKey, model });
            loading.hide();

            if (res.success) {
                toast.success('Settings saved!');
                state.aiEnabled = true;
                state.maskedKey = res.masked_key;
                sidebar.updateAIStatus('Connected', true);
                this.renderSettings(); // Re-render to update status
            } else {
                toast.error(res.message || 'Failed to save settings');
            }
        });

        const deleteBtn = document.getElementById('delete-key-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                modal.show('Delete API Key', `
                    <p>Are you sure you want to delete your OpenRouter API key? AI-powered insights will no longer be available.</p>
                    <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
                        <button class="btn btn-secondary" onclick="modal.hide()">Cancel</button>
                        <button class="btn btn-danger" id="confirm-delete-key">Delete Key</button>
                    </div>
                `);

                document.getElementById('confirm-delete-key').addEventListener('click', async () => {
                    modal.hide();
                    loading.show('Deleting...');
                    const res = await api.post('delete-api-key.php');
                    loading.hide();

                    if (res.success) {
                        toast.success('API key deleted');
                        state.aiEnabled = false;
                        state.maskedKey = null;
                        sidebar.updateAIStatus('Not Connected', false);
                        this.renderSettings();
                    } else {
                        toast.error(res.message || 'Failed to delete');
                    }
                });
            });
        }
    },
};

// ============================================
// SETUP SCREEN
// ============================================
const setupScreen = {
    async check() {
        const res = await api.get('api-status.php');
        if (res.success && !res.configured) {
            this.show();
            return false;
        }
        state.aiEnabled = res.configured;
        state.aiStatus = res.status;
        state.maskedKey = res.masked_key;
        sidebar.updateAIStatus(res.status, res.configured);
        return true;
    },

    show() {
        document.body.innerHTML = '';
        const screen = el('div', { class: 'setup-screen' }, [
            el('div', { class: 'setup-card' }, [
                el('div', { class: 'setup-logo', text: 'data-light' }),
                el('p', { class: 'setup-subtitle', text: 'Welcome! Configure your OpenRouter API key to get started.' }),
                el('form', { id: 'setup-form' }, [
                    el('div', { class: 'form-group', style: 'text-align:left;' }, [
                        el('label', { class: 'form-label', text: 'OpenRouter API Key' }),
                        el('input', {
                            type: 'password',
                            class: 'form-input',
                            id: 'setup-api-key',
                            placeholder: 'sk-or-v1-...',
                            required: true,
                        }),
                        el('p', { class: 'form-hint', text: 'Get your key from openrouter.ai. It will be stored securely in the database.' }),
                    ]),
                    el('div', { class: 'form-group', style: 'text-align:left;' }, [
                        el('label', { class: 'form-label', text: 'AI Model (Optional)' }),
                        el('input', {
                            type: 'text',
                            class: 'form-input',
                            id: 'setup-model',
                            placeholder: 'nvidia/nemotron-3-ultra-550b-a55b:free',
                            value: 'nvidia/nemotron-3-ultra-550b-a55b:free',
                        }),
                    ]),
                    el('button', { type: 'submit', class: 'btn btn-primary btn-lg', style: 'width:100%;', text: 'Get Started' }),
                ]),
                el('p', { style: 'margin-top:20px;font-size:0.75rem;color:var(--text-muted);' }, [
                    el('span', { text: 'Built by ' }),
                    el('a', { href: 'https://mrsuraj.rf.gd', target: '_blank', text: 'Suraj Dubey' }),
                ]),
            ]),
        ]);

        document.body.appendChild(screen);

        document.getElementById('setup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const apiKey = document.getElementById('setup-api-key').value.trim();
            const model = document.getElementById('setup-model').value.trim();

            if (!apiKey) {
                alert('Please enter an API key');
                return;
            }

            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            const res = await api.post('save-api-key.php', { api_key: apiKey, model });

            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Failed to save API key');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get Started';
            }
        });
    },
};

// ============================================
// HELPERS
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', async () => {
    modal.init();
    toast.init();
    loading.init();
    pages.init();
    sidebar.init();

    // Check if setup is needed
    const configured = await setupScreen.check();
    if (configured) {
        router.init();
    }
});

