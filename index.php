<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/SecurityManager.php';
require_once __DIR__ . '/classes/DocumentManager.php';
require_once __DIR__ . '/classes/FileUploader.php';
require_once __DIR__ . '/classes/Layout.php';

$uploadDir = UPLOAD_DIR;
$indexFile = UPLOAD_DIR . '.index.json';

class IndexController
{
    private SecurityManager $security;
    private DocumentManager $docManager;
    private FileUploader    $uploader;
    private array           $flashErrors  = [];
    private string          $flashSuccess = '';

    public function __construct()
    {
        $this->security   = new SecurityManager();
        $this->docManager = new DocumentManager();
        $this->uploader   = new FileUploader();
    }

    public function run(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Читаем flash-сообщения из сессии (установленные после редиректа)
        $this->flashErrors  = $_SESSION['flash_errors']  ?? [];
        $this->flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_errors'], $_SESSION['flash_success']);

        $action = $_POST['action'] ?? '';

        if ($action === 'upload') $this->handleUpload();
        if ($action === 'delete') $this->handleDelete();

        $this->render();
    }

    private function handleUpload(): void
    {
        if (!$this->security->validateCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ошибка безопасности. Обновите страницу.'];
            header('Location: index.php');
            exit;
        }
        if (empty($_FILES['document']['name'])) {
            $_SESSION['flash_errors'] = ['Файл не выбран.'];
            header('Location: index.php');
            exit;
        }
        if ($this->uploader->handle($_FILES['document'])) {
            $doc = $this->docManager->add($this->uploader->getFileInfo());
            $_SESSION['flash_success'] = "Документ «{$doc->originalName}» загружен.";
        } else {
            $_SESSION['flash_errors'] = $this->uploader->getErrors();
        }
        header('Location: index.php');
        exit;
    }

    private function handleDelete(): void
    {
        if (!$this->security->validateCsrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ошибка безопасности.'];
            header('Location: index.php');
            exit;
        }
        $id = $_POST['id'] ?? '';
        if ($this->docManager->delete($id)) {
            $_SESSION['flash_success'] = 'Документ удалён.';
        } else {
            $_SESSION['flash_errors'] = ['Документ не найден.'];
        }
        header('Location: index.php');
        exit;
    }

    private function render(): void
    {
        $documents = $this->docManager->getAll();
        $csrf      = $this->security->getCsrfToken();

        // Статистика
        $totalSize    = array_sum(array_map(fn($d) => $d->size, $documents));
        $totalSizeFmt = $totalSize >= 1048576
            ? round($totalSize / 1048576, 1) . ' МБ'
            : round($totalSize / 1024, 1) . ' КБ';

        $byExt = [];
        foreach ($documents as $doc) {
            $byExt[$doc->extension] = ($byExt[$doc->extension] ?? 0) + 1;
        }
        ?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Документы</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary:   #727cf5;
    --success:   #0acf97;
    --danger:    #fa5c7c;
    --warning:   #ffbc00;
    --body-bg:   #f5f6f8;
    --card-bg:   #fff;
    --border:    #e2e8ef;
    --text-muted:#8a99b0;
    --dark:      #313a46;
    --topbar-h:  62px;
    --font:      'Nunito', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: var(--font);
    background:  var(--body-bg);
    color:       #6c757d;
    font-size:   .9rem;
    min-height:  100vh;
}

.topbar {
    height:      var(--topbar-h);
    background:  #fff;
    box-shadow:  0 0 35px 0 rgba(154,161,171,.15);
    position:    sticky;
    top: 0;
    z-index:     999;
    display:     flex;
    align-items: center;
    padding:     0 24px;
    gap:         16px;
}

.topbar-brand {
    display:     flex;
    align-items: center;
    gap:         10px;
    text-decoration: none;
    font-weight: 700;
    font-size:   1.15rem;
    color:       var(--dark);
    flex-shrink: 0;
}
.topbar-brand .logo-icon {
    width:           34px;
    height:          34px;
    background:      var(--primary);
    border-radius:   8px;
    display:         flex;
    align-items:     center;
    justify-content: center;
    color:           #fff;
    font-size:       1rem;
}

.topbar-divider { width: 1px; height: 28px; background: var(--border); }

.topbar-nav { display: flex; align-items: center; gap: 4px; }
.topbar-nav a {
    padding:        6px 14px;
    border-radius:  6px;
    text-decoration: none;
    color:          #6c757d;
    font-weight:    600;
    font-size:      .85rem;
    transition:     all .15s;
    display:        flex;
    align-items:    center;
    gap:            6px;
}
.topbar-nav a:hover  { background: var(--body-bg); color: var(--dark); }
.topbar-nav a.active { background: rgba(114,124,245,.1); color: var(--primary); }

.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 8px; font-size: .8rem; color: var(--text-muted); }

.page-content { padding: 28px 24px; max-width: 1400px; margin: 0 auto; }

.page-title-box {
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    flex-wrap:       wrap;
    gap:             12px;
    margin-bottom:   24px;
}
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--dark); margin: 0; }
.breadcrumb { margin: 0; padding: 0; background: transparent; font-size: .78rem; }
.breadcrumb-item a { color: var(--primary); text-decoration: none; }

.card {
    background:    var(--card-bg);
    border:        none;
    border-radius: 8px;
    box-shadow:    0 0 35px 0 rgba(154,161,171,.15);
    margin-bottom: 24px;
}
.card-header {
    background:    transparent;
    border-bottom: 1px solid var(--border);
    padding:       14px 20px;
    display:       flex;
    align-items:   center;
    justify-content: space-between;
    flex-wrap:     wrap;
    gap:           10px;
}
.card-title { font-size: .95rem; font-weight: 700; color: var(--dark); margin: 0; }
.card-body  { padding: 20px; }

.btn-primary { background: var(--primary); border-color: var(--primary); }
.btn-primary:hover { background: #6169d0; border-color: #6169d0; }
.btn-outline-primary { color: var(--primary); border-color: var(--primary); }
.btn-outline-primary:hover { background: var(--primary); border-color: var(--primary); }

.table > thead > tr > th {
    background:      var(--body-bg);
    color:           var(--text-muted);
    font-weight:     700;
    font-size:       .72rem;
    text-transform:  uppercase;
    letter-spacing:  .5px;
    border:          none;
    padding:         10px 14px;
}
.table > tbody > tr > td {
    vertical-align: middle;
    border-color:   var(--border);
    color:          #6c757d;
    font-size:      .875rem;
    padding:        10px 14px;
}
.table-hover tbody tr:hover { background: #fafbfe; }

.badge { font-size: .7rem; font-weight: 700; letter-spacing: .3px; padding: 4px 8px; }
.badge.bg-danger   { background: rgba(250,92,124,.12)  !important; color: var(--danger);  }
.badge.bg-primary  { background: rgba(114,124,245,.12) !important; color: var(--primary); }
.badge.bg-success  { background: rgba(10,207,151,.12)  !important; color: var(--success); }
.badge.bg-warning  { background: rgba(255,188,0,.12)   !important; color: var(--warning); }
.badge.bg-secondary{ background: rgba(108,117,125,.12) !important; color: #6c757d; }

.doc-icon {
    width: 38px; height: 38px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; flex-shrink: 0;
}
.doc-icon.pdf  { background: rgba(250,92,124,.12);  color: var(--danger);  }
.doc-icon.docx,
.doc-icon.doc  { background: rgba(114,124,245,.12); color: var(--primary); }
.doc-icon.xlsx,
.doc-icon.xls  { background: rgba(10,207,151,.12);  color: var(--success); }

.upload-zone {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 36px 20px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #fafbfe;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: var(--primary);
    background: rgba(114,124,245,.04);
}
.upload-zone .upload-icon { font-size: 2.6rem; color: var(--primary); opacity: .7; margin-bottom: 10px; }

.stat-widget { display: flex; align-items: center; gap: 14px; }
.stat-icon { width: 52px; height: 52px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.stat-value { font-size: 1.45rem; font-weight: 700; color: var(--dark); line-height: 1; }
.stat-label { font-size: .75rem; color: var(--text-muted); margin-top: 3px; }

.alert { border: none; border-radius: 6px; font-size: .875rem; }
.alert-success { background: rgba(10,207,151,.1);  color: #06825e; }
.alert-danger  { background: rgba(250,92,124,.1);  color: #c53358; }
</style>

</head>
<body>

<div class="page-content">
    <div class="page-title-box">
        <h4 class="page-title"><i class="uil-folder-open me-2" style="color:var(--primary)"></i>Мои документы</h4>
    </div>

    <!-- Flash -->
    <?php if ($this->flashSuccess): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="uil-check-circle" style="font-size:1.1rem"></i>
        <?= htmlspecialchars($this->flashSuccess) ?>
    </div>
    <?php endif; ?>
    <?php foreach ($this->flashErrors as $err): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="uil-exclamation-triangle" style="font-size:1.1rem"></i>
        <?= htmlspecialchars($err) ?>
    </div>
    <?php endforeach; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $stats = [
            ['icon'=>'uil-files-landscapes-alt', 'bg'=>'rgba(114,124,245,.1)', 'color'=>'var(--primary)',
             'val'=>count($documents), 'label'=>'Всего документов'],
            ['icon'=>'uil-database-alt', 'bg'=>'rgba(10,207,151,.1)', 'color'=>'var(--success)',
             'val'=>$totalSizeFmt, 'label'=>'Занято места'],
            ['icon'=>'uil-file-alt', 'bg'=>'rgba(250,92,124,.1)', 'color'=>'var(--danger)',
             'val'=>($byExt['pdf']??0), 'label'=>'PDF-файлов'],
            ['icon'=>'uil-table', 'bg'=>'rgba(255,188,0,.1)', 'color'=>'var(--warning)',
             'val'=>($byExt['xlsx']??0)+($byExt['xls']??0), 'label'=>'Excel-файлов'],
        ];
        foreach ($stats as $s): ?>
        <div class="col-6 col-xl-3">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="stat-widget">
                        <div class="stat-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>">
                            <i class="<?= $s['icon'] ?>"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $s['val'] ?></div>
                            <div class="stat-label"><?= $s['label'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">

        <!-- Upload -->
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="uil-cloud-upload me-2" style="color:var(--primary)"></i>Загрузить документ</h5>
                </div>
                <div class="card-body">
                    <form id="upload-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action"     value="upload">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                        <div class="upload-zone" id="drop-zone"
                             onclick="document.getElementById('file-input').click()">
                            <div class="upload-icon"><i class="uil-cloud-upload"></i></div>
                            <p class="mb-1 fw-semibold" style="color:#555">
                                Перетащите файл или <span style="color:var(--primary)">нажмите</span>
                            </p>
                            <p class="mb-0" style="font-size:.76rem;color:var(--text-muted)">
                                PDF, DOCX, DOC, XLSX, XLS · макс. 50 МБ
                            </p>
                            <div id="file-preview" class="mt-3" style="display:none">
                                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded"
                                     style="background:#f5f6f8;border:1px solid var(--border)">
                                    <i class="uil-file-check" style="font-size:1.3rem;color:var(--primary)"></i>
                                    <span id="file-name" class="small text-truncate flex-grow-1 fw-semibold" style="color:#444"></span>
                                    <span id="file-size" class="small flex-shrink-0" style="color:var(--text-muted)"></span>
                                </div>
                            </div>
                        </div>

                        <input type="file" id="file-input" name="document" class="d-none"
                               accept=".pdf,.doc,.docx,.xls,.xlsx">

                        <button type="submit" id="submit-btn"
                                class="btn btn-primary w-100 mt-3 d-flex align-items-center justify-content-center gap-2">
                            <i class="uil-file-upload-alt"></i> Загрузить
                        </button>
                    </form>

                    <hr class="my-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (array_keys(ALLOWED_TYPES) as $ext): ?>
                        <span class="badge bg-secondary text-uppercase"><?= $ext ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents list -->
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="uil-list-ul me-2" style="color:var(--primary)"></i>Список документов
                        <?php if ($documents): ?>
                        <span class="badge bg-primary ms-1"><?= count($documents) ?></span>
                        <?php endif; ?>
                    </h5>
                    <?php if ($documents): ?>
                    <input type="text" id="search-input" class="form-control form-control-sm"
                           placeholder="Поиск по названию..." style="max-width:210px">
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">

                <?php if (empty($documents)): ?>
                    <div class="py-5 text-center" style="color:var(--text-muted)">
                        <i class="uil-folder-open" style="font-size:3.5rem;opacity:.35"></i>
                        <h6 class="mt-3 mb-1 fw-bold" style="color:#6c757d">Документов пока нет</h6>
                        <p class="small mb-0">Загрузите первый документ с помощью формы слева</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="docs-table">
                            <thead>
                                <tr>
                                    <th style="width:46px">ID</th>
                                    <th>Название</th>
                                    <th>Размер</th>
                                    <th>Дата</th>
                                    <th class="text-center">Тип</th>
                                    <th class="text-end" style="width:100px">Действия</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php foreach ($documents as $doc): ?>
                            <tr data-name="<?= $doc->originalName ?>">
                                <td>
                                    <div class="doc-icon <?= $doc->extension ?>">
                                        <i class="<?= $doc->iconClass() ?>"></i>
                                    </div>
                                </td>
                                <td>
                                    <a href="viewer.php?id=<?= urlencode($doc->id) ?>"
                                       class="fw-semibold text-decoration-none"
                                       style="color:var(--dark);max-width:240px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                       title="<?= htmlspecialchars($doc->originalName) ?>">
                                        <?= htmlspecialchars($doc->originalName) ?>
                                    </a>
                                </td>
                                <td class="text-nowrap"><?= $doc->formattedSize() ?></td>
                                <td class="text-nowrap" style="font-size:.8rem"><?= $doc->formattedDate() ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $doc->badgeClass() ?> text-uppercase">
                                        <?= $doc->extension ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="viewer.php?id=<?= urlencode($doc->id) ?>"
                                       class="btn btn-sm btn-outline-primary me-1" title="Открыть">
                                        <i class="uil-eye"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger delete-btn"
                                            data-id="<?= htmlspecialchars($doc->id) ?>"
                                            data-name="<?= htmlspecialchars($doc->originalName) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            title="Удалить">
                                        <i class="uil-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                </div>
            </div>
        </div>

    </div><!-- /row -->
</div><!-- /page-content -->

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:none;border-radius:10px">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" style="color:var(--dark)">
                    <i class="uil-trash-alt me-2" style="color:var(--danger)"></i>Удалить документ?
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:.875rem">
                Удалить <strong id="modal-doc-name"></strong>? Действие нельзя отменить.
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Отмена</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id"         id="modal-doc-id">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="uil-trash-alt me-1"></i>Удалить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
        echo $this->pageScript();
    }

    private function pageScript(): string { return <<<'JS'
<script>

(function(){
    const input   = document.getElementById('file-input');
    const zone    = document.getElementById('drop-zone');
    const preview = document.getElementById('file-preview');
    const nameEl  = document.getElementById('file-name');
    const sizeEl  = document.getElementById('file-size');

    function showFile(f){
        nameEl.textContent = f.name;
        sizeEl.textContent = f.size >= 1048576
            ? (f.size/1048576).toFixed(1)+' МБ'
            : (f.size/1024).toFixed(1)+' КБ';
        preview.style.display = 'block';
    }

    input.addEventListener('change', () => { if(input.files[0]) showFile(input.files[0]); });

    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if(e.dataTransfer.files[0]){
            const dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            input.files = dt.files;
            showFile(e.dataTransfer.files[0]);
        }
    });

    document.getElementById('upload-form').addEventListener('submit', function(){
        const btn = document.getElementById('submit-btn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Загрузка...';
        btn.disabled = true;
    });
})();

// Delete modal
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('modal-doc-id').value       = btn.dataset.id;
        document.getElementById('modal-doc-name').textContent = btn.dataset.name;
    });
});

// Search
const si = document.getElementById('search-input');
if(si) si.addEventListener('input', () => {
    const q = si.value.toLowerCase();
    document.querySelectorAll('#docs-table tbody tr').forEach(tr => {
        tr.style.display = tr.dataset.name.includes(q) ? '' : 'none';
    });
});
</script>
JS; }
}

(new IndexController())->run();