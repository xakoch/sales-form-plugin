<?php
/**
 * Файл для страницы просмотра заявок
 */

if (!defined('ABSPATH')) exit;

function my_forms_requests_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Определяем текущую страницу для пагинации
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20; // Количество записей на странице
    $offset = ($current_page - 1) * $per_page;
    
    // Определяем форму для фильтрации, если задана
    $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    
    // Формируем условие WHERE для запроса
    $where_clause = "WHERE f.user_id = %d";
    $where_params = array($user_id);
    
    if ($form_id > 0) {
        $where_clause .= " AND r.form_id = %d";
        $where_params[] = $form_id;
    }
    
    // Получение списка заявок
    $requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, f.title as form_title, f.token 
            FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            $where_clause
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d",
            array_merge($where_params, array($per_page, $offset))
        )
    );
    
    // Получаем общее количество заявок для пагинации
    $total_requests = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            $where_clause",
            $where_params
        )
    );
    
    // Получаем список всех форм для фильтра
    $forms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, title
            FROM {$wpdb->prefix}my_forms
            WHERE user_id = %d
            ORDER BY created_at DESC",
            $user_id
        )
    );
    
    // Рассчитываем параметры пагинации
    $total_pages = ceil($total_requests / $per_page);
    
    // Формируем HTML страницы
    ?>
    <div class="wrap my-forms-admin-page">
        <h1 class="wp-heading-inline">Заявки с форм</h1>
        
        <!-- Хлебные крошки -->
        <div class="my-forms-breadcrumbs">
            <a href="<?php echo admin_url('admin.php?page=my-forms-dashboard'); ?>">Дашборд</a>
            <span class="separator"> &gt; </span>
            <span class="current">Заявки</span>
        </div>
        
        <div class="my-forms-section">
            <!-- Фильтр по формам -->
            <div class="my-forms-filters">
                <form method="get" class="my-forms-filter-form">
                    <input type="hidden" name="page" value="my-forms-requests">
                    <div class="filter-group">
                        <label for="form_id">Фильтр по форме:</label>
                        <select name="form_id" id="form_id" class="form-select">
                            <option value="0">Все формы</option>
                            <?php foreach ($forms as $form) : ?>
                                <option value="<?php echo $form->id; ?>" <?php selected($form_id, $form->id); ?>>
                                    <?php echo esc_html($form->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button">Применить</button>
                        
                        <?php if ($form_id > 0) : ?>
                            <a href="<?php echo admin_url('admin.php?page=my-forms-requests'); ?>" class="button">Сбросить</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Таблица заявок -->
            <?php if (empty($requests)) : ?>
                <div class="my-forms-empty-state">
                    <p>Заявок не найдено.</p>
                    <?php if ($form_id > 0) : ?>
                        <p>Попробуйте изменить параметры фильтрации.</p>
                    <?php else : ?>
                        <p>Когда пользователи заполнят ваши формы, заявки появятся здесь.</p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="tablenav top">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_requests; ?> заявок</span>
                        <?php if ($total_pages > 1) : ?>
                            <span class="pagination-links">
                                <?php
                                // Строим ссылки для пагинации
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                echo $page_links;
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped my-forms-requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Форма</th>
                            <th>ФИО</th>
                            <th>Дополнительные данные</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request) : ?>
                            <tr>
                                <td><?php echo $request->id; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=my-forms-requests&form_id=' . $request->form_id); ?>">
                                        <?php echo esc_html($request->form_title); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($request->full_name); ?></td>
                                <td>
                                    <?php 
                                    $meta_data = json_decode($request->meta_data, true);
                                    if (is_array($meta_data) && !empty($meta_data)) {
                                        echo '<button class="button view-details" data-request-id="' . $request->id . '">Просмотр деталей</button>';
                                        
                                        // Скрытый блок с деталями
                                        echo '<div id="request-details-' . $request->id . '" class="request-details-modal" style="display:none;" title="Детали заявки #' . $request->id . '">';
                                        echo '<table class="widefat fixed">';
                                        foreach ($meta_data as $key => $value) {
                                            echo '<tr>';
                                            echo '<th>' . esc_html($key) . '</th>';
                                            echo '<td>' . esc_html($value) . '</td>';
                                            echo '</tr>';
                                        }
                                        echo '</table>';
                                        echo '</div>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date_i18n('d.m.Y H:i', strtotime($request->created_at)); ?></td>
                                <td>
                                    <select class="request-status" data-request-id="<?php echo $request->id; ?>">
                                        <option value="new" <?php selected($request->status, 'new'); ?>>Новая</option>
                                        <option value="processing" <?php selected($request->status, 'processing'); ?>>В обработке</option>
                                        <option value="completed" <?php selected($request->status, 'completed'); ?>>Выполнена</option>
                                        <option value="rejected" <?php selected($request->status, 'rejected'); ?>>Отклонена</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Пагинация внизу -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo $total_requests; ?> заявок</span>
                            <span class="pagination-links">
                                <?php echo $page_links; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Инициализация диалоговых окон для деталей заявок
        if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
            jQuery(".request-details-modal").dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                maxHeight: 500
            });
        }
        
        // Открытие модального окна с деталями
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-details')) {
                var button = e.target.closest('.view-details');
                var requestId = button.getAttribute('data-request-id');
                if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                    jQuery("#request-details-" + requestId).dialog("open");
                }
            }
        });
        
        // Экспорт заявки в CSV
        document.addEventListener('click', function(e) {
            if (e.target.closest('.export-request')) {
                e.preventDefault();
                var exportButton = e.target.closest('.export-request');
                var requestId = exportButton.getAttribute('data-request-id');
                
                // Получаем данные из таблицы
                var row = exportButton.closest("tr");
                var formName = row.cells[1].textContent.trim();
                var fullName = row.cells[2].textContent.trim();
                var date = row.cells[4].textContent.trim();
                
                // Получаем дополнительные данные
                var metaData = {};
                var detailsModal = document.getElementById("request-details-" + requestId);
                
                if (detailsModal) {
                    var rows = detailsModal.querySelectorAll("table tr");
                    rows.forEach(function(row) {
                        var key = row.querySelector("th").textContent.trim();
                        var value = row.querySelector("td").textContent.trim();
                        metaData[key] = value;
                    });
                }
                
                // Формируем CSV содержимое
                var csvContent = "Заявка #" + requestId + "\n";
                csvContent += "Форма:," + formName + "\n";
                csvContent += "ФИО:," + fullName + "\n";
                csvContent += "Дата:," + date + "\n\n";
                
                // Добавляем дополнительные данные
                csvContent += "Дополнительные данные:\n";
                for (var key in metaData) {
                    csvContent += key + "," + metaData[key] + "\n";
                }
                
                // Создаем временный элемент для скачивания
                var encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
                var link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "request_" + requestId + ".csv");
                document.body.appendChild(link);
                
                // Запускаем скачивание
                link.click();
                
                // Удаляем временный элемент
                document.body.removeChild(link);
            }
        });
    });
    </script>
    
    <style>
    .my-forms-filters {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-select {
        min-width: 200px;
    }
    
    .my-forms-empty-state {
        text-align: center;
        padding: 40px 0;
    }
    
    .my-forms-requests-table th,
    .my-forms-requests-table td {
        padding: 10px;
    }
    </style>
    <?php
}