<?php
// inisialisasi array untuk menyimpan data todo
$todos = [];

// cek apakah file todo.txt ada atau tidak
if (file_exists('todo.txt')) {
    // baca isi file todo.txt
    $file = file_get_contents('todo.txt');
    // ubah dari format serialize ke array biasa
    $todos = unserialize($file);
}

// kalau ada data todo yang dikirim lewat form
// fungsi untuk nyimpen data ke file txt
function simpanData($data)
{
    file_put_contents('todo.txt', $data);
    header('location: index.php');
    exit();
}

if (isset($_POST['todo']) && !empty(trim($_POST['todo']))) {
    $data = trim($_POST['todo']);
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    
    // tambahin data baru ke array todos
    $todos[] = [
        'todo' => $data,
        'status' => 0,
        'due_date' => $due_date,
        'priority' => $priority,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $serialized_data = serialize($todos);
    simpanData($serialized_data);
}

// handler untuk ajax request update status
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'toggle_status') {
    $key = $_POST['key'];
    $status = $_POST['status'];
    
    if (isset($todos[$key])) {
        $todos[$key]['status'] = $status;
        $serialized_data = serialize($todos);
        file_put_contents('todo.txt', $serialized_data);
        
        // reload data untuk mendapatkan sorting terbaru
        $todos = unserialize(file_get_contents('todo.txt'));
        $sortedTodos = sortTodos($todos);
        
        // hitung statistik terbaru
        $completed = array_filter($todos, function($todo) {
            return $todo['status'] == 1;
        });
        $total_todos = count($todos);
        $completed_todos = count($completed);
        $high_priority = count(array_filter($todos, function($todo) {
            return isset($todo['priority']) && $todo['priority'] == 'high' && $todo['status'] == 0;
        }));
        $overdue = count(array_filter($todos, function($todo) {
            return isset($todo['due_date']) && $todo['due_date'] && strtotime($todo['due_date']) < time() && $todo['status'] == 0;
        }));
        
        echo json_encode([
            'success' => true, 
            'message' => 'Status updated',
            'stats' => [
                'total' => $total_todos,
                'completed' => $completed_todos,
                'urgent' => $high_priority,
                'overdue' => $overdue
            ],
            'sortedTodos' => $sortedTodos
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Todo not found']);
    }
    exit();
}

// kalau ada request untuk hapus todo
if (isset($_GET['hapus'])) {
    $key_to_delete = $_GET['key'];
    if (isset($todos[$key_to_delete])) {
        unset($todos[$key_to_delete]);
        $todos = array_values($todos);
        $serialized_data = serialize($todos);
        file_put_contents('todo.txt', $serialized_data);
        $todos = unserialize(file_get_contents('todo.txt')); // Reload for sorting
        $sortedTodos = sortTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus!', 'sortedTodos' => $sortedTodos, 'stats' => getStats($todos)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan.']);
    }
    exit();
}

// handler untuk ajax request update todo text
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'update_todo_text') {
    $key = $_POST['key'];
    $new_text = trim($_POST['new_text']);
    if (isset($todos[$key]) && !empty($new_text)) {
        $todos[$key]['todo'] = $new_text;
        $serialized_data = serialize($todos);
        file_put_contents('todo.txt', $serialized_data);
        $todos = unserialize(file_get_contents('todo.txt')); // Reload for sorting
        $sortedTodos = sortTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Tugas berhasil diperbarui', 'sortedTodos' => $sortedTodos, 'stats' => getStats($todos)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui tugas.']);
    }
    exit();
}



// fungsi untuk mendapatkan statistik terbaru
function getStats($todos) {
    $completed = array_filter($todos, function($todo) {
        return $todo['status'] == 1;
    });
    $total_todos = count($todos);
    $completed_todos = count($completed);
    $high_priority = count(array_filter($todos, function($todo) {
        return isset($todo['priority']) && $todo['priority'] == 'high' && $todo['status'] == 0;
    }));
    $overdue = count(array_filter($todos, function($todo) {
        return isset($todo['due_date']) && $todo['due_date'] && strtotime($todo['due_date']) < time() && $todo['status'] == 0;
    }));
    return [
        'total' => $total_todos,
        'completed' => $completed_todos,
        'urgent' => $high_priority,
        'overdue' => $overdue
    ];
}

// fungsi untuk sorting todos - berdasarkan prioritas, deadline, dan status
function sortTodos($todos) {
    // pisahkan todos berdasarkan status
    $incomplete = [];
    $completed = [];
    
    foreach ($todos as $key => $todo) {
        if ($todo['status'] == 0) {
            $incomplete[] = ['key' => $key, 'todo' => $todo];
        } else {
            $completed[] = ['key' => $key, 'todo' => $todo];
        }
    }
    
    // urutkan yang belum selesai berdasarkan prioritas, deadline, lalu waktu dibuat
    usort($incomplete, function($a, $b) {
        // prioritas order: high > medium > low
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        $aPriority = isset($priorityOrder[$a['todo']['priority']]) ? $priorityOrder[$a['todo']['priority']] : 2;
        $bPriority = isset($priorityOrder[$b['todo']['priority']]) ? $priorityOrder[$b['todo']['priority']] : 2;
        
        // kalau prioritas beda, urutkan berdasarkan prioritas
        if ($aPriority !== $bPriority) {
            return $bPriority - $aPriority;
        }
        
        // kalau prioritas sama, urutkan berdasarkan deadline
        $aDate = $a['todo']['due_date'] ? strtotime($a['todo']['due_date']) : PHP_INT_MAX;
        $bDate = $b['todo']['due_date'] ? strtotime($b['todo']['due_date']) : PHP_INT_MAX;
        
        if ($aDate !== $bDate) {
            return $aDate - $bDate;
        }
        
        // kalau deadline juga sama, urutkan berdasarkan waktu dibuat (terbaru di atas)
        return strtotime($b['todo']['created_at']) - strtotime($a['todo']['created_at']);
    });
    
    // urutkan yang sudah selesai berdasarkan waktu dibuat (terlama di atas)
    usort($completed, function($a, $b) {
        return strtotime($a['todo']['created_at']) - strtotime($b['todo']['created_at']);
    });
    
    // gabungkan: yang belum selesai di atas, yang selesai di bawah
    return array_merge($incomplete, $completed);
}

// fungsi untuk mendapatkan class CSS berdasarkan prioritas
function getPriorityClass($priority) {
    switch($priority) {
        case 'high': return 'priority-high';
        case 'medium': return 'priority-medium';
        case 'low': return 'priority-low';
        default: return 'priority-medium';
    }
}

// fungsi untuk mendapatkan label prioritas
function getPriorityLabel($priority) {
    switch($priority) {
        case 'high': return '<i class="fas fa-exclamation-triangle text-danger"></i> Urgent';
        case 'medium': return '<i class="fas fa-minus-circle text-warning"></i> Normal';
        case 'low': return '<i class="fas fa-circle text-success"></i> Rendah';
        default: return '<i class="fas fa-minus-circle text-warning"></i> Normal';
    }
}

// fungsi untuk format tanggal deadline
function formatDueDate($due_date) {
    if (!$due_date) return null;
    
    $date = new DateTime($due_date);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    $formatted = $date->format('d/m/Y');
    
    if ($date < $now) {
        return '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Terlambat (' . $formatted . ')</span>';
    } elseif ($diff->days == 0) {
        return '<span class="text-warning"><i class="fas fa-clock"></i> Hari ini (' . $formatted . ')</span>';
    } elseif ($diff->days == 1) {
        return '<span class="text-info"><i class="fas fa-calendar-day"></i> Besok (' . $formatted . ')</span>';
    } elseif ($diff->days <= 7) {
        return '<span class="text-primary"><i class="fas fa-calendar-week"></i> ' . $diff->days . ' hari lagi (' . $formatted . ')</span>';
    } else {
        return '<span class="text-muted"><i class="fas fa-calendar"></i> ' . $formatted . '</span>';
    }
}

// sorting todos
$sortedTodos = sortTodos($todos);

// hitung berapa todo yang udah selesai
$completed = array_filter($todos, function($todo) {
    return $todo['status'] == 1;
});
$total_todos = count($todos);
$completed_todos = count($completed);

// hitung statistik prioritas
$high_priority = count(array_filter($todos, function($todo) {
    return isset($todo['priority']) && $todo['priority'] == 'high' && $todo['status'] == 0;
}));
$overdue = count(array_filter($todos, function($todo) {
    return isset($todo['due_date']) && $todo['due_date'] && strtotime($todo['due_date']) < time() && $todo['status'] == 0;
}));

// hitung jumlah per section
$incomplete_count = count(array_filter($todos, function($todo) {
    return $todo['status'] == 0;
}));
$completed_count = count(array_filter($todos, function($todo) {
    return $todo['status'] == 1;
}));
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo App - M. Rohid Rivaldi</title>
    
    <!-- bootstrap css -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- font awesome untuk icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- google fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- custom css -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- loading screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile photo of M. Rohid Rivaldi showing a professional headshot with friendly smile" class="profile-img" />
            </div>
            <h2>Selamat Datang di Todo App</h2>
            <p>Nama: M. Rohid Rivaldi</p>
            <p>NIM: 2401301002</p>
            <small>Memuat aplikasi...</small>
        </div>
    </div>

    <!-- tips modal -->
    <div class="modal fade" id="tipsModal" tabindex="-1" aria-labelledby="tipsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tipsModalLabel">
                        <i class="fas fa-lightbulb me-2"></i>Tips & Keyboard Shortcuts
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts</h6>
                            <div class="tips-list">
                                <div class="tip-item">
                                    <kbd>Enter</kbd> <span>Tambah tugas cepat</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Esc</kbd> <span>Clear form</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Alt + 1</kbd> <span>Prioritas Rendah</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Alt + 2</kbd> <span>Prioritas Normal</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Alt + 3</kbd> <span>Prioritas Urgent</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Ctrl + Alt + T</kbd> <span>Set deadline hari ini</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Ctrl + Alt + D</kbd> <span>Toggle Dark/Light Mode</span>
                                </div>
                                <div class="tip-item">
                                    <kbd>Ctrl + Alt + E</kbd> <span>Export data</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-tips me-2"></i>Tips Penggunaan</h6>
                            <div class="tips-list">
                                <div class="tip-item">
                                    <i class="fas fa-mouse-pointer text-primary"></i>
                                    <span>Double click pada teks tugas untuk edit</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-flag text-warning"></i>
                                    <span>Gunakan prioritas untuk mengatur urutan tugas</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-calendar text-info"></i>
                                    <span>Set deadline untuk tugas yang penting</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <span>Centang checkbox untuk menandai tugas selesai</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-chart-bar text-secondary"></i>
                                    <span>Lihat progress Anda di bagian statistik</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-sort text-primary"></i>
                                    <span>Tugas akan otomatis tersortir berdasarkan prioritas</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-save text-success"></i>
                                    <span>Data tersimpan otomatis setiap ada perubahan</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-trophy text-warning"></i>
                                    <span>Selesaikan semua tugas untuk mendapat animasi konfetti!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- main application -->
    <div class="main-container" id="mainApp">
        <div class="container">
            <div class="row justify-content-center">
                                <div class="col-lg-8 col-md-10">
                    <div class="todo-card">
                        <!-- header section -->
                        <div class="header-section">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <img src="images/profile.png" alt="Small profile photo of M. Rohid Rivaldi for todo app header" class="header-profile-img me-3" />
                                <div class="text-start">
                                    <h1 class="mb-0"><i class="fas fa-tasks me-2"></i>My Todo App</h1>
                                    <p class="mb-0">M. Rohid Rivaldi - 2401301002</p>
                                </div>
                                <button id="themeToggle" class="btn btn-outline-light btn-sm ms-auto me-2">
                                     <i class="fas fa-moon"></i>
                                 </button>
                                 <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#tipsModal">
                                     <i class="fas fa-question-circle me-1"></i>Tips
                                 </button>
                            </div>
                            <p class="mb-0">"Kelola tugas harian Anda dengan mudah"</p>
                            
                            <!-- statistics -->
                            <div class="row mt-4">
                                <div class="col-md-3 col-6">
                                    <div class="stats-card">
                                        <h5 id="totalCount"><i class="fas fa-list me-2"></i><?php echo $total_todos; ?></h5>
                                        <small>Total Tugas</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="stats-card">
                                        <h5 id="completedCount"><i class="fas fa-check-circle me-2"></i><?php echo $completed_todos; ?></h5>
                                        <small>Selesai</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="stats-card">
                                        <h5 id="urgentCount"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $high_priority; ?></h5>
                                        <small>Urgent</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="stats-card">
                                        <h5 id="overdueCount"><i class="fas fa-clock me-2"></i><?php echo $overdue; ?></h5>
                                        <small>Terlambat</small>
                                    </div>
                                </div>
                            </div>

                            <!-- progress bar -->
                            <div class="progress-bar-container mt-3">
                                <div class="progress" style="height: 8px; border-radius: 5px;">
                                    <div class="progress-bar bg-success" style="transition: width 0.5s ease; width: <?php echo $total_todos > 0 ? ($completed_todos / $total_todos) * 100 : 0; ?>%;"></div>
                                </div>
                                <small class="text-muted">Progress: <span class="progress-text"><?php echo $total_todos > 0 ? round(($completed_todos / $total_todos) * 100) : 0; ?>%</span></small>
                            </div>
                        </div>

                        <!-- form input todo -->
                        <div class="p-4">
                            <form method="POST" class="mb-4" id="todoForm">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <input type="text" 
                                               name="todo" 
                                               class="form-control todo-input" 
                                               placeholder="Tambahkan tugas baru..." 
                                               required
                                               id="todoInput">
                                    </div>
                                </div>
                                
                                <!-- advanced options -->
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="due_date" class="form-label">
                                            <i class="fas fa-calendar me-1"></i>Deadline (Opsional)
                                        </label>
                                        <input type="date" 
                                               name="due_date" 
                                               class="form-control" 
                                               id="due_date"
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="priority" class="form-label">
                                            <i class="fas fa-flag me-1"></i>Prioritas
                                        </label>
                                        <div class="priority-select-wrapper">
                                            <select name="priority" class="form-select priority-select" id="priority">
                                                <option value="low">🟢 Rendah</option>
                                                <option value="medium" selected>🟡 Normal</option>
                                                <option value="high">🔴 Urgent</option>
                                            </select>
                                            <i class="fas fa-chevron-down priority-arrow"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-add w-100">
                                            <i class="fas fa-plus me-2"></i>Tambah Tugas
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- daftar todo -->
                            <div class="todo-list" id="todoList">
                                <?php if (empty($todos)): ?>
                                    <!-- kalau belum ada todo -->
                                    <div class="empty-state" id="emptyState">
                                        <i class="fas fa-clipboard-list"></i>
                                        <h5>Belum ada tugas</h5>
                                        <p>Tambahkan tugas pertama Anda untuk memulai!</p>
                                    </div>
                                <?php else: ?>
                                    <!-- section untuk tugas yang belum selesai -->
                                    <?php if ($incomplete_count > 0): ?>
                                        <div class="section-header incomplete-section" id="incompleteSection">
                                            <h6>
                                                <i class="fas fa-clock me-2"></i>Tugas Belum Selesai 
                                                <span class="section-count">(<span id="incompleteCount"><?php echo $incomplete_count; ?></span>)</span>
                                            </h6>
                                        </div>
                                        <div id="incompleteTodos">
                                            <?php 
                                            foreach ($sortedTodos as $item) {
                                                if ($item['todo']['status'] == 0) {
                                                    $key = $item['key'];
                                                    $todo = $item['todo'];
                                                    $priorityClass = getPriorityClass(isset($todo['priority']) ? $todo['priority'] : 'medium');
                                            ?>
                                                <div class="todo-item fade-in <?php echo $priorityClass; ?>" data-key="<?php echo $key; ?>" data-status="0">
                                                    <div class="d-flex align-items-center">
                                                        <!-- checkbox untuk mark as complete -->
                                                        <input type="checkbox" 
                                                               class="todo-checkbox"
                                                               data-key="<?php echo $key; ?>">
                                                        
                                                        <!-- text todo -->
                                                        <div class="todo-content flex-grow-1">
                                                            <p class="todo-text" ondblclick="editTodo(<?php echo $key; ?>)">
                                                                <?php echo htmlspecialchars($todo['todo']); ?>
                                                            </p>
                                                            
                                                            <!-- info tambahan: prioritas dan deadline -->
                                                            <div class="todo-meta">
                                                                <span class="priority-badge">
                                                                    <?php echo getPriorityLabel(isset($todo['priority']) ? $todo['priority'] : 'medium'); ?>
                                                                </span>
                                                                
                                                                <?php if (isset($todo['due_date']) && $todo['due_date']): ?>
                                                                    <span class="due-date-badge">
                                                                        <?php echo formatDueDate($todo['due_date']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- tombol hapus -->
                                                        <button class="btn-delete" onclick="deleteTodo(<?php echo $key; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- info waktu dibuat -->
                                                    <?php if (isset($todo['created_at'])): ?>
                                                        <small class="text-muted created-time">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Dibuat: <?php echo date('d/m/Y H:i', strtotime($todo['created_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php 
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- section untuk tugas yang sudah selesai -->
                                    <?php if ($completed_count > 0): ?>
                                        <div class="section-header completed-section" id="completedSection">
                                            <h6>
                                                <i class="fas fa-check-circle me-2"></i>Tugas Selesai 
                                                <span class="section-count">(<span id="completedSectionCount"><?php echo $completed_count; ?></span>)</span>
                                            </h6>
                                        </div>
                                        <div id="completedTodos">
                                            <?php 
                                            foreach ($sortedTodos as $item) {
                                                if ($item['todo']['status'] == 1) {
                                                    $key = $item['key'];
                                                    $todo = $item['todo'];
                                                    $priorityClass = getPriorityClass(isset($todo['priority']) ? $todo['priority'] : 'medium');
                                            ?>
                                                <div class="todo-item completed fade-in <?php echo $priorityClass; ?>" data-key="<?php echo $key; ?>" data-status="1">
                                                    <div class="d-flex align-items-center">
                                                        <!-- checkbox untuk mark as complete -->
                                                        <input type="checkbox" 
                                                               class="todo-checkbox"
                                                               checked
                                                               data-key="<?php echo $key; ?>">
                                                        
                                                        <!-- text todo -->
                                                        <div class="todo-content flex-grow-1">
                                                            <p class="todo-text completed">
                                                                <?php echo htmlspecialchars($todo['todo']); ?>
                                                            </p>
                                                            
                                                            <!-- info tambahan: prioritas dan deadline -->
                                                            <div class="todo-meta">
                                                                <span class="priority-badge completed">
                                                                    <?php echo getPriorityLabel(isset($todo['priority']) ? $todo['priority'] : 'medium'); ?>
                                                                </span>
                                                                
                                                                <?php if (isset($todo['due_date']) && $todo['due_date']): ?>
                                                                    <span class="due-date-badge completed">
                                                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($todo['due_date'])); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- tombol hapus -->
                                                        <button class="btn-delete" onclick="deleteTodo(<?php echo $key; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- info waktu dibuat -->
                                                    <?php if (isset($todo['created_at'])): ?>
                                                        <small class="text-muted created-time">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Dibuat: <?php echo date('d/m/Y H:i', strtotime($todo['created_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php 
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- footer section -->
                    <div class="app-footer">
                        <div class="footer-content">
                            <div class="footer-info">
                                <p>&copy; 2024 M. Rohid Rivaldi - Made with <i class="fas fa-heart text-danger"></i> for productivity</p>
                                <small>NIM: 2401301002 | Todo App v1.0</small>
                            </div>
                            <div class="social-links">
                                <a href="https://github.com/rohidrivaldi" target="_blank" class="social-link" title="GitHub">
                                    <i class="fab fa-github"></i>
                                </a>
                                <a href="https://linkedin.com/in/rohidrivaldi" target="_blank" class="social-link" title="LinkedIn">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                                <a href="https://instagram.com/revaldy.v" target="_blank" class="social-link" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- notification container -->
    <div id="notificationContainer" class="notification-container"></div>

    <!-- bootstrap js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jquery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- custom script -->
    <script src="script.js"></script>

    <!-- inline script untuk data PHP -->
    <script>
        // data dari PHP untuk JavaScript
        const todoData = <?php echo json_encode($sortedTodos); ?>;
        const totalTodos = <?php echo $total_todos; ?>;
        const completedTodos = <?php echo $completed_todos; ?>;
        const incompleteTodos = <?php echo $incomplete_count; ?>;
        const completedCount = <?php echo $completed_count; ?>;
    </script>

</body>
</html>