<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap my-forms-admin-page">
    <h1>My Forms Dashboard</h1>
    
    <div class="my-forms-dashboard">
        <!-- Блок статистики -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-feedback"></span>
                </div>
                <div class="stat-content">
                    <h3>Всего форм</h3>
                    <p class="stat-number"><?php echo $stats['total_forms']; ?></p>
                </div>
            </div>
            
            <div class="stat-card active">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3>Активные формы</h3>
                    <p class="stat-number"><?php echo $stats['active_forms']; ?></p>
                </div>
            </div>
            
            <div class="stat-card expired">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3>Истекшие формы</h3>
                    <p class="stat-number"><?php echo $stats['expired_forms']; ?></p>
                </div>
            </div>
            
            <div class="stat-card requests">
                <div class="stat-icon">
                    <span class="dashicons dashicons-list-view"></span>
                </div>
                <div class="stat-content">
                    <h3>Всего заявок</h3>
                    <p class="stat-number"><?php echo $stats['total_requests']; ?></p>
                </div>
            </div>
            
            <div class="stat-card recent">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <h3>Заявки за 24 часа</h3>
                    <p class="stat-number"><?php echo $stats['recent_requests']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Блок быстрого действия -->
        <div class="quick-actions">
            <div class="action-card">
                <h3>Быстрое действие</h3>
                <p>Создайте новую форму, которую можно отправить клиентам</p>
                <a href="<?php echo admin_url('admin.php?page=my-forms'); ?>" class="button button-primary">Создать форму</a>
            </div>
        </div>
        
        <!-- Блок с последними заявками -->
        <div class="recent-data-container">
            <div class="data-card">
                <h3>Последние заявки</h3>
                
                <?php if (empty($recent_requests)) : ?>
                    <p class="empty-message">У вас пока нет заявок. Когда пользователи заполнят ваши формы, заявки будут отображаться здесь.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ФИО</th>
                                <th>Форма</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_requests as $request) : ?>
                                <tr>
                                    <td><?php echo esc_html($request->full_name); ?></td>
                                    <td><?php echo esc_html($request->form_title); ?></td>
                                    <td><?php echo date_i18n('d.m.Y H:i', strtotime($request->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="view-all-link">
                        <a href="<?php echo admin_url('admin.php?page=my-forms-requests'); ?>">Просмотреть все заявки</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Блок с активными формами -->
            <div class="data-card">
                <h3>Активные формы</h3>
                
                <?php if (empty($active_forms)) : ?>
                    <p class="empty-message">У вас пока нет активных форм. Создайте свою первую форму!</p>
                    <a href="<?php echo admin_url('admin.php?page=my-forms'); ?>" class="button button-primary">Создать форму</a>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Истекает через</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_forms as $form) : 
                                // Рассчитываем оставшееся время
                                $now = current_time('timestamp');
                                $expires = strtotime($form->expires_at);
                                $time_left = $expires - $now;
                                $hours_left = max(0, round($time_left / 3600, 1));
                            ?>
                                <tr>
                                    <td><?php echo esc_html($form->title); ?></td>
                                    <td><?php echo $hours_left; ?> ч.</td>
                                    <td>
                                        <a href="<?php echo esc_url($form->link); ?>" class="button button-small" target="_blank">Просмотр</a>
                                        <button class="button button-small copy-link" data-link="<?php echo esc_url($form->link); ?>">Копировать ссылку</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="view-all-link">
                        <a href="<?php echo admin_url('admin.php?page=my-forms'); ?>">Управление формами</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Копирование ссылки в буфер обмена
    document.addEventListener('click', function(e) {
        if (e.target.closest('.copy-link')) {
            e.preventDefault();
            
            var button = e.target.closest('.copy-link');
            var link = button.getAttribute('data-link');
            var tempInput = document.createElement('input');
            document.body.appendChild(tempInput);
            tempInput.value = link;
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // Показываем всплывающее уведомление
            var originalText = button.textContent;
            
            button.textContent = 'Скопировано!';
            
            setTimeout(function() {
                button.textContent = originalText;
            }, 2000);
        }
    });
});
</script>

<style>
.my-forms-dashboard {
    margin-top: 20px;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid #2271b1;
}

.stat-card.active {
    border-left-color: #46b450;
}

.stat-card.expired {
    border-left-color: #dc3232;
}

.stat-card.requests {
    border-left-color: #ffb900;
}

.stat-card.recent {
    border-left-color: #826eb4;
}

.stat-icon {
    margin-right: 15px;
}

.stat-icon .dashicons {
    font-size: 30px;
    width: 30px;
    height: 30px;
    color: #666;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    color: #333;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
    border-top: 4px solid #2271b1;
}

.action-card h3 {
    margin-top: 0;
}

.recent-data-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.data-card {
    background: #fff;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.data-card h3 {
    margin-top: 0;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.empty-message {
    text-align: center;
    padding: 20px;
    color: #666;
}

.view-all-link {
    text-align: right;
    margin-top: 15px;
    border-top: 1px solid #f0f0f0;
    padding-top: 10px;
}

@media screen and (max-width: 782px) {
    .stats-container,
    .quick-actions,
    .recent-data-container {
        grid-template-columns: 1fr;
    }
}
</style>