<?php
// dashboard.php
session_start();

// Check if user is logged in first
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include required files
include_once 'config/database.php'; // Sudah berisi class Task juga

$database = new Database();
$db = $database->getConnection();
$task = new Task($db);
$task->user_id = $_SESSION['user_id'];

// Handle AJAX requests - pindahkan ke atas sebelum output HTML
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Set proper content type
    header('Content-Type: application/json');
    
    // Turn off error display to prevent HTML in JSON response
    ini_set('display_errors', 0);
    
    try {
        switch($_POST['action']) {
            case 'create':
                // Validate input
                if (empty($_POST['title']) || empty($_POST['category']) || empty($_POST['priority']) || empty($_POST['due_date'])) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit();
                }
                
                $task->title = $_POST['title'];
                $task->category = $_POST['category'];
                $task->priority = $_POST['priority'];
                $task->status = 'todo';
                $task->due_date = $_POST['due_date'];
                
                if($task->create()) {
                    echo json_encode(['success' => true, 'message' => 'Task berhasil ditambahkan']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan task']);
                }
                exit();
                
            case 'update':
                // Validate input
                if (empty($_POST['id']) || empty($_POST['title']) || empty($_POST['category']) || empty($_POST['priority']) || empty($_POST['due_date'])) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit();
                }
                
                $task->id = $_POST['id'];
                $task->title = $_POST['title'];
                $task->category = $_POST['category'];
                $task->priority = $_POST['priority'];
                $task->status = $_POST['status'] ?? 'todo';
                $task->due_date = $_POST['due_date'];
                
                if($task->update()) {
                    echo json_encode(['success' => true, 'message' => 'Task berhasil diupdate']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate task']);
                }
                exit();
                
            case 'delete':
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
                    exit();
                }
                
                $task->id = $_POST['id'];
                
                if($task->delete()) {
                    echo json_encode(['success' => true, 'message' => 'Task berhasil dihapus']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal menghapus task']);
                }
                exit();
                
            case 'toggle_status':
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
                    exit();
                }
                
                $task->id = $_POST['id'];
                // Get current task data
                $query = "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $task->id);
                $stmt->bindParam(":user_id", $task->user_id);
                $stmt->execute();
                $current_task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($current_task) {
                    $task->title = $current_task['title'];
                    $task->category = $current_task['category'];
                    $task->priority = $current_task['priority'];
                    $task->due_date = $current_task['due_date'];
                    $task->status = $current_task['status'] == 'completed' ? 'todo' : 'completed';
                    
                    if($task->update()) {
                        echo json_encode(['success' => true, 'new_status' => $task->status]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal mengubah status']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Task not found']);
                }
                exit();
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit();
    }
}

// Get tasks and stats untuk display
$stmt = $task->readAll();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = $task->getStats();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KitaListinAja</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }

        .logo i {
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date {
            color: #666;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 280px;
            background: white;
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .category-item, .priority-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .category-item:hover, .priority-item:hover {
            background: #f8f9fa;
        }

        .category-item.active, .priority-item.active {
            background: #667eea;
            color: white;
        }

        .category-item i, .priority-item i {
            margin-right: 10px;
            width: 20px;
        }

        .progress-section {
            margin-top: 30px;
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.3s;
        }

        .progress-stats {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .stat-completed {
            background: #d4edda;
            color: #155724;
        }

        .stat-ongoing {
            background: #fff3cd;
            color: #856404;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            color: #333;
            margin-bottom: 20px;
        }

        .create-task-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: transform 0.2s;
        }

        .create-task-btn:hover {
            transform: translateY(-2px);
        }

        .tasks-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .task-column {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .column-title {
            font-weight: bold;
            color: #333;
        }

        .task-count {
            background: #e9ecef;
            color: #666;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .task-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }

        .task-item:hover {
            transform: translateX(5px);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .task-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .task-date {
            font-size: 12px;
            color: #e74c3c;
        }

        .task-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .tag {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .tag-school { background: #e3f2fd; color: #1976d2; }
        .tag-work { background: #f3e5f5; color: #7b1fa2; }
        .tag-personal { background: #e8f5e8; color: #388e3c; }
        .tag-shopping { background: #fff3e0; color: #f57c00; }
        .tag-health { background: #ffebee; color: #d32f2f; }

        .tag-high { background: #ffebee; color: #d32f2f; }
        .tag-medium { background: #fff3e0; color: #f57c00; }
        .tag-low { background: #e8f5e8; color: #388e3c; }

        .task-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .btn-edit {
            color: #667eea;
        }

        .btn-edit:hover {
            background: #f0f0ff;
        }

        .btn-delete {
            color: #e74c3c;
        }

        .btn-delete:hover {
            background: #ffebee;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .completed {
            opacity: 0.7;
            text-decoration: line-through;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 20px;
            }
            
            .tasks-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="logo.png" alt="KitaListinAja Logo" style="height: 65px; width: auto;">
        </div>

        <div class="user-info">
            <span class="date"><?php echo date('d F Y'); ?></span>
            <span>Hi, <?php echo $_SESSION['username']; ?>!</span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3>All Tasks</h3>
            
            <div class="categories">
                <h4 style="margin-bottom: 15px; color: #666;">Categories</h4>
                <div class="category-item active" data-category="all">
                    <i class="fas fa-list"></i>
                    All Tasks
                </div>
                <div class="category-item" data-category="work">
                    <i class="fas fa-briefcase"></i>
                    Work
                </div>
                <div class="category-item" data-category="school">
                    <i class="fas fa-graduation-cap"></i>
                    School
                </div>
                <div class="category-item" data-category="personal">
                    <i class="fas fa-user"></i>
                    Personal
                </div>
                <div class="category-item" data-category="shopping">
                    <i class="fas fa-shopping-cart"></i>
                    Shopping
                </div>
                <div class="category-item" data-category="health">
                    <i class="fas fa-heart"></i>
                    Health
                </div>
            </div>

            <div style="margin-top: 30px;">
                <h4 style="margin-bottom: 15px; color: #666;">Priority</h4>
                <div class="priority-item" data-priority="high">
                    <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    High
                </div>
                <div class="priority-item" data-priority="medium">
                    <i class="fas fa-minus" style="color: #f39c12;"></i>
                    Medium
                </div>
                <div class="priority-item" data-priority="low">
                    <i class="fas fa-arrow-down" style="color: #27ae60;"></i>
                    Low
                </div>
            </div>

            <div class="progress-section">
                <h4 style="margin-bottom: 15px; color: #666;">Progress</h4>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $stats['total'] > 0 ? ($stats['completed'] / $stats['total'] * 100) : 0; ?>%"></div>
                </div>
                <div style="text-align: center; margin-top: 5px; font-size: 14px; color: #666;">
                    <?php echo $stats['total'] > 0 ? round($stats['completed'] / $stats['total'] * 100) : 0; ?>%
                </div>
                <div class="progress-stats">
                    <div class="stat-item stat-completed">
                        Completed: <?php echo $stats['completed']; ?>
                    </div>
                    <div class="stat-item stat-ongoing">
                        On Going: <?php echo $stats['todo']; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="welcome-section">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>Welcome, <?php echo $_SESSION['username']; ?></h1>
                        <p style="color: #666;">Kelola tugas Anda dengan mudah dan efisien</p>
                    </div>
                    <button class="create-task-btn" onclick="openModal()">
                        <i class="fas fa-plus"></i>
                        Create Task
                    </button>
                </div>
            </div>

            <div class="tasks-section">
                <div class="task-column">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fas fa-clock" style="color: #f39c12; margin-right: 8px;"></i>
                            To do
                        </span>
                        <span class="task-count" id="todo-count"><?php echo count(array_filter($tasks, function($t) { return $t['status'] == 'todo'; })); ?></span>
                    </div>
                    <div id="todo-tasks">
                        <?php foreach($tasks as $t): if($t['status'] == 'todo'): ?>
                        <div class="task-item" data-id="<?php echo $t['id']; ?>" data-category="<?php echo $t['category']; ?>" data-priority="<?php echo $t['priority']; ?>">
                            <div class="task-header">
                                <div>
                                    <div class="task-title"><?php echo htmlspecialchars($t['title']); ?></div>
                                    <div class="task-date"><?php echo date('d M Y', strtotime($t['due_date'])); ?></div>
                                </div>
                            </div>
                            <div class="task-tags">
                                <span class="tag tag-<?php echo $t['category']; ?>"><?php echo ucfirst($t['category']); ?></span>
                                <span class="tag tag-<?php echo $t['priority']; ?>"><?php echo ucfirst($t['priority']); ?></span>
                            </div>
                            <div class="task-actions">
                                <input type="checkbox" class="task-checkbox" onchange="toggleStatus(<?php echo $t['id']; ?>)">
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editTask(<?php echo $t['id']; ?>, <?php echo htmlspecialchars(json_encode($t['title']), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $t['category']; ?>', '<?php echo $t['priority']; ?>', '<?php echo $t['due_date']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="deleteTask(<?php echo $t['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <div class="task-column">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fas fa-check-circle" style="color: #27ae60; margin-right: 8px;"></i>
                            Done
                        </span>
                        <span class="task-count" id="done-count"><?php echo count(array_filter($tasks, function($t) { return $t['status'] == 'completed'; })); ?></span>
                    </div>
                        <div id="done-tasks">
                            <?php foreach($tasks as $t): if($t['status'] == 'completed'): ?>
                            <div class="task-item completed" data-id="<?php echo $t['id']; ?>" data-category="<?php echo $t['category']; ?>" data-priority="<?php echo $t['priority']; ?>">
                                <div class="task-header">
                                    <div>
                                        <div class="task-title"><?php echo htmlspecialchars($t['title']); ?></div>
                                        <div class="task-date"><?php echo date('d M Y', strtotime($t['due_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="task-tags">
                                    <span class="tag tag-<?php echo $t['category']; ?>"><?php echo ucfirst($t['category']); ?></span>
                                    <span class="tag tag-<?php echo $t['priority']; ?>"><?php echo ucfirst($t['priority']); ?></span>
                                </div>
                                <div class="task-actions">
                                    <input type="checkbox" class="task-checkbox" checked onchange="toggleStatus(<?php echo $t['id']; ?>)">
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick="editTask(<?php echo $t['id']; ?>, <?php echo htmlspecialchars(json_encode($t['title']), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo $t['category']; ?>', '<?php echo $t['priority']; ?>', '<?php echo $t['due_date']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteTask(<?php echo $t['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>                    
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Create/Edit Task -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Create New Task</h2>
            <form id="taskForm">
                <input type="hidden" id="taskId" name="id">
                
                <div class="form-group">
                    <label for="taskTitle">Task Title</label>
                    <input type="text" id="taskTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="taskCategory">Category</label>
                    <select id="taskCategory" name="category" required>
                        <option value="">Select Category</option>
                        <option value="work">Work</option>
                        <option value="school">School</option>
                        <option value="personal">Personal</option>
                        <option value="shopping">Shopping</option>
                        <option value="health">Health</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="taskPriority">Priority</label>
                    <select id="taskPriority" name="priority" required>
                        <option value="">Select Priority</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="taskDueDate">Due Date</label>
                    <input type="date" id="taskDueDate" name="due_date" required>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">Create Task</button>
            </form>
        </div>
    </div>

    <script>
        let currentFilter = { category: 'all', priority: 'all' };
        
        // Modal Functions
        function openModal() {
            document.getElementById('taskModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Create New Task';
            document.getElementById('submitBtn').textContent = 'Create Task';
            document.getElementById('taskForm').reset();
            document.getElementById('taskId').value = '';
        }

        function closeModal() {
            document.getElementById('taskModal').style.display = 'none';
        }

        function editTask(id, title, category, priority, dueDate) {
            document.getElementById('taskModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Edit Task';
            document.getElementById('submitBtn').textContent = 'Update Task';
            
            document.getElementById('taskId').value = id;
            document.getElementById('taskTitle').value = title;
            document.getElementById('taskCategory').value = category;
            document.getElementById('taskPriority').value = priority;
            document.getElementById('taskDueDate').value = dueDate;
        }

        // Form Submit Handler
        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const isEdit = document.getElementById('taskId').value !== '';
            formData.append('action', isEdit ? 'update' : 'create');
            
            // Validate form data
            if (!formData.get('title') || !formData.get('category') || !formData.get('priority') || !formData.get('due_date')) {
                alert('Please fill in all fields');
                return;
            }
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if(data.success) {
                    closeModal();
                    location.reload(); // Refresh to show changes
                } else {
                    alert(data.message || 'An error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            });
        });

        // Delete Task
        function deleteTask(id) {
            if(confirm('Apakah Anda yakin ingin menghapus task ini?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan');
                });
            }
        }

        // Toggle Status
        function toggleStatus(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', id);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            });
        }

        // Filter Functions
        function filterTasks() {
            const tasks = document.querySelectorAll('.task-item');
            let todoCount = 0;
            let doneCount = 0;
            
            tasks.forEach(task => {
                const category = task.dataset.category;
                const priority = task.dataset.priority;
                const isCompleted = task.classList.contains('completed');
                
                let show = true;
                
                if(currentFilter.category !== 'all' && category !== currentFilter.category) {
                    show = false;
                }
                
                if(currentFilter.priority !== 'all' && priority !== currentFilter.priority) {
                    show = false;
                }
                
                if(show) {
                    task.style.display = 'block';
                    if(isCompleted) {
                        doneCount++;
                    } else {
                        todoCount++;
                    }
                } else {
                    task.style.display = 'none';
                }
            });
            
            document.getElementById('todo-count').textContent = todoCount;
            document.getElementById('done-count').textContent = doneCount;
        }

        // Category Filter
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                currentFilter.category = this.dataset.category;
                filterTasks();
            });
        });

        // Priority Filter
        document.querySelectorAll('.priority-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.priority-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                currentFilter.priority = this.dataset.priority || 'all';
                filterTasks();
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('taskModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>



