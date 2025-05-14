<?php
/**
 * Файл для страницы дашборда
 */

if (!defined('ABSPATH')) exit;

// Проверка таблиц только после полной загрузки WordPress
add_action('admin_init', 'my_forms_check_tables');
function my_forms_check_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_forms';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Таблица не существует, вызываем функцию активации
        my_forms_activate();
        
        // Используем хуки для вывода сообщений в админке
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Таблицы плагина были созданы.</p></div>';
        });
    }
}

function my_forms_dashboard_page() {
    global $wpdb;
    $user_id = get_current_user_id();

    // Получаем статистику
    $stats = my_forms_get_stats($user_id);
    
    // Получаем последние заявки
    $recent_requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.*, f.title as form_title 
            FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            WHERE f.user_id = %d
            ORDER BY r.created_at DESC
            LIMIT 5",
            $user_id
        )
    );
    
    // Получаем активные формы
    $active_forms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}my_forms 
            WHERE user_id = %d 
            AND status = 'active'
            ORDER BY created_at DESC 
            LIMIT 5",
            $user_id
        )
    );
    
    // Подключаем шаблон
    include plugin_dir_path(__FILE__) . '../templates/dashboard.php';
}

function my_forms_get_stats($user_id) {
    global $wpdb;
    
    // Общее количество форм
    $total_forms = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}my_forms 
            WHERE user_id = %d",
            $user_id
        )
    );
    
    // Количество активных форм
    $active_forms = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}my_forms 
            WHERE user_id = %d AND status = 'active'",
            $user_id
        )
    );
    
    // Количество истекших форм
    $expired_forms = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}my_forms 
            WHERE user_id = %d AND status = 'expired'",
            $user_id
        )
    );
    
    // Общее количество заявок
    $total_requests = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(r.id) 
            FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            WHERE f.user_id = %d",
            $user_id
        )
    );
    
    // Заявки за последние 24 часа
    $recent_requests = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(r.id) 
            FROM {$wpdb->prefix}my_forms_requests r
            JOIN {$wpdb->prefix}my_forms f ON r.form_id = f.id
            WHERE f.user_id = %d
            AND r.created_at >= %s",
            $user_id,
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        )
    );
    
    return array(
        'total_forms' => $total_forms,
        'active_forms' => $active_forms,
        'expired_forms' => $expired_forms,
        'total_requests' => $total_requests,
        'recent_requests' => $recent_requests
    );
}