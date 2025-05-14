<?php
/**
 * Файл для страницы управления формами
 */

if (!defined('ABSPATH')) exit;

function my_forms_page() {
    global $wpdb;
    $user_id = get_current_user_id();

    // Сначала обновляем статус всех истекших форм
    $now = current_time('mysql');
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}my_forms SET status = 'expired' 
            WHERE user_id = %d AND status = 'active' AND expires_at <= %s",
            $user_id, $now
        )
    );

    // Обработка создания формы
    if (isset($_POST['create_form'])) {
        // Проверка nonce
        check_admin_referer('create_form_nonce');
        
        // Получаем данные из формы
        $form_name = sanitize_text_field($_POST['form_name']);
        $form_logo = isset($_FILES['form_logo']) ? $_FILES['form_logo'] : null;
        $form_template = sanitize_text_field($_POST['form_template']);
        
        // Создание формы и получение данных
        $form_data = my_forms_create_form($user_id, $form_name, $form_logo, $form_template);
        
        if ($form_data) {
            add_action('admin_notices', function() use ($form_data) {
                echo '<div class="notice notice-success"><p>Форма создана! Ссылка: <a href="' . esc_url($form_data['link']) . '">' . esc_url($form_data['link']) . '</a></p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Ошибка при создании формы</p></div>';
            });
        }
    }

    // Получение списка активных форм
    $forms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT f.*, 
            (SELECT COUNT(*) FROM {$wpdb->prefix}my_forms_requests r WHERE r.form_id = f.id) as requests_count
            FROM {$wpdb->prefix}my_forms f
            WHERE f.user_id = %d 
            AND f.status = 'active'
            ORDER BY f.created_at DESC",
            $user_id
        )
    );

    // Проверка на ошибку БД
    if ($wpdb->last_error) {
        add_action('admin_notices', function() use ($wpdb) {
            echo '<div class="notice notice-error"><p>Ошибка БД: ' . esc_html($wpdb->last_error) . '</p></div>';
        });
    }
    
    // Подключаем ресурсы для страницы
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');
    ?>
    
    <div class="wrap my-forms-admin-page">
        <h1 class="wp-heading-inline">Мои формы</h1>
        <a href="#" class="page-title-action add-new-form">Создать новую форму</a>
        
        <!-- Модальное окно для создания формы -->
        <div id="create-form-modal" title="Создать новую форму" style="display:none;">
            <form method="post" id="create-form-form" enctype="multipart/form-data">
                <?php wp_nonce_field('create_form_nonce'); ?>
                
                <div class="form-field">
                    <label for="form_name">Название формы:</label>
                    <input type="text" id="form_name" name="form_name" required value="">
                </div>
                
                <div class="form-field">
                    <label for="form_logo">Логотип формы:</label>
                    <input type="file" id="form_logo" name="form_logo" accept="image/*">
                    <div id="logo-preview" style="margin-top: 10px; max-width: 200px;"></div>
                </div>
                
                <div class="form-field">
                    <label for="form_template">Шаблон формы:</label>
                    <div class="template-selector">
                        <label class="template-option">
                            <input type="radio" name="form_template" value="default" checked>
                            <div class="template-preview default">
                                <div class="template-header">Стандартный шаблон</div>
                                <div class="template-body">Классический дизайн с синим заголовком</div>
                            </div>
                        </label>
                        
                        <label class="template-option">
                            <input type="radio" name="form_template" value="modern">
                            <div class="template-preview modern">
                                <div class="template-header">Современный шаблон</div>
                                <div class="template-body">Минималистичный дизайн с градиентом</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="submit-wrapper" style="margin-top: 15px;">
                    <button type="submit" name="create_form" class="button button-primary">Создать форму</button>
                    <button type="button" class="button cancel-create">Отмена</button>
                </div>
            </form>
        </div>
        
        <!-- Хлебные крошки -->
        <div class="my-forms-breadcrumbs">
            <a href="<?php echo admin_url('admin.php?page=my-forms-dashboard'); ?>">Дашборд</a>
            <span class="separator"> &gt; </span>
            <span class="current">Формы</span>
        </div>
        
        <!-- Блок с активными формами -->
        <div class="my-forms-section">
            <h2>Активные формы</h2>
            
            <?php if (empty($forms)) : ?>
                <div class="my-forms-empty-state">
                    <p>У вас пока нет активных форм.</p>
                    <button class="button button-primary add-new-form">Создать первую форму</button>
                </div>
            <?php else : ?>
                <div class="my-forms-cards">
                    <?php foreach ($forms as $form) : 
// Рассчитываем оставшееся время
$now = current_time('timestamp');
$expires = strtotime($form->expires_at);
$time_left = $expires - $now;
$hours_left = floor($time_left / 3600);  // Используем floor вместо round
                        
                        // Определяем класс для статуса (для цветового выделения)
                        $status_class = '';
                        if ($hours_left <= 1) {
                            $status_class = 'expiring-soon';
                        } elseif ($hours_left <= 6) {
                            $status_class = 'expiring-warning';
                        } else {
                            $status_class = 'active';
                        }
                        
                        // Получаем путь к логотипу
                        $logo_url = '';
                        if (!empty($form->logo_path)) {
                            $upload_dir = wp_upload_dir();
                            $logo_url = $upload_dir['baseurl'] . '/my-forms-logos/' . $form->logo_path;
                        }
                    ?>
                        <div class="my-form-card <?php echo $status_class; ?>">
                            <div class="form-header">
                                <?php if ($logo_url) : ?>
                                    <div class="form-logo">
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                                    </div>
                                <?php endif; ?>
                                <h3 class="form-title" data-form-id="<?php echo $form->id; ?>">
                                    <span class="title-text"><?php echo esc_html($form->title); ?></span>
                                    <a href="#" class="edit-title" title="Изменить название"><span class="dashicons dashicons-edit"></span></a>
                                </h3>
                                <div class="form-actions">
                                    <a href="<?php echo esc_url($form->link); ?>" class="button view-form" target="_blank">Просмотр</a>
                                    <button class="button copy-link" data-link="<?php echo esc_url($form->link); ?>">Копировать ссылку</button>
                                    <a href="#" class="delete-form" data-form-id="<?php echo $form->id; ?>" title="Удалить форму">
                                        <span class="dashicons dashicons-trash" style="color: #dc3232;"></span>
                                    </a>
                                </div>
                            </div>
                            <div class="form-details">
                                <div class="form-detail">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <span>Создана: <?php echo date_i18n('d.m.Y H:i', strtotime($form->created_at)); ?></span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-clock"></span>
                                    <span class="time-left" data-expires-at="<?php echo esc_attr($form->expires_at); ?>">
                                        Осталось: <?php echo $hours_left; ?> ч.
                                    </span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span>
                                        <a href="<?php echo admin_url('admin.php?page=my-forms-requests&form_id=' . $form->id); ?>">
                                            Заявок: <?php echo $form->requests_count; ?>
                                        </a>
                                    </span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <span>Шаблон: <?php echo $form->template === 'modern' ? 'Современный' : 'Стандартный'; ?></span>
                                </div>
                            </div>
                            <div class="form-link">
                                <input type="text" readonly value="<?php echo esc_url($form->link); ?>" class="form-link-field" 
                                       onclick="this.select();" aria-label="Ссылка на форму">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Модальное окно для редактирования названия -->
    <div id="edit-title-modal" title="Изменить название формы" style="display:none;">
        <form id="edit-title-form">
            <input type="hidden" id="edit-form-id" name="form_id" value="">
            <div class="form-field">
                <label for="edit-form-title">Название формы:</label>
                <input type="text" id="edit-form-title" name="title" value="" required>
            </div>
            <div class="form-field">
                <label for="edit-form-comment">Комментарий:</label>
                <textarea id="edit-form-comment" name="comment" rows="3"></textarea>
            </div>
            <div class="submit-wrapper" style="margin-top: 15px;">
                <button type="submit" class="button button-primary">Сохранить</button>
                <button type="button" class="button cancel-edit">Отмена</button>
            </div>
        </form>
    </div>
    
    <style>
    .form-field {
        margin-bottom: 15px;
    }
    
    .form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-field input[type="text"],
    .form-field textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .template-selector {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .template-option {
        cursor: pointer;
        flex: 1;
        min-width: 150px;
    }
    
    .template-option input[type="radio"] {
        display: none;
    }
    
    .template-preview {
        border: 2px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .template-option input[type="radio"]:checked + .template-preview {
        border-color: #2271b1;
        box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
    }
    
    .template-preview.default .template-header {
        background: #2271b1;
        color: white;
        padding: 15px;
        text-align: center;
        font-weight: bold;
    }
    
    .template-preview.modern .template-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        text-align: center;
        font-weight: bold;
    }
    
    .template-preview .template-body {
        padding: 10px;
        background: #f9f9f9;
        color: #666;
        text-align: center;
    }
    
    #logo-preview img {
        max-width: 100%;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-logo {
        width: 60px;
        height: 60px;
        margin-bottom: 10px;
        overflow: hidden;
        border-radius: 4px;
    }
    
    .form-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #f9f9f9;
    }
    </style>
    <?php
}