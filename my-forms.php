<?php
/**
 * Plugin Name: My Forms
 * Description: Плагин для управления временными формами.
 * Version: 1.0
 * Author: Ваше имя
 */

if (!defined('ABSPATH')) exit;

// Подключение зависимостей
require_once plugin_dir_path(__FILE__) . 'includes/security.php';
require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'admin/my-forms.php';
require_once plugin_dir_path(__FILE__) . 'admin/requests.php';
require_once plugin_dir_path(__FILE__) . 'admin/expired.php';

// Регистрация меню
add_action('admin_menu', 'my_forms_menu');
function my_forms_menu() {
    add_menu_page(
        'My Forms Dashboard',
        'My Forms',
        'read',
        'my-forms-dashboard',
        'my_forms_dashboard_page',
        'dashicons-feedback',
        6
    );
    add_submenu_page(
        'my-forms-dashboard',
        'Dashboard',
        'Dashboard',
        'read',
        'my-forms-dashboard',
        'my_forms_dashboard_page'
    );
    add_submenu_page(
        'my-forms-dashboard',
        'My Forms',
        'Forms',
        'read',
        'my-forms',
        'my_forms_page'
    );
    add_submenu_page(
        'my-forms-dashboard',
        'Requests',
        'Requests',
        'read',
        'my-forms-requests',
        'my_forms_requests_page'
    );
    add_submenu_page(
        'my-forms-dashboard',
        'Expired Forms',
        'Expired Forms',
        'read',
        'my-forms-expired',
        'my_forms_expired_page'
    );
}

// Редирект после входа
add_action('wp_login', 'redirect_to_dashboard', 10, 2);
function redirect_to_dashboard($user_login, $user) {
    wp_redirect(admin_url('admin.php?page=my-forms-dashboard'));
    exit;
}

// Регистрация CRON
add_action('my_forms_daily_cleanup', 'expire_forms');
if (!wp_next_scheduled('my_forms_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'my_forms_daily_cleanup');
}

// Подключение стилей и скриптов
add_action('admin_enqueue_scripts', 'my_forms_assets');
function my_forms_assets($hook) {
    if (strpos($hook, 'my-forms') !== false) {
        wp_enqueue_style('my-forms-style', plugins_url('assets/style.css', __FILE__));
        wp_enqueue_style('dashicons');
        
        wp_enqueue_script('jquery'); // Сначала jQuery
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        // Только после загрузки jQuery и его зависимостей:
        wp_enqueue_script('my-forms-script', plugins_url('assets/script.js', __FILE__), 
            array('jquery', 'jquery-ui-dialog'), '1.0.5', true);
        
        // Добавляем nonce для AJAX-запросов
        wp_localize_script('my-forms-script', 'myFormsAjax', array(
            'nonce' => wp_create_nonce('my_forms_ajax_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
}

// Функция активации плагина
register_activation_hook(__FILE__, 'my_forms_activate');
function my_forms_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Создание таблицы `my_forms`
    $forms_table = $wpdb->prefix . 'my_forms';
    $sql = "CREATE TABLE $forms_table (
        id INT NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) NOT NULL,
        title VARCHAR(255) NOT NULL,
        token VARCHAR(32) NOT NULL,
        link VARCHAR(255) NOT NULL,
        user_agent VARCHAR(255) DEFAULT '',
        template VARCHAR(50) DEFAULT 'default',
        logo_path VARCHAR(255) DEFAULT '',
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        status ENUM('active', 'expired') DEFAULT 'active',
        comment TEXT DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Создание таблицы для запросов/заявок
    $requests_table = $wpdb->prefix . 'my_forms_requests';
    $sql_requests = "CREATE TABLE $requests_table (
        id INT NOT NULL AUTO_INCREMENT,
        form_id INT NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        meta_data LONGTEXT,
        source VARCHAR(255),
        created_at DATETIME NOT NULL,
        comment TEXT DEFAULT '',
        status ENUM('new', 'processing', 'completed', 'rejected') DEFAULT 'new',
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    dbDelta($sql_requests);
    
    // Создаем правила перезаписи и сбрасываем правила
    my_forms_rewrite_rules();
    flush_rewrite_rules();
    
    // Создаем директорию для логотипов
    $upload_dir = wp_upload_dir();
    $logos_dir = $upload_dir['basedir'] . '/my-forms-logos';
    
    if (!file_exists($logos_dir)) {
        wp_mkdir_p($logos_dir);
    }
}

// Функция деактивации плагина
register_deactivation_hook(__FILE__, 'my_forms_deactivate');
function my_forms_deactivate() {
    // Отменяем CRON-задание при деактивации
    $timestamp = wp_next_scheduled('my_forms_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'my_forms_daily_cleanup');
    }
    
    // Сбрасываем правила перезаписи
    flush_rewrite_rules();
}

// Регистрация rewrite rules для URL вида /form/{token}
add_action('init', 'my_forms_rewrite_rules', 10);
function my_forms_rewrite_rules() {
    add_rewrite_rule(
        '^form/([^/]+)/?$',
        'index.php?my_form_token=$matches[1]',
        'top'
    );
    add_rewrite_tag('%my_form_token%', '([^/]+)');
}

// Функция для получения следующего номера формы
function my_forms_get_next_form_number($user_id) {
    global $wpdb;
    
    // Получаем максимальный текущий номер формы
    $max_number = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(title, '-', -1) AS UNSIGNED)) 
            FROM {$wpdb->prefix}my_forms 
            WHERE user_id = %d 
            AND title REGEXP '^Форма-[0-9]+$'", // Изменено с 'Form-' на 'Форма-'
            $user_id
        )
    );
    
    // Если нет форм, начинаем с 1
    if ($max_number === null) {
        return 1;
    }
    
    return $max_number + 1;
}

// Функция для создания временной формы
function my_forms_create_form($user_id, $form_name = '', $form_logo = null, $form_template = 'default') {
    global $wpdb;
    
    if (!$user_id) {
        return false;
    }
    
    // Если название не указано, генерируем номер автоматически
    if (empty($form_name)) {
        $form_number = my_forms_get_next_form_number($user_id);
        $title = 'Форма-' . $form_number;
    } else {
        $title = $form_name;
    }
    
    $token = md5(uniqid(rand(), true));
    $created_at = current_time('mysql');
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $link = home_url('/form/' . $token . '/');
    
    // Обрабатываем загрузку логотипа
    $logo_path = '';
    if ($form_logo && isset($form_logo['tmp_name']) && $form_logo['error'] == 0) {
        $upload_dir = wp_upload_dir();
        $logos_dir = $upload_dir['basedir'] . '/my-forms-logos';
        
        // Создаем директорию если не существует
        if (!file_exists($logos_dir)) {
            wp_mkdir_p($logos_dir);
        }
        
        // Генерируем уникальное имя файла
        $file_extension = pathinfo($form_logo['name'], PATHINFO_EXTENSION);
        $file_name = 'logo_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
        $file_path = $logos_dir . '/' . $file_name;
        
        // Проверяем тип файла
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($form_logo['tmp_name'], $file_path)) {
                $logo_path = $file_name;
            }
        }
    }
    
    $form_data = array(
        'user_id' => $user_id,
        'title' => $title,
        'token' => $token,
        'link' => $link,
        'user_agent' => '',
        'template' => $form_template,
        'logo_path' => $logo_path,
        'created_at' => $created_at,
        'expires_at' => $expires_at,
        'status' => 'active',
        'comment' => ''
    );
    
    $table = $wpdb->prefix . 'my_forms';
    $result = $wpdb->insert($table, $form_data);
    
    if ($result) {
        return array(
            'id' => $wpdb->insert_id,
            'link' => $link
        );
    }
    
    return false;
}

// Функция для обновления названия формы
function my_forms_update_form_title($form_id, $title, $user_id) {
    global $wpdb;
    
    $result = $wpdb->update(
        $wpdb->prefix . 'my_forms',
        array('title' => $title),
        array('id' => $form_id, 'user_id' => $user_id)
    );
    
    return $result !== false;
}

// Функция для истечения срока форм
function expire_forms() {
    global $wpdb;
    $table = $wpdb->prefix . 'my_forms';
    $now = current_time('mysql');
    
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table SET status = 'expired' WHERE expires_at <= %s AND status = 'active'",
            $now
        )
    );
}

// Добавление шаблона для страницы формы
add_filter('template_include', 'my_forms_template', 99);
function my_forms_template($template) {
    $token = get_query_var('my_form_token');
    
    if ($token) {
        // Проверка валидности токена
        global $wpdb;
        $table = $wpdb->prefix . 'my_forms';
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE token = %s AND status = 'active'",
                $token
            )
        );
        
        if ($form) {
            // Определяем шаблон в зависимости от выбранного типа
            $template_name = 'form-page.php';
            if ($form->template === 'modern') {
                $template_name = 'form-page-modern.php';
            }
            
            $form_template = plugin_dir_path(__FILE__) . 'templates/' . $template_name;
            
            // Проверяем, существует ли файл шаблона
            if (file_exists($form_template)) {
                return $form_template;
            } else {
                error_log('Шаблон формы не найден: ' . $form_template);
            }
        } else {
            error_log('Форма не найдена или не активна для токена: ' . $token);
        }
    }
    
    return $template;
}

// AJAX для сохранения данных формы
add_action('wp_ajax_submit_my_form', 'handle_my_form_submit');
add_action('wp_ajax_nopriv_submit_my_form', 'handle_my_form_submit');
function handle_my_form_submit() {
    // Проверка nonce
    if (!isset($_POST['my_form_nonce']) || 
        !wp_verify_nonce($_POST['my_form_nonce'], 'submit_my_form')) {
        wp_send_json_error('Ошибка безопасности');
        exit;
    }
    
    $token = sanitize_text_field($_POST['token']);
    
    // Получение формы по токену
    global $wpdb;
    $forms_table = $wpdb->prefix . 'my_forms';
    $form = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $forms_table WHERE token = %s AND status = 'active'",
            $token
        )
    );
    
    if (!$form) {
        wp_send_json_error('Форма не найдена или срок ее действия истек');
        exit;
    }
    
    // Проверка User-Agent
    if (!empty($form->user_agent) && $form->user_agent !== $_SERVER['HTTP_USER_AGENT']) {
        wp_send_json_error('Доступ с другого устройства запрещен');
        exit;
    }
    
    // Сохранение данных запроса
    $full_name = sanitize_text_field($_POST['full_name']);
    
    // Безопасная обработка meta_data
    $meta_data = array();
    if (isset($_POST['meta_data']) && is_array($_POST['meta_data'])) {
        foreach ($_POST['meta_data'] as $key => $value) {
            $meta_data[sanitize_text_field($key)] = sanitize_text_field($value);
        }
    }
    
    $source = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
    
    $requests_table = $wpdb->prefix . 'my_forms_requests';
    $result = $wpdb->insert(
        $requests_table,
        array(
            'form_id' => $form->id,
            'full_name' => $full_name,
            'meta_data' => json_encode($meta_data),
            'source' => $source,
            'created_at' => current_time('mysql'),
            'status' => 'new', // Новая заявка будет иметь статус "new"
            'comment' => ''
        )
    );
    
    if ($result) {
        // Успешное сохранение заявки
        $response = array(
            'success' => true,
            'message' => 'Заявка успешно отправлена',
            'redirect_url' => add_query_arg('submitted', '1', $form->link) // URL для перенаправления
        );
        wp_send_json($response);
    } else {
        wp_send_json_error('Ошибка при сохранении данных');
    }
    exit;
}

// AJAX для обновления названия формы
add_action('wp_ajax_update_form_title', 'handle_update_form_title');
function handle_update_form_title() {
    // Проверка nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_form_title_nonce')) {
        wp_send_json_error('Ошибка безопасности');
        exit;
    }
    
    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $user_id = get_current_user_id();
    
    if (empty($title) || $form_id <= 0) {
        wp_send_json_error('Неверные данные');
        exit;
    }
    
    $result = my_forms_update_form_title($form_id, $title, $user_id);
    
    if ($result) {
        wp_send_json_success('Название формы обновлено');
    } else {
        wp_send_json_error('Ошибка при обновлении названия формы');
    }
    exit;
}

// AJAX для удаления формы
add_action('wp_ajax_delete_form', 'handle_delete_form');
function handle_delete_form() {
    // Проверка nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'my_forms_ajax_nonce')) {
        wp_send_json_error('Ошибка безопасности');
        exit;
    }
    
    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $user_id = get_current_user_id();
    
    if (!$form_id) {
        wp_send_json_error('Неверный ID формы');
        exit;
    }
    
    global $wpdb;
    
    // Удаляем форму
    $result = $wpdb->delete(
        $wpdb->prefix . 'my_forms',
        array('id' => $form_id, 'user_id' => $user_id),
        array('%d', '%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Форма успешно удалена');
    } else {
        wp_send_json_error('Ошибка при удалении формы: ' . $wpdb->last_error);
    }
    exit;
}

// Добавьте в my-forms.php
function my_forms_update_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Добавляем поле comment к таблице my_forms
    $wpdb->query("ALTER TABLE {$wpdb->prefix}my_forms ADD COLUMN comment TEXT DEFAULT ''");
    
    // Добавляем поля comment и status к таблице my_forms_requests
    $wpdb->query("ALTER TABLE {$wpdb->prefix}my_forms_requests ADD COLUMN comment TEXT DEFAULT ''");
    $wpdb->query("ALTER TABLE {$wpdb->prefix}my_forms_requests ADD COLUMN status ENUM('new', 'processing', 'completed', 'rejected') DEFAULT 'new'");
    
    // Добавляем новые поля для шаблонов и логотипов
    $wpdb->query("ALTER TABLE {$wpdb->prefix}my_forms ADD COLUMN template VARCHAR(50) DEFAULT 'default'");
    $wpdb->query("ALTER TABLE {$wpdb->prefix}my_forms ADD COLUMN logo_path VARCHAR(255) DEFAULT ''");
}

// Регистрируем функцию обновления при активации
register_activation_hook(__FILE__, 'my_forms_update_database');

// Также можно вызвать эту функцию при первичной проверке
add_action('admin_init', function() {
    // Проверяем, нужно ли обновить базу данных
    if (get_option('my_forms_db_version') != '1.2') {
        my_forms_update_database();
        update_option('my_forms_db_version', '1.2');
    }
});


// В файле my-forms.php
add_action('wp_ajax_update_request_status', 'handle_update_request_status');
function handle_update_request_status() {
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $user_id = get_current_user_id();
    
    if (!$request_id || !in_array($status, array('new', 'processing', 'completed', 'rejected'))) {
        wp_send_json_error('Неверные данные');
        exit;
    }
    
    global $wpdb;
    
    // Проверяем, принадлежит ли заявка пользователю
    $request_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            WHERE r.id = %d AND f.user_id = %d",
            $request_id, $user_id
        )
    );
    
    if (!$request_exists) {
        wp_send_json_error('Заявка не найдена или у вас нет прав на её редактирование');
        exit;
    }
    
    // Обновляем статус
    $result = $wpdb->update(
        $wpdb->prefix . 'my_forms_requests',
        array('status' => $status),
        array('id' => $request_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Статус заявки обновлен');
    } else {
        wp_send_json_error('Ошибка при обновлении статуса: ' . $wpdb->last_error);
    }
    exit;
}