<?php
/**
 * Модуль безопасности для плагина My Forms
 */

if (!defined('ABSPATH')) exit;

/**
 * Проверка доступа к форме
 */
add_action('template_redirect', 'my_forms_check_access');
function my_forms_check_access() {
    // Проверяем, находимся ли мы на странице формы
    if (strpos($_SERVER['REQUEST_URI'], '/form/') === false) return;

    global $wpdb;
    $token = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    
    // Очищаем токен от возможных символов
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
    
    // Проверяем существование формы
    $form = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}my_forms 
            WHERE token = %s",
            $token
        )
    );

    if (!$form) {
        wp_die('Форма не найдена.', 'Ошибка доступа', array('response' => 404));
    }

    // Проверяем статус формы
    if ($form->status === 'expired') {
        wp_die('Срок действия формы истек.', 'Ошибка доступа', array('response' => 403));
    }

    // Получаем текущий User-Agent
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Проверяем, является ли User-Agent ботом или мессенджером
    $is_bot = false;
    $bot_signatures = array('bot', 'Bot', 'crawler', 'spider', 'Telegram', 'facebook', 'WhatsApp', 'viber', 'messenger');
    
    foreach ($bot_signatures as $signature) {
        if (strpos($current_user_agent, $signature) !== false) {
            $is_bot = true;
            break;
        }
    }
    
    // Если это бот - пропускаем все проверки и даем доступ
    if ($is_bot) {
        return;
    }
    
    // Если user_agent не установлен, устанавливаем его
    if (empty($form->user_agent)) {
        $wpdb->update(
            $wpdb->prefix . 'my_forms',
            array('user_agent' => $current_user_agent),
            array('id' => $form->id)
        );
    } 
    // Если user_agent установлен и не соответствует текущему - блокируем доступ
    elseif ($form->user_agent !== $current_user_agent) {
        // Редирект на страницу с сообщением об ошибке
        include(plugin_dir_path(dirname(__FILE__)) . 'templates/access-denied.php');
        exit;
    }
}

/**
 * Дополнительная защита от CSRF-атак
 */
add_action('init', 'my_forms_security_headers');
function my_forms_security_headers() {
    // Если это страница формы, устанавливаем дополнительные заголовки безопасности
    if (strpos($_SERVER['REQUEST_URI'], '/form/') !== false) {
        // Защита от кликджекинга
        header('X-Frame-Options: SAMEORIGIN');
        
        // Защита от XSS
        header('X-XSS-Protection: 1; mode=block');
        
        // Контроль типа контента
        header('X-Content-Type-Options: nosniff');
        
        // Политика безопасности контента (CSP)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://ajax.googleapis.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
    }
}

/**
 * Защита от брутфорс атак на формы
 */
add_action('init', 'my_forms_brute_force_protection');
function my_forms_brute_force_protection() {
    // Проверяем только при отправке формы
    if (!isset($_POST['action']) || $_POST['action'] !== 'submit_my_form') {
        return;
    }
    
    // Получаем IP-адрес пользователя
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Создаем ключ для хранения попыток
    $key = 'my_forms_attempts_' . md5($ip);
    
    // Получаем текущее количество попыток
    $attempts = get_transient($key);
    
    // Если за последние 15 минут было более 10 попыток, блокируем
    if ($attempts && $attempts > 10) {
        wp_send_json_error('Слишком много запросов. Пожалуйста, попробуйте позже.');
        exit;
    }
    
    // Увеличиваем счетчик попыток
    if (!$attempts) {
        set_transient($key, 1, 15 * MINUTE_IN_SECONDS);
    } else {
        set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
    }
}

/**
 * Валидация и фильтрация входных данных
 */
function my_forms_validate_input($data) {
    $clean_data = array();
    
    // Проверка обязательных полей
    if (empty($data['token']) || empty($data['full_name'])) {
        return false;
    }
    
    // Очистка и валидация токена
    $clean_data['token'] = preg_replace('/[^a-zA-Z0-9]/', '', $data['token']);
    
    // Очистка и валидация ФИО
    $clean_data['full_name'] = sanitize_text_field($data['full_name']);
    
    // Очистка и валидация meta_data
    if (isset($data['meta_data']) && is_array($data['meta_data'])) {
        $clean_data['meta_data'] = array();
        
        foreach ($data['meta_data'] as $key => $value) {
            // Проверяем email
            if ($key === 'email' && !empty($value)) {
                $value = sanitize_email($value);
                if (!is_email($value)) {
                    return false; // Неверный формат email
                }
            } 
            // Проверяем телефон
            elseif ($key === 'phone' && !empty($value)) {
                // Удаляем все кроме цифр, скобок, плюса и дефиса
                $value = preg_replace('/[^0-9\(\)\+\-]/', '', $value);
            }
            
            $clean_data['meta_data'][$key] = sanitize_text_field($value);
        }
    } else {
        $clean_data['meta_data'] = array();
    }
    
    return $clean_data;
}

/**
 * Проверка спам-защиты (honeypot)
 */
function my_forms_check_honeypot($data) {
    // Если поле-ловушка заполнено, это бот
    if (isset($data['website']) && !empty($data['website'])) {
        return true; // Это спам
    }
    
    return false; // Не спам
}