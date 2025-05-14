<?php
// Подключаем WordPress header
get_header();
?>

<div class="my-forms-page">
    <div class="my-forms-container">
        <div class="access-denied-header">
            <div class="error-icon">
                <span class="dashicons dashicons-shield-alt"></span>
            </div>
            <h1>Доступ запрещен</h1>
        </div>
        
        <div class="access-denied-content">
            <div class="error-message">
                <p>Извините, но доступ к этой форме ограничен.</p>
                <p>Форма может быть заполнена только с того устройства, с которого был осуществлен первый доступ.</p>
                <p>Для обеспечения безопасности и конфиденциальности данных, доступ с другого устройства запрещен.</p>
            </div>
            
            <div class="actions">
                <a href="<?php echo home_url(); ?>" class="back-button">Вернуться на главную</a>
            </div>
        </div>
    </div>
</div>

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

.access-denied-header {
    background: #dc3232;
    color: #fff;
    padding: 30px;
    text-align: center;
}

.error-icon {
    margin-bottom: 20px;
}

.error-icon .dashicons {
    font-size: 60px;
    width: 60px;
    height: 60px;
}

.access-denied-header h1 {
    margin: 0;
    font-size: 28px;
    color: #fff;
}

.access-denied-content {
    padding: 30px;
}

.error-message {
    text-align: center;
    margin-bottom: 30px;
}

.error-message p {
    margin-bottom: 15px;
    font-size: 16px;
    color: #555;
}

.actions {
    text-align: center;
}

.back-button {
    display: inline-block;
    background: #2271b1;
    color: #fff;
    padding: 12px 25px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.2s;
}

.back-button:hover {
    background: #135e96;
    color: #fff;
    text-decoration: none;
}

@media (max-width: 768px) {
    .my-forms-page {
        padding: 20px 0;
    }
    
    .access-denied-header h1 {
        font-size: 24px;
    }
    
    .error-message p {
        font-size: 14px;
    }
    
    .back-button {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>

<?php
// Подключаем WordPress footer
get_footer();
?>