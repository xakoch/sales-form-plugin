<?php
// Получаем токен из URL
$token = get_query_var('my_form_token');

// Получаем данные формы
global $wpdb;
$table = $wpdb->prefix . 'my_forms';
$form = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE token = %s AND status = 'active'",
        $token
    )
);

// Если форма не найдена, редирект на главную
if (!$form) {
    wp_redirect(home_url());
    exit;
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
wp_register_script('my-forms-countdown', '', array(), '', true);
wp_localize_script('my-forms-countdown', 'myFormsData', array(
    'expiresAt' => date('Y-m-d H:i:s', $expires),
    'serverTime' => date('Y-m-d H:i:s', $now)
));
wp_enqueue_script('my-forms-countdown');
wp_enqueue_script('jquery');

// Подключаем WordPress header
get_header();
?>

<div class="my-forms-page">
    <div class="my-forms-container">
        <div class="form-header">
            <?php if ($logo_url) : ?>
                <div class="form-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
                </div>
            <?php endif; ?>
            <h1><?php echo esc_html($form->title); ?></h1>
            
            <div class="expiration-info">
                <span class="dashicons dashicons-clock"></span>
                Форма будет доступна: <strong id="countdown"><?php echo $hours_left; ?> часов</strong>
            </div>
        </div>
        
        <div class="form-content">
            <form id="my-form" method="post">
                <div class="form-field">
                    <label for="full_name">ФИО <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                
                <div class="form-field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-field">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-field">
                    <label for="message">Сообщение</label>
                    <textarea id="message" name="message" rows="5"></textarea>
                </div>
                
                <!-- Скрытое поле для защиты от спама -->
                <div class="honeypot-field" style="display:none;">
                    <label for="website">Оставьте это поле пустым</label>
                    <input type="text" id="website" name="website">
                </div>
                
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                
                <div class="form-actions">
                    <button type="submit" id="submit-button">Отправить заявку</button>
                </div>
            </form>
            
            <div id="form-message" class="form-message" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обновление счетчика времени
    function updateCountdown() {
        // Получаем время истечения формы и текущее время сервера
        var expiresAt = new Date(myFormsData.expiresAt);
        var serverTime = new Date(myFormsData.serverTime);
        var now = new Date();
        
        // Корректируем на разницу между временем клиента и сервера
        var timeDiff = now - serverTime;
        var correctedNow = new Date(now.getTime() - timeDiff);
        
        // Вычисляем оставшееся время
        var timeLeft = expiresAt - correctedNow;
        
        if (timeLeft <= 0) {
            // Форма истекла, показываем сообщение и блокируем форму
            document.getElementById("countdown").textContent = "0 часов";
            document.getElementById("form-message").innerHTML = "<p>Срок действия формы истек.</p>";
            document.getElementById("form-message").classList.add("error");
            document.getElementById("form-message").style.display = "block";
            
            var formElements = document.querySelectorAll("#my-form input, #my-form textarea, #my-form button");
            formElements.forEach(function(el) {
                el.disabled = true;
            });
            return;
        }
        
        // Переводим миллисекунды в часы, минуты, секунды
        var hours = Math.floor(timeLeft / (1000 * 60 * 60));
        var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        
        // Обновляем текст на странице
        if (hours > 0) {
            document.getElementById("countdown").textContent = hours + " ч";
        } else {
            document.getElementById("countdown").textContent = minutes + " мин";
        }
        
        // Обновляем каждую минуту
        setTimeout(updateCountdown, 60000);
    }
    
    // Запускаем обновление счетчика
    updateCountdown();
    
    // Обработка отправки формы через AJAX
    document.getElementById("my-form").addEventListener("submit", function(e) {
        e.preventDefault();
        
        // Блокируем кнопку отправки и показываем индикатор загрузки
        var submitButton = document.getElementById("submit-button");
        submitButton.disabled = true;
        submitButton.textContent = "Отправка...";
        
        // Собираем данные формы
        var fullName = document.getElementById("full_name").value;
        var email = document.getElementById("email").value;
        var phone = document.getElementById("phone").value;
        var message = document.getElementById("message").value;
        var token = document.querySelector("input[name='token']").value;
        var website = document.getElementById("website").value;
        
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
        formData.append('website', website);
        
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
            if (data.success) {
                // Скрываем всю форму
                document.querySelector(".form-header").style.display = "none";
                document.querySelector(".form-content").style.display = "none";
                
                // Добавляем блок благодарности
                var thankYouHtml = `
                    <div class="thank-you-header">
                        <span class="success-icon dashicons dashicons-yes-alt"></span>
                        <h1>Заявка успешно отправлена!</h1>
                    </div>
                    
                    <div class="thank-you-content">
                        <p>Благодарим вас за заполнение формы. Ваша заявка была успешно получена и будет обработана в ближайшее время.</p>
                        
                        <p>При необходимости с вами свяжутся по указанным контактным данным.</p>
                        
                        <div class="actions">
                            <a href="${window.location.origin}" class="back-to-home">Вернуться на главную</a>
                        </div>
                    </div>
                `;
                
                // Добавляем блок благодарности в контейнер
                document.querySelector(".my-forms-container").innerHTML = thankYouHtml;
                
                // Прокручиваем страницу вверх
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            } else {
                // Показываем сообщение об ошибке
                var messageEl = document.getElementById("form-message");
                messageEl.innerHTML = "<p>Ошибка: " + data.data + "</p>";
                messageEl.classList.remove("success");
                messageEl.classList.add("error");
                messageEl.style.display = "block";
                
                // Разблокируем кнопку отправки
                submitButton.disabled = false;
                submitButton.textContent = "Отправить заявку";
            }
        })
        .catch(error => {
            // Показываем сообщение о технической ошибке
            var messageEl = document.getElementById("form-message");
            messageEl.innerHTML = "<p>Произошла техническая ошибка. Пожалуйста, попробуйте позже.</p>";
            messageEl.classList.remove("success");
            messageEl.classList.add("error");
            messageEl.style.display = "block";
            
            // Разблокируем кнопку отправки
            submitButton.disabled = false;
            submitButton.textContent = "Отправить заявку";
        });
    });
});
</script>

<style>
.my-forms-page {
    width: 100%;
    max-width: 100%;
    min-height: 100vh;
    background-color: #f8f9fa;
    padding: 40px 0;
}

.my-forms-container {
    width: 90%;
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.form-header {
    background: #2271b1;
    color: #fff;
    padding: 30px;
    text-align: center;
}

.form-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 10px;
    display: inline-block;
}

.form-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 4px;
}

.form-header h1 {
    margin: 0 0 15px;
    font-size: 28px;
    color: #fff;
}

.expiration-info {
    display: inline-flex;
    align-items: center;
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
}

.expiration-info .dashicons {
    margin-right: 5px;
}

.form-content {
    padding: 30px;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #555;
}

.form-field .required {
    color: #dc3232;
}

.form-field input,
.form-field textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.form-field input:focus,
.form-field textarea:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

.form-actions {
    margin-top: 30px;
    text-align: center;
}

.form-actions button {
    background: #2271b1;
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: background 0.2s;
}

.form-actions button:hover {
    background: #135e96;
}

.form-actions button:disabled {
    background: #999;
    cursor: not-allowed;
}

.form-message {
    margin-top: 20px;
    padding: 15px;
    border-radius: 4px;
    text-align: center;
}

.form-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.form-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.thank-you-header {
    background: #46b450;
    color: #fff;
    padding: 30px;
    text-align: center;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.thank-you-header h1 {
    margin: 0 0 15px;
    font-size: 28px;
    color: #fff;
}

.success-icon {
    font-size: 60px;
    width: 60px;
    height: 60px;
    display: block;
    margin: 0 auto 20px;
}

.thank-you-content {
    padding: 30px;
    text-align: center;
}

.thank-you-content p {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.actions {
    margin-top: 30px;
}

.back-to-home {
    display: inline-block;
    background: #2271b1;
    color: #fff;
    padding: 12px 25px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.2s;
}

.back-to-home:hover {
    background: #135e96;
    color: #fff;
}

@media (max-width: 768px) {
    .my-forms-page {
        padding: 20px 0;
    }
    
    .form-header h1 {
        font-size: 24px;
    }
    
    .form-field input,
    .form-field textarea {
        padding: 10px;
        font-size: 14px;
    }
    
    .form-actions button {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>

<?php
// Подключаем WordPress footer
get_footer();
?>