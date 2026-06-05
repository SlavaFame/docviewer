<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/SecurityManager.php';
require_once __DIR__ . '/classes/DocumentManager.php';

class ViewerController
{
    private SecurityManager $security;
    private DocumentManager $docManager;

    public function __construct()
    {
        $this->security   = new SecurityManager();
        $this->docManager = new DocumentManager();
    }

    public function run(): void
    {
        $id  = $_GET['id'] ?? '';
        $doc = $this->docManager->getById($id);

        if (!$doc) { $this->renderNotFound(); return; }

        $token = $this->security->createToken($doc->id);
        $type  = match($doc->extension) {
            'pdf'  => 'pdf',
            'docx' => 'docx',
            'doc'  => 'doc',
            'xlsx' => 'xlsx',
            'xls'  => 'xls',
            default => 'unknown',
        };

        $this->renderHead('Просмотр: ' . $doc->originalName);
        ?>

<div class="page-content">

    <!-- Header -->
    <div class="page-title-box">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="doc-icon <?= $doc->extension ?>" style="width:46px;height:46px;font-size:1.35rem">
                <i class="<?= $doc->iconClass() ?>"></i>
            </div>
            <div>
                <h4 class="page-title mb-1"
                    style="max-width:580px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($doc->originalName) ?>">
                    <?= htmlspecialchars($doc->originalName) ?>
                </h4>
                <div class="d-flex gap-3 flex-wrap" style="font-size:.76rem;color:var(--text-muted)">
                    <span><i class="uil-weight me-1"></i><?= $doc->formattedSize() ?></span>
                    <span><i class="uil-calendar-alt me-1"></i><?= $doc->formattedDate() ?></span>
                    <span class="badge bg-<?= $doc->badgeClass() ?> text-uppercase"><?= $doc->extension ?></span>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="security-badge"><i class="uil-lock-alt me-1"></i>Только просмотр</span>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="uil-arrow-left me-1"></i>Назад
            </a>
        </div>
    </div>

    <!-- Viewer card -->
    <div class="card">
        <!-- Toolbar -->
        <div class="card-header viewer-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- PDF controls -->
                <div class="toolbar-group" id="pdf-controls" style="display:none">
                    <button class="toolbar-btn" id="prev-page"><i class="uil-angle-left"></i></button>
                    <span id="page-info" class="page-info">Стр. 1 / ?</span>
                    <button class="toolbar-btn" id="next-page"><i class="uil-angle-right"></i></button>
                    <div class="toolbar-divider"></div>
                    <button class="toolbar-btn" id="zoom-out"><i class="uil-minus-circle"></i></button>
                    <span id="zoom-info" class="zoom-info">100%</span>
                    <button class="toolbar-btn" id="zoom-in"><i class="uil-plus-circle"></i></button>
                </div>
                <!-- Sheet tabs -->
                <div id="sheet-tabs" class="d-flex gap-1 flex-wrap"></div>
                <!-- Loading -->
                <div id="loading-indicator" class="d-flex align-items-center gap-2 small text-muted">
                    <span class="spinner-border spinner-border-sm text-primary"></span>
                    Загрузка документа...
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="viewer-body">
            <div class="viewer-watermark">ТОЛЬКО ДЛЯ ПРОСМОТРА</div>
            <div id="pdf-container"  class="viewer-pdf-container"  style="display:none"></div>
            <div id="docx-container" class="viewer-docx-container" style="display:none"></div>
            <div id="xlsx-container" class="viewer-xlsx-container" style="display:none"></div>
            <div id="error-state" style="display:none;padding:60px;text-align:center;color:var(--text-muted)">
                <i class="uil-exclamation-triangle" style="font-size:2.5rem;color:var(--danger)"></i>
                <p class="mt-3 mb-0 small" id="error-message"></p>
            </div>
        </div>
    </div>

</div>

<div id="vd"
     data-token="<?= htmlspecialchars($token) ?>"
     data-type="<?= $type ?>"
     style="display:none"></div>

        <?php
        $this->renderFooter();
        echo $this->viewerScript();
    }

    private function renderNotFound(): void
    {
        $this->renderHead('Не найдено');
        ?>
<div class="page-content d-flex align-items-center justify-content-center" style="min-height:70vh">
    <div class="text-center">
        <i class="uil-file-question" style="font-size:5rem;color:#e2e8ef"></i>
        <h4 class="mt-3 mb-2" style="color:var(--dark)">Документ не найден</h4>
        <a href="index.php" class="btn btn-primary"><i class="uil-arrow-left me-2"></i>К списку</a>
    </div>
</div>
        <?php
        $this->renderFooter();
    }

    private function renderHead(string $title): void { ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary:#727cf5; --success:#0acf97; --danger:#fa5c7c;
    --warning:#ffbc00; --body-bg:#f5f6f8; --card-bg:#fff;
    --border:#e2e8ef;  --text-muted:#8a99b0; --dark:#313a46;
    --topbar-h:62px;   --font:'Nunito',sans-serif;
}
*,*::before,*::after{box-sizing:border-box}
body{font-family:var(--font);background:var(--body-bg);color:#6c757d;font-size:.9rem;min-height:100vh}
.topbar{height:var(--topbar-h);background:#fff;box-shadow:0 0 35px 0 rgba(154,161,171,.15);
    position:sticky;top:0;z-index:999;display:flex;align-items:center;padding:0 24px;gap:16px}
.topbar-brand{display:flex;align-items:center;gap:10px;text-decoration:none;
    font-weight:700;font-size:1.15rem;color:var(--dark);flex-shrink:0}
.topbar-brand .logo-icon{width:34px;height:34px;background:var(--primary);border-radius:8px;
    display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem}
.topbar-divider{width:1px;height:28px;background:var(--border)}
.topbar-nav{display:flex;align-items:center;gap:4px}
.topbar-nav a{padding:6px 14px;border-radius:6px;text-decoration:none;color:#6c757d;
    font-weight:600;font-size:.85rem;transition:all .15s;display:flex;align-items:center;gap:6px}
.topbar-nav a:hover{background:var(--body-bg);color:var(--dark)}
.topbar-nav a.active{background:rgba(114,124,245,.1);color:var(--primary)}
.page-content{padding:28px 24px;max-width:1400px;margin:0 auto}
.page-title-box{display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:12px;margin-bottom:24px}
.page-title{font-size:1.1rem;font-weight:700;color:var(--dark);margin:0}
.card{background:var(--card-bg);border:none;border-radius:8px;
    box-shadow:0 0 35px 0 rgba(154,161,171,.15);margin-bottom:24px}
.card-header{background:transparent;border-bottom:1px solid var(--border);padding:14px 20px;
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:.95rem;font-weight:700;color:var(--dark);margin:0}
.badge{font-size:.7rem;font-weight:700;letter-spacing:.3px;padding:4px 8px}
.badge.bg-danger {background:rgba(250,92,124,.12)!important;color:var(--danger)}
.badge.bg-primary{background:rgba(114,124,245,.12)!important;color:var(--primary)}
.badge.bg-success{background:rgba(10,207,151,.12)!important;color:var(--success)}
.badge.bg-warning{background:rgba(255,188,0,.12)!important;color:var(--warning)}
.badge.bg-secondary{background:rgba(108,117,125,.12)!important;color:#6c757d}
.doc-icon{width:38px;height:38px;border-radius:6px;display:flex;align-items:center;
    justify-content:center;font-size:1.15rem;flex-shrink:0}
.doc-icon.pdf {background:rgba(250,92,124,.12);color:var(--danger)}
.doc-icon.docx,.doc-icon.doc{background:rgba(114,124,245,.12);color:var(--primary)}
.doc-icon.xlsx,.doc-icon.xls{background:rgba(10,207,151,.12);color:var(--success)}
.btn-outline-secondary:hover{background:#6c757d;color:#fff}

.security-badge{font-size:.73rem;font-weight:700;color:var(--success);
    background:rgba(10,207,151,.1);padding:4px 12px;border-radius:20px;
    display:inline-flex;align-items:center}
.viewer-toolbar{padding:10px 16px;flex-wrap:wrap;gap:8px}
.toolbar-group{display:flex;align-items:center;gap:3px;background:#f5f6f8;
    border-radius:6px;padding:3px}
.toolbar-btn{background:transparent;border:none;cursor:pointer;padding:6px 9px;
    border-radius:4px;color:#6c757d;font-size:1rem;transition:all .15s;
    line-height:1;display:inline-flex;align-items:center}
.toolbar-btn:hover{background:#e2e8ef;color:var(--primary)}
.toolbar-divider{width:1px;height:18px;background:var(--border);margin:0 3px}
.page-info,.zoom-info{font-size:.78rem;color:#6c757d;min-width:65px;
    text-align:center;font-weight:700}
.sheet-tab{font-size:.76rem;padding:4px 12px;border-radius:20px;cursor:pointer;
    background:#f5f6f8;color:#6c757d;border:1px solid transparent;
    font-weight:700;transition:all .15s}
.sheet-tab.active,.sheet-tab:hover{background:var(--primary);color:#fff}

/* viewer-body — серый фон на заднем плане, контейнеры тянутся на всю высоту */
.viewer-body{
    position:relative;
    min-height:600px;
    background:#525659;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.viewer-watermark{
    position:absolute;top:50%;left:50%;
    transform:translate(-50%,-50%) rotate(-30deg);
    font-size:3.5rem;font-weight:900;color:rgba(0,0,0,.04);
    pointer-events:none;z-index:10;white-space:nowrap;
    user-select:none;letter-spacing:4px;
}

/* PDF — страницы плавают на сером фоне (intentional) */
.viewer-pdf-container{
    overflow-y:auto;max-height:820px;padding:24px;
    display:flex;flex-direction:column;align-items:center;gap:16px;
}
.viewer-pdf-container canvas{
    box-shadow:0 4px 24px rgba(0,0,0,.35);border-radius:2px;
    display:block;max-width:100%;
}

/* DOCX/XLSX — белый фон заполняет всю высоту, серый только за краями */
.viewer-docx-container{
    flex:1;
    min-height:600px;
    background:#fff;
    overflow-y:auto;
    padding:50px 60px;
    font-family:var(--font);font-size:.9rem;line-height:1.8;color:#333;
}
.viewer-docx-container table{border-collapse:collapse;width:100%;margin-bottom:16px}
.viewer-docx-container td,.viewer-docx-container th{border:1px solid #ccc;padding:6px 10px}

.viewer-xlsx-container{
    flex:1;
    min-height:600px;
    background:#fff;
    overflow:auto;
}
.xlsx-table{border-collapse:collapse;width:100%;font-size:.82rem}
.xlsx-table th{background:#f5f6f8;border:1px solid var(--border);padding:7px 10px;
    font-weight:700;color:var(--text-muted);text-transform:uppercase;
    font-size:.68rem;letter-spacing:.4px;position:sticky;top:0;z-index:1}
.xlsx-table td{border:1px solid var(--border);padding:5px 10px;color:#6c757d;white-space:nowrap}
.xlsx-table tr:hover td{background:#fafbfe}
</style>
</head>
<body>
    <?php }

    private function renderFooter(): void { ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php }

    // ─── Скрипт просмотра ────────────────────────────────────────────────────

    private function viewerScript(): string { return <<<'JS'
<script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.js"></script>
<script type="module">
const D     = document.getElementById('vd');
const TOKEN = D.dataset.token;
const TYPE  = D.dataset.type;
const URL   = `serve.php?token=${TOKEN}`;

// ── Security ──────────────────────────────────────────────────────────────────
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('dragstart',   e => e.preventDefault());
document.addEventListener('keydown', e => {
    const c = e.ctrlKey || e.metaKey;
    if (c && ['s','p','u','a'].includes(e.key.toLowerCase())) e.preventDefault();
    if (e.key === 'F12') e.preventDefault();
    if (c && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase())) e.preventDefault();
});
const ps = document.createElement('style');
ps.textContent = '@media print{body{display:none!important}}';
document.head.appendChild(ps);

// ── Helpers ───────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

function hideLoading() {
    const el = $('loading-indicator');
    if (el) el.style.display = 'none';
}

function showError(msg) {
    hideLoading();
    $('error-state').style.display = 'block';
    $('error-message').textContent = msg;
}

function showUnsupported(ext) {
    hideLoading();
    const upper = ext.toUpperCase();
    $('error-state').style.display = 'block';
    $('error-message').innerHTML =
        `Предварительный просмотр формата <strong>${upper}</strong> в браузере невозможен.<br>` +
        `Это старый бинарный формат Microsoft Office.<br>` +
        `<span style="color:#6c757d">Конвертируйте файл в ` +
        (ext === 'doc' ? 'DOCX' : 'XLSX') +
        ` и загрузите повторно — он откроется без проблем.</span>`;
}

async function loadBuffer() {
    const r = await fetch(URL, { credentials: 'same-origin' });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.arrayBuffer();
}

// ── PDF ───────────────────────────────────────────────────────────────────────
async function initPdf() {
    const { getDocument, GlobalWorkerOptions } = await import(
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.mjs'
    );
    GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.mjs';

    const buf = await loadBuffer();
    const pdf = await getDocument({ data: buf }).promise;

    const container = $('pdf-container');
    container.style.display = 'flex';
    hideLoading();

    $('pdf-controls').style.display = '';

    let page = 1;
    let scale = 1.4;

    async function renderAll() {
        container.innerHTML = '';
        for (let i = 1; i <= pdf.numPages; i++) {
            const pg       = await pdf.getPage(i);
            const viewport = pg.getViewport({ scale });
            const canvas   = document.createElement('canvas');
            canvas.id      = `p${i}`;
            canvas.width   = viewport.width;
            canvas.height  = viewport.height;
            container.appendChild(canvas);
            await pg.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }
        updateInfo();
    }

    function updateInfo() {
        $('page-info').textContent = `Стр. ${page} / ${pdf.numPages}`;
        $('zoom-info').textContent = Math.round(scale / 1.4 * 100) + '%';
    }

    $('prev-page').onclick = () => { if(page>1){ page--; $(`p${page}`)?.scrollIntoView({behavior:'smooth'}); updateInfo(); } };
    $('next-page').onclick = () => { if(page<pdf.numPages){ page++; $(`p${page}`)?.scrollIntoView({behavior:'smooth'}); updateInfo(); } };
    $('zoom-in').onclick   = () => { scale = Math.min(scale+.25, 3.5); renderAll(); };
    $('zoom-out').onclick  = () => { scale = Math.max(scale-.25, .6);  renderAll(); };

    container.addEventListener('scroll', () => {
        const boxes = container.querySelectorAll('canvas');
        const top   = container.getBoundingClientRect().top + 40;
        for (let i = boxes.length-1; i >= 0; i--) {
            if (boxes[i].getBoundingClientRect().top <= top) { page = i+1; updateInfo(); break; }
        }
    });

    await renderAll();
}

// ── DOCX ─────────────────────────────────────────────────────────────────────
// mammoth загружается как UMD-скрипт (<script> выше), доступен как window.mammoth
async function initDocx() {
    const mammoth = window.mammoth;
    if (!mammoth) throw new Error('Библиотека mammoth не загружена.');

    const buf    = await loadBuffer();
    const result = await mammoth.convertToHtml({ arrayBuffer: buf });

    const c = $('docx-container');
    c.innerHTML = result.value || '<p style="color:#aaa;text-align:center;padding:40px">Документ пуст или не содержит текста.</p>';
    c.querySelectorAll('a').forEach(a => { a.removeAttribute('href'); a.style.cursor = 'default'; });
    c.style.userSelect = 'none';
    c.style.display    = 'block';
    hideLoading();
}

// ── XLSX ──────────────────────────────────────────────────────────────────────
async function initXlsx() {
    const XLSX = await import('https://cdn.jsdelivr.net/npm/xlsx@0.18.5/xlsx.mjs');
    const buf  = await loadBuffer();
    const wb   = XLSX.read(buf, { type: 'array' });

    const tabs = $('sheet-tabs');
    wb.SheetNames.forEach((name, i) => {
        const btn = document.createElement('button');
        btn.className   = 'sheet-tab' + (i===0?' active':'');
        btn.textContent = name;
        btn.onclick     = () => {
            tabs.querySelectorAll('.sheet-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            showSheet(wb, XLSX, name);
        };
        tabs.appendChild(btn);
    });

    showSheet(wb, XLSX, wb.SheetNames[0]);
    $('xlsx-container').style.display = 'block';
    hideLoading();
}

function showSheet(wb, XLSX, name) {
    const ws   = wb.Sheets[name];
    const data = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
    const c    = $('xlsx-container');
    if (!data.length) { c.innerHTML = '<p class="p-4 text-muted small">Лист пуст</p>'; return; }

    const t  = document.createElement('table');
    t.className = 'xlsx-table';
    const th = t.createTHead().insertRow();
    th.insertAdjacentHTML('beforeend','<th style="width:36px;text-align:center">#</th>');
    (data[0] || []).forEach(cell => {
        const h = document.createElement('th');
        h.textContent = String(cell ?? '');
        th.appendChild(h);
    });
    const tb = t.createTBody();
    data.slice(1).forEach((row, ri) => {
        const tr = tb.insertRow();
        const nd = tr.insertCell();
        nd.textContent = ri+2;
        nd.style.cssText = 'text-align:center;color:#b0bac5;font-size:.72rem';
        row.forEach(cell => { tr.insertCell().textContent = String(cell ?? ''); });
    });
    t.style.userSelect = 'none';
    c.innerHTML = '';
    c.appendChild(t);
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
try {
    if      (TYPE === 'pdf')  await initPdf();
    else if (TYPE === 'docx') await initDocx();
    else if (TYPE === 'xlsx') await initXlsx();
    else if (TYPE === 'doc')  showUnsupported('doc');
    else if (TYPE === 'xls')  showUnsupported('xls');
    else showError('Формат не поддерживается.');
} catch(e) {
    showError('Ошибка загрузки: ' + e.message);
} finally {
    // Гарантированно скрываем спиннер в любом случае
    hideLoading();
}
</script>
JS; }
}

(new ViewerController())->run();