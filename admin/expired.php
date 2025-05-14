<?php
/**
 * Файл для страницы истекших форм
 */

if (!defined('ABSPATH')) exit;

function my_forms_expired_page() {
    global $wpdb;
    $user_id = get_current_user_id();

    // Обработка очистки истекших форм
    if (isset($_POST['clear_expired']) && isset($_POST['_wpnonce'])) {
        // Проверка nonce
        if (wp_verify_nonce($_POST['_wpnonce'], 'clear_expired_forms_nonce')) {
            // Удаление истекших форм
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}my_forms 
                    WHERE user_id = %d AND status = 'expired'",
                    $user_id
                )
            );
            
            if ($result !== false) {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-success"><p>Удалено истекших форм: ' . intval($result) . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() use ($wpdb) {
                    echo '<div class="notice notice-error"><p>Ошибка при удалении форм: ' . esc_html($wpdb->last_error) . '</p></div>';
                });
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Ошибка проверки безопасности.</p></div>';
            });
        }
    }

    // Получение списка истекших форм
    $forms = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT f.*, 
            (SELECT COUNT(*) FROM {$wpdb->prefix}my_forms_requests r WHERE r.form_id = f.id) as requests_count
            FROM {$wpdb->prefix}my_forms f
            WHERE f.user_id = %d 
            AND f.status = 'expired'
            ORDER BY f.expires_at DESC",
            $user_id
        )
    );
    
    // Инициализация UI компонентов
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');
    ?>
    
    <div class="wrap my-forms-admin-page">
        <h1 class="wp-heading-inline">Истекшие формы</h1>
        
        <!-- Хлебные крошки -->
        <div class="my-forms-breadcrumbs">
            <a href="<?php echo admin_url('admin.php?page=my-forms-dashboard'); ?>">Дашборд</a>
            <span class="separator"> &gt; </span>
            <span class="current">Истекшие формы</span>
        </div>
        
        <div class="my-forms-section">
            <?php if (empty($forms)) : ?>
                <div class="my-forms-empty-state">
                    <p>У вас нет истекших форм.</p>
                    <a href="<?php echo admin_url('admin.php?page=my-forms'); ?>" class="button button-primary">Создать новую форму</a>
                </div>
            <?php else : ?>
                <!-- Кнопка очистки истекших форм -->
                <div class="my-forms-actions">
                    <button id="clear-expired-btn" class="button">Удалить все истекшие формы</button>
                </div>
                
                <!-- Модальное окно для подтверждения удаления -->
                <div id="confirm-delete-modal" title="Подтверждение удаления" style="display:none;">
                    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span>Вы уверены, что хотите удалить все истекшие формы? Это действие нельзя отменить.</p>
                    
                    <form method="post" id="clear-expired-form">
                        <?php wp_nonce_field('clear_expired_forms_nonce'); ?>
                        <input type="hidden" name="clear_expired" value="1">
                    </form>
                </div>
                
                <!-- Таблица истекших форм -->
                <div class="my-forms-cards expired-forms">
                    <?php foreach ($forms as $form) : 
                        // Получаем путь к логотипу
                        $logo_url = '';
                        if (!empty($form->logo_path)) {
                            $upload_dir = wp_upload_dir();
                            $logo_url = $upload_dir['baseurl'] . '/my-forms-logos/' . $form->logo_path;
                        }
                    ?>
                        <div class="my-form-card expired">
                            <div class="form-header">
                                <?php if ($logo_url) : ?>
                                    <div class="form-logo">
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                                    </div>
                                <?php endif; ?>
                                <h3 class="form-title">
                                    <?php echo esc_html($form->title); ?>
                                </h3>
                            </div>
                            <div class="form-details">
                                <div class="form-detail">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <span>Создана: <?php echo date_i18n('d.m.Y H:i', strtotime($form->created_at)); ?></span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-clock"></span>
                                    <span>Истекла: <?php echo date_i18n('d.m.Y H:i', strtotime($form->expires_at)); ?></span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <span>
                                        <?php if ($form->requests_count > 0) : ?>
                                            <a href="<?php echo admin_url('admin.php?page=my-forms-requests&form_id=' . $form->id); ?>">
                                                Заявок: <?php echo $form->requests_count; ?>
                                            </a>
                                        <?php else : ?>
                                            Заявок: 0
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="form-detail">
                                    <span class="dashicons dashicons-admin-appearance"></span>
                                    <span>Шаблон: <?php echo $form->template === 'modern' ? 'Современный' : 'Стандартный'; ?></span>
                                </div>
                            </div>
                            <div class="form-actions">
                                <form method="post" class="delete-single-form">
                                    <?php wp_nonce_field('delete_single_form_nonce_' . $form->id); ?>
                                    <input type="hidden" name="delete_form_id" value="<?php echo $form->id; ?>">
                                    <button type="button" class="button delete-form" data-form-id="<?php echo $form->id; ?>">Удалить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Инициализация модального окна для подтверждения удаления
        if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
            jQuery("#confirm-delete-modal").dialog({
                autoOpen: false,
                modal: true,
                width: 400,
                buttons: {
                    "Удалить": function() {
                        document.getElementById("clear-expired-form").submit();
                    },
                    "Отмена": function() {
                        jQuery(this).dialog("close");
                    }
                }
            });
        }
        
        // Открытие модального окна для подтверждения удаления
        document.getElementById("clear-expired-btn")?.addEventListener("click", function() {
            if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                jQuery("#confirm-delete-modal").dialog("open");
            }
        });
        
        // Удаление отдельной формы
        document.addEventListener('click', function(e) {
            if (e.target.closest(".delete-form")) {
                e.preventDefault();
                var deleteButton = e.target.closest(".delete-form");
                var formId = deleteButton.getAttribute("data-form-id");
                var formCard = deleteButton.closest(".my-form-card");
                var formTitle = formCard.querySelector(".form-title").textContent.trim();
                
                if (confirm("Вы уверены, что хотите удалить форму \"" + formTitle + "\"?")) {
                    var form = deleteButton.closest("form");
                    form.submit();
                }
            }
        });
    });
    </script>
    
    <style>
    .my-forms-actions {
        margin-bottom: 20px;
    }
    
    .expired-forms .my-form-card {
        background-color: #f9f9f9;
        border-left: 4px solid #999;
    }
    
    .form-actions {
        padding: 15px;
        border-top: 1px solid #eee;
        text-align: right;
    }
    </style>
    <?php
}

// Обработка удаления одной формы
add_action('admin_init', 'handle_single_form_delete');
function handle_single_form_delete() {
    if (isset($_POST['delete_form_id'])) {
        $form_id = intval($_POST['delete_form_id']);
        $user_id = get_current_user_id();
        
        // Проверяем nonce
        $nonce_name = 'delete_single_form_nonce_' . $form_id;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], $nonce_name)) {
            global $wpdb;
            
            // Удаляем форму
            $result = $wpdb->delete(
                $wpdb->prefix . 'my_forms',
                array('id' => $form_id, 'user_id' => $user_id),
                array('%d', '%d')
            );
            
            if ($result !== false) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>Форма успешно удалена.</p></div>';
                });
            } else {
                add_action('admin_notices', function() use ($wpdb) {
                    echo '<div class="notice notice-error"><p>Ошибка при удалении формы: ' . esc_html($wpdb->last_error) . '</p></div>';
                });
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Ошибка проверки безопасности.</p></div>';
            });
        }
    }
}