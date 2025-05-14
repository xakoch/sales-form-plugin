<?php

// Проверяем, доступна ли форма
global $wpdb;
$token = isset($atts['token']) ? $atts['token'] : '';

if (empty($token)) {
    return '<p>Ошибка: Токен формы не указан</p>';
}

$table = $wpdb->prefix . 'my_forms';
$form = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE token = %s AND status = 'active'",
        $token
    )
);

if (!$form) {
    return '<p>Ошибка: Форма не найдена или срок ее действия истек</p>';
}

// Вычисляем оставшееся время жизни формы
$now = current_time('timestamp');
$expires = strtotime($form->expires_at);
$hours_left = max(0, round(($expires - $now) / (60 * 60), 1));

// Получаем путь к логотипу
$logo_url = '';
if (!empty($form->logo_path)) {
    $upload_dir = wp_upload_dir();
    $logo_url = $upload_dir['baseurl'] . '/my-forms-logos/' . $form->logo_path;
}

// Локализация временного JavaScript
$random_id = 'my-form-' . rand(1000, 9999);

?>

<div class="my-forms-container <?php echo $form->template === 'modern' ? 'modern-template' : 'default-template'; ?>" style="max-width: 100%; margin: 20px 0; padding: 20px; background: #f8f8f8; border-radius: 5px;">
    <?php if ($logo_url) : ?>
        <div class="form-logo-container" style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 100px; height: auto;">
        </div>
    <?php endif; ?>
    
    <h3 style="margin-bottom: 20px; text-align: center;"><?php echo esc_html($form->title); ?></h3>
    
    <div class="expiration-notice" style="margin-bottom: 20px; padding: 10px; background: #fff; border-left: 4px solid #2271b1; border-radius: 4px;">
        <p>Форма будет доступна для заполнения в течение <strong class="countdown"><?php echo $hours_left; ?> часов</strong></p>
    </div>
    
    <form id="<?php echo $random_id; ?>" class="my-form-shortcode" method="post">
        <div class="form-field" style="margin-bottom: 15px;">
            <label for="<?php echo $random_id; ?>-full_name" style="display: block; margin-bottom: 5px; font-weight: bold;">ФИО:</label>
            <input type="text" id="<?php echo $random_id; ?>-full_name" name="full_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div class="form-field" style="margin-bottom: 15px;">
            <label for="<?php echo $random_id; ?>-email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email:</label>
            <input type="email" id="<?php echo $random_id; ?>-email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div class="form-field" style="margin-bottom: 15px;">
            <label for="<?php echo $random_id; ?>-phone" style="display: block; margin-bottom: 5px; font-weight: bold;">Телефон:</label>
            <input type="tel" id="<?php echo $random_id; ?>-phone" name="phone" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div class="form-field" style="margin-bottom: 15px;">
            <label for="<?php echo $random_id; ?>-message" style="display: block; margin-bottom: 5px; font-weight: bold;">Сообщение:</label>
            <textarea id="<?php echo $random_id; ?>-message" name="message" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
        </div>
        
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
        <div class="form-submit" style="text-align: center;">
            <button type="submit" class="submit-btn" style="background: <?php echo $form->template === 'modern' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#2271b1'; ?>; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Отправить заявку</button>
        </div>
        
        <div id="<?php echo $random_id; ?>-message" class="form-message" style="margin-top: 15px; padding: 10px; display: none;"></div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка отправки формы через AJAX
    document.getElementById('<?php echo $random_id; ?>').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var messageEl = document.getElementById('<?php echo $random_id; ?>-message');
        
        // Собираем данные формы
        var fullName = document.getElementById('<?php echo $random_id; ?>-full_name').value;
        var email = document.getElementById('<?php echo $random_id; ?>-email').value;
        var phone = document.getElementById('<?php echo $random_id; ?>-phone').value;
        var message = document.getElementById('<?php echo $random_id; ?>-message').value;
        var token = form.querySelector('input[name="token"]').value;
        
        // Дополнительные данные
        var metaData = {
            'email': email,
            'phone': phone,
            'message': message
        };
        
        // Формируем данные для отправки
        var formData = new FormData();
        formData.append('action', 'submit_my_form');
        formData.append('my_form_nonce', '<?php echo wp_create_nonce('submit_my_form'); ?>');
        formData.append('token', token);
        formData.append('full_name', fullName);
        
        // Добавляем meta_data
        for (var key in metaData) {
            formData.append('meta_data[' + key + ']', metaData[key]);
        }
        
        // Отправка данных на сервер
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            messageEl.style.display = 'block';
            if (data.success) {
                // Очищаем форму
                form.reset();
                
                // Показываем сообщение об успехе
                messageEl.innerHTML = '<p>Спасибо! Ваша заявка успешно отправлена.</p>';
                messageEl.style.background = '#d4edda';
                messageEl.style.color = '#155724';
                messageEl.style.border = '1px solid #c3e6cb';
                messageEl.style.borderRadius = '4px';
            } else {
                // Показываем сообщение об ошибке
                messageEl.innerHTML = '<p>Ошибка: ' + data.data + '</p>';
                messageEl.style.background = '#f8d7da';
                messageEl.style.color = '#721c24';
                messageEl.style.border = '1px solid #f5c6cb';
                messageEl.style.borderRadius = '4px';
            }
        })
        .catch(error => {
            // Показываем сообщение о технической ошибке
            messageEl.style.display = 'block';
            messageEl.innerHTML = '<p>Произошла техническая ошибка. Пожалуйста, попробуйте позже.</p>';
            messageEl.style.background = '#f8d7da';
            messageEl.style.color = '#721c24';
            messageEl.style.border = '1px solid #f5c6cb';
            messageEl.style.borderRadius = '4px';
        });
    });
});
</script>

<style>
.my-forms-container.modern-template {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.my-forms-container.modern-template .form-field input,
.my-forms-container.modern-template .form-field textarea {
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.my-forms-container.modern-template .form-field label {
    color: white;
}

.my-forms-container.modern-template .expiration-notice {
    background: rgba(255, 255, 255, 0.1);
    border-left: 4px solid rgba(255, 255, 255, 0.5);
    color: white;
}

.my-forms-container.modern-template h3 {
    color: white;
}

.my-forms-container.modern-template .submit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
</style>