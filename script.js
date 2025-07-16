/* ===== SCRIPT.JS - TODO APP BY M. ROHID RIVALDI ===== */

$(document).ready(function() {
    // Show main app after loading screen
    setTimeout(function() {
        $('#loadingScreen').fadeOut(500, function() {
            $('#mainApp').fadeIn(500);
        });
    }, 2000);

    // Load theme preference from local storage
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        $('html').attr('data-theme', savedTheme);
        updateThemeToggleIcon(savedTheme);
    } else {
        // Default to light theme if no preference is saved
        $('html').attr('data-theme', 'light');
        updateThemeToggleIcon('light');
    }

    // Theme Toggle Button
    $('#themeToggle').on('click', function() {
        toggleTheme();
    });

    // Keyboard shortcut for theme toggle (Ctrl+Alt+D)
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.altKey && e.key === 'd') {
            e.preventDefault();
            toggleTheme();
        }
    });

    function toggleTheme() {
        const currentTheme = $('html').attr('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        $('html').attr('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeToggleIcon(newTheme);
        showNotification(`Theme changed to ${newTheme.charAt(0).toUpperCase() + newTheme.slice(1)} Mode`, 'info');

        // Animate icon rotation
        const icon = $('#themeToggle i');
        icon.addClass('fa-spin');
        setTimeout(() => {
            icon.removeClass('fa-spin');
        }, 500); // Match CSS transition duration
    }

    function updateThemeToggleIcon(theme) {
        const icon = $('#themeToggle i');
        if (theme === 'dark') {
            icon.removeClass('fa-moon').addClass('fa-sun');
        } else {
            icon.removeClass('fa-sun').addClass('fa-moon');
        }
    }

    // Function to show notifications
    function showNotification(message, type = 'info') {
        const notificationContainer = $('#notificationContainer');
        const notification = $('<div class="notification fade-in "></div>');
        notification.addClass(type);
        let iconClass = '';
        if (type === 'success') iconClass = 'fas fa-check-circle';
        else if (type === 'error') iconClass = 'fas fa-times-circle';
        else if (type === 'warning') iconClass = 'fas fa-exclamation-triangle';
        else iconClass = 'fas fa-info-circle';

        notification.html(`<i class="${iconClass}"></i> ${message}`);
        notificationContainer.append(notification);

        setTimeout(() => {
            notification.fadeOut(500, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Handle form submission for adding new todo
    $('#todoForm').on('submit', function(e) {
        e.preventDefault();
        // Form will be submitted via PHP, no AJAX here for initial add
        // The page will reload and PHP will handle the new todo
        showNotification('Tugas berhasil ditambahkan!', 'success');
        this.submit();
    });

    // Handle checkbox change for todo status
    $(document).on('change', '.todo-checkbox', function() {
        const checkbox = $(this);
        const key = checkbox.data('key');
        const todoItem = checkbox.closest('.todo-item');
        const newStatus = checkbox.is(':checked') ? 1 : 0;

        todoItem.addClass('updating'); // Add updating class for spinner

        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                ajax_action: 'toggle_status',
                key: key,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    updateTodoDisplay(response.sortedTodos, response.stats);
                    checkConfetti(response.stats.completed, response.stats.total);
                } else {
                    showNotification(response.message, 'error');
                    checkbox.prop('checked', !newStatus); // Revert checkbox state on error
                }
            },
            error: function() {
                showNotification('Terjadi kesalahan saat memperbarui status.', 'error');
                checkbox.prop('checked', !newStatus); // Revert checkbox state on error
            },
            complete: function() {
                todoItem.removeClass('updating'); // Remove updating class
            }
        });
    });

    // Function to update the todo list display and statistics
    function updateTodoDisplay(sortedTodos, stats) {
        const incompleteTodosContainer = $('#incompleteTodos');
        const completedTodosContainer = $('#completedTodos');
        const emptyState = $('#emptyState');
        const incompleteSection = $('#incompleteSection');
        const completedSection = $('#completedSection');

        incompleteTodosContainer.empty();
        completedTodosContainer.empty();

        let incompleteCount = 0;
        let completedCount = 0;

        if (sortedTodos.length === 0) {
            emptyState.show();
            incompleteSection.hide();
            completedSection.hide();
        } else {
            emptyState.hide();
            sortedTodos.forEach(item => {
                const todo = item.todo;
                const key = item.key;
                const priorityClass = getPriorityClass(todo.priority);
                const dueDateHtml = todo.due_date ? `<span class="due-date-badge ${todo.status == 1 ? 'completed' : ''}">${formatDueDate(todo.due_date, todo.status)}</span>` : '';
                const createdTimeHtml = todo.created_at ? `<small class="text-muted created-time"><i class="fas fa-clock me-1"></i>Dibuat: ${formatDateTime(todo.created_at)}</small>` : '';

                const todoHtml = `
                    <div class="todo-item fade-in ${todo.status == 1 ? 'completed' : ''} ${priorityClass}" data-key="${key}" data-status="${todo.status}">
                        <div class="d-flex align-items-center">
                            <input type="checkbox" class="todo-checkbox" data-key="${key}" ${todo.status == 1 ? 'checked' : ''}>
                            <div class="todo-content flex-grow-1">
                                <p class="todo-text ${todo.status == 1 ? 'completed' : ''}" ondblclick="editTodo(${key})">
                                    ${escapeHtml(todo.todo)}
                                </p>
                                <div class="todo-meta">
                                    <span class="priority-badge ${todo.status == 1 ? 'completed' : ''}">
                                        ${getPriorityLabel(todo.priority)}
                                    </span>
                                    ${dueDateHtml}
                                </div>
                            </div>
                            <button class="btn-delete" onclick="deleteTodo(${key})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        ${createdTimeHtml}
                    </div>
                `;

                if (todo.status == 0) {
                    incompleteTodosContainer.append(todoHtml);
                    incompleteCount++;
                } else {
                    completedTodosContainer.append(todoHtml);
                    completedCount++;
                }
            });

            if (incompleteCount > 0) {
                incompleteSection.show();
                $('#incompleteCount').text(incompleteCount);
            } else {
                incompleteSection.hide();
            }

            if (completedCount > 0) {
                completedSection.show();
                $('#completedSectionCount').text(completedCount);
            } else {
                completedSection.hide();
            }
        }

        // Update statistics
        $('#totalCount').html(`<i class="fas fa-list me-2"></i>${stats.total}`);
        $('#completedCount').html(`<i class="fas fa-check-circle me-2"></i>${stats.completed}`);
        $('#urgentCount').html(`<i class="fas fa-exclamation-triangle me-2"></i>${stats.urgent}`);
        $('#overdueCount').html(`<i class="fas fa-clock me-2"></i>${stats.overdue}`);

        const progressPercentage = stats.total > 0 ? (stats.completed / stats.total) * 100 : 0;
        $('.progress-bar').css('width', progressPercentage + '%');
        $('.progress-text').text(`${Math.round(progressPercentage)}%`);
    }

    // Helper functions (copied from PHP logic for consistency)
    function getPriorityClass(priority) {
        switch(priority) {
            case 'high': return 'priority-high';
            case 'medium': return 'priority-medium';
            case 'low': return 'priority-low';
            default: return 'priority-medium';
        }
    }

    function getPriorityLabel(priority) {
        switch(priority) {
            case 'high': return '<i class="fas fa-exclamation-triangle text-danger"></i> Urgent';
            case 'medium': return '<i class="fas fa-minus-circle text-warning"></i> Normal';
            case 'low': return '<i class="fas fa-circle text-success"></i> Rendah';
            default: return '<i class="fas fa-minus-circle text-warning"></i> Normal';
        }
    }

    function formatDueDate(due_date, status) {
        if (!due_date) return null;
        
        const date = new Date(due_date);
        const now = new Date();
        now.setHours(0, 0, 0, 0); // Normalize 'now' to start of day
        date.setHours(0, 0, 0, 0); // Normalize 'date' to start of day

        const diffTime = date.getTime() - now.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
        
        const formatted = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
        
        if (status == 1) {
            return `<i class="fas fa-calendar"></i> ${formatted}`;
        } else if (diffDays < 0) {
            return `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Terlambat (${formatted})</span>`;
        } else if (diffDays === 0) {
            return `<span class="text-warning"><i class="fas fa-clock"></i> Hari ini (${formatted})</span>`;
        } else if (diffDays === 1) {
            return `<span class="text-info"><i class="fas fa-calendar-day"></i> Besok (${formatted})</span>`;
        } else if (diffDays <= 7) {
            return `<span class="text-primary"><i class="fas fa-calendar-week"></i> ${diffDays} hari lagi (${formatted})</span>`;
        } else {
            return `<span class="text-muted"><i class="fas fa-calendar"></i> ${formatted}</span>`;
        }
    }

    function formatDateTime(datetime) {
        const date = new Date(datetime);
        return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            '\'': '&#039;'
        };
        return text.replace(/[&<>"\']/g, function(m) { return map[m]; });
    }

    // Initial display update based on PHP data
    updateTodoDisplay(todoData, {
        total: totalTodos,
        completed: completedTodos,
        urgent: $('#urgentCount').text().replace('\u00a0', '').trim(), // Remove non-breaking space
        overdue: $('#overdueCount').text().replace('\u00a0', '').trim()
    });

    // Confetti animation
    function checkConfetti(completed, total) {
        if (total > 0 && completed === total) {
            showNotification('Selamat! Semua tugas selesai!', 'success');
            for (let i = 0; i < 100; i++) {
                createConfetti();
            }
        }
    }

    function createConfetti() {
        const confetti = $('<div class="confetti"></div>');
        confetti.css({
            width: `${Math.random() * 10 + 5}px`,
            height: `${Math.random() * 10 + 5}px`,
            backgroundColor: `hsl(${Math.random() * 360}, 100%, 50%)`,
            position: 'fixed',
            left: `${Math.random() * 100}vw`,
            top: `-20px`,
            opacity: Math.random(),
            transform: `rotate(${Math.random() * 360}deg)`,
            zIndex: 99999,
            animation: `confettiFall ${Math.random() * 3 + 2}s linear forwards`
        });
        $('body').append(confetti);

        confetti.on('animationend', function() {
            $(this).remove();
        });
    }

    // Double click to edit todo
    window.editTodo = function(key) {
        const todoItem = $(`.todo-item[data-key="${key}"]`);
        const todoTextElement = todoItem.find('.todo-text');
        const currentText = todoTextElement.text().trim();

        // Prevent editing completed todos
        if (todoItem.hasClass('completed')) {
            showNotification('Tidak bisa mengedit tugas yang sudah selesai.', 'warning');
            return;
        }

        const inputField = $('<input type="text" class="form-control edit-todo-input">')
        .val(currentText);
        todoTextElement.replaceWith(inputField);
        inputField.focus();

        const saveChanges = function() {
            const newText = inputField.val().trim();
            if (newText && newText !== currentText) {
                // Send AJAX request to update todo
                $.ajax({
                    url: 'index.php', // Assuming index.php handles updates
                    type: 'POST',
                    data: {
                        ajax_action: 'update_todo_text',
                        key: key,
                        new_text: newText
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showNotification('Tugas berhasil diperbarui!', 'success');
                            todoTextElement.text(newText);
                            inputField.replaceWith(todoTextElement);
                            updateTodoDisplay(response.sortedTodos, response.stats);
                        } else {
                            showNotification(response.message, 'error');
                            todoTextElement.text(currentText);
                            inputField.replaceWith(todoTextElement);
                        }
                    },
                    error: function() {
                        showNotification('Terjadi kesalahan saat memperbarui tugas.', 'error');
                        todoTextElement.text(currentText);
                        inputField.replaceWith(todoTextElement);
                    }
                });
            } else {
                todoTextElement.text(currentText);
                inputField.replaceWith(todoTextElement);
            }
        };

        inputField.on('blur', saveChanges);
        inputField.on('keydown', function(e) {
            if (e.key === 'Enter') {
                inputField.off('blur'); // Prevent blur from firing twice
                saveChanges();
            } else if (e.key === 'Escape') {
                todoTextElement.text(currentText);
                inputField.replaceWith(todoTextElement);
            }
        });
    };

    // Delete todo function
    window.deleteTodo = function(key) {
        if (confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
            $.ajax({
                url: 'index.php',
                type: 'GET',
                data: {
                    hapus: true,
                    key: key
                },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    if (response.success) {
                        showNotification('Tugas berhasil dihapus!', 'success');
                        updateTodoDisplay(response.sortedTodos, response.stats);
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Terjadi kesalahan saat menghapus tugas.', 'error');
                }
            });
        }
    };

    // Keyboard shortcuts for priority
    $(document).on('keydown', function(e) {
        if (e.altKey) {
            if (e.key === '1') {
                $('#priority').val('low').change();
                showNotification('Prioritas diatur ke Rendah', 'info');
            } else if (e.key === '2') {
                $('#priority').val('medium').change();
                showNotification('Prioritas diatur ke Normal', 'info');
            } else if (e.key === '3') {
                $('#priority').val('high').change();
                showNotification('Prioritas diatur ke Urgent', 'info');
            }
        }
    });

    // Keyboard shortcut for setting due date to today (Ctrl+Alt+T)
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.altKey && e.key === 't') {
            e.preventDefault();
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0'); // Months start at 0!
            const dd = String(today.getDate()).padStart(2, '0');
            const todayFormatted = `${yyyy}-${mm}-${dd}`;
            $('#due_date').val(todayFormatted);
            showNotification('Deadline diatur ke Hari Ini', 'info');
        }
    });

    // Keyboard shortcut for clearing form (Esc)
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#todoInput').val('');
            $('#due_date').val('');
            $('#priority').val('medium');
            showNotification('Form dibersihkan', 'info');
        }
    });

    // Keyboard shortcut for export (Ctrl+Alt+E)
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.altKey && e.key === 'e') {
            e.preventDefault();
            exportData();
        }
    });

    function exportData() {
        // This is a placeholder. In a real app, you'd fetch data and format it.
        // For now, we'll just show a notification.
        showNotification('Fitur export data belum diimplementasikan sepenuhnya.', 'warning');
        // Example: window.location.href = 'export.php';
    }

    // Character counter for todo input
    $('#todoInput').on('input', function() {
        const maxLength = 255; // Example max length
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        let counterHtml = `<small class="character-counter text-muted">${currentLength}/${maxLength} karakter</small>`;
        if (remaining < 20 && remaining >= 0) {
            counterHtml = `<small class="character-counter text-warning">${currentLength}/${maxLength} karakter (sisa ${remaining})</small>`;
        } else if (remaining < 0) {
            counterHtml = `<small class="character-counter text-danger">${currentLength}/${maxLength} karakter (kelebihan ${Math.abs(remaining)})</small>`;
        }
        
        if ($(this).next('.character-counter').length) {
            $(this).next('.character-counter').replaceWith(counterHtml);
        } else {
            $(this).after(counterHtml);
        }
    }).trigger('input'); // Trigger on load to show initial count
});