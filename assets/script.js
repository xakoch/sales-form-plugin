/**
 * Скрипт для административной панели плагина My Forms
 * Переписан на vanilla JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Копирование ссылки в буфер обмена
    document.addEventListener('click', function(e) {
        if (e.target.closest('.copy-link')) {
            e.preventDefault();
            
            // Получаем ссылку из атрибута data-link
            var button = e.target.closest('.copy-link');
            var link = button.getAttribute('data-link');
            
            // Создаем временный элемент input
            var temp = document.createElement('input');
            document.body.appendChild(temp);
            temp.value = link;
            temp.select();
            
            // Копируем текст в буфер обмена
            document.execCommand('copy');
            
            // Удаляем временный элемент
            document.body.removeChild(temp);
            
            // Показываем уведомление
            var originalText = button.textContent;
            
            button.textContent = 'Скопировано!';
            
            setTimeout(function() {
                button.textContent = originalText;
            }, 2000);
        }
    });
    
    // Обработка подтверждений перед удалением
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-form')) {
            if (!confirm('Вы уверены, что хотите удалить эту форму? Все связанные заявки также будут удалены.')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    function updateExpirationTimers() {
        if (window.timerUpdateRunning) {
            return;
        }
        
        window.timerUpdateRunning = true;
        
        try {
            var timers = document.querySelectorAll('.time-left');
            timers.forEach(function(timer) {
                var expiresAt = new Date(timer.getAttribute('data-expires-at'));
                var now = new Date();
                
                // Вычисляем оставшееся время в миллисекундах
                var timeLeft = expiresAt.getTime() - now.getTime();
                
                if (timeLeft <= 0) {
                    // Если время истекло, меняем текст и стиль
                    timer.textContent = 'Истекла';
                    // Также можно добавить класс или изменить стиль
                    var card = timer.closest('.my-form-card');
                    if (card) {
                        card.classList.add('expired');
                        card.classList.remove('active', 'expiring-warning', 'expiring-soon');
                    }
                } else {
                    // Вычисляем точное время в часах без округления
                    var hours = Math.floor(timeLeft / (1000 * 60 * 60));
                    var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    
                    // Используем только часы, если есть часы, иначе минуты
                    var timeString;
                    if (hours > 0) {
                        timeString = hours + ' ч';
                    } else {
                        timeString = minutes + ' мин';
                    }
                    
                    timer.textContent = 'Осталось: ' + timeString;
                    
                    // Обновляем класс карточки
                    var card = timer.closest('.my-form-card');
                    
                    if (card) {
                        if (hours <= 1) {
                            card.classList.remove('active', 'expiring-warning');
                            card.classList.add('expiring-soon');
                        } else if (hours <= 6) {
                            card.classList.remove('active', 'expiring-soon');
                            card.classList.add('expiring-warning');
                        }
                    }
                }
            });
        } catch (e) {
            console.error("Ошибка при обновлении таймеров:", e);
        } finally {
            window.timerUpdateRunning = false;
        }
        
        // Запускаем следующее обновление через минуту
        setTimeout(updateExpirationTimers, 60000);
    }

    // Инициализация модальных окон, если jQuery UI доступен
    if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
        jQuery(function($) {
            // Модальное окно для создания формы
            $("#create-form-modal").dialog({
                autoOpen: false,
                modal: true,
                width: 600,
                buttons: {
                    "Отмена": function() {
                        $(this).dialog("close");
                    }
                }
            });
            
            // Модальное окно для редактирования названия
            $("#edit-title-modal").dialog({
                autoOpen: false,
                modal: true,
                width: 400
            });
        });
    }
    
    // Открытие модального окна для создания формы
    document.addEventListener('click', function(e) {
        if (e.target.closest('.add-new-form')) {
            e.preventDefault();
            if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                jQuery("#create-form-modal").dialog("open");
            }
        }
    });
    
    // Открытие модального окна для редактирования названия
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-title')) {
            e.preventDefault();
            var editLink = e.target.closest('.edit-title');
            var formTitle = editLink.closest('.form-title').querySelector('.title-text').textContent;
            var formId = editLink.closest('.form-title').getAttribute('data-form-id');
            
            if (typeof jQuery !== 'undefined') {
                jQuery('#edit-form-id').val(formId);
                jQuery('#edit-form-title').val(formTitle);
                jQuery("#edit-title-modal").dialog("open");
            }
        }
    });
    
    // Закрытие модального окна при нажатии на "Отмена"
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-edit')) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                jQuery("#edit-title-modal").dialog("close");
            }
        }
        
        if (e.target.classList.contains('cancel-create')) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                jQuery("#create-form-modal").dialog("close");
            }
        }
    });
    
    // Обработка AJAX-запроса для обновления названия формы
    var editForm = document.getElementById('edit-title-form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formId = document.getElementById('edit-form-id').value;
            var newTitle = document.getElementById('edit-form-title').value;
            
            // Создаем FormData для отправки
            var formData = new FormData();
            formData.append('action', 'update_form_title');
            formData.append('form_id', formId);
            formData.append('title', newTitle);
            formData.append('nonce', editForm.getAttribute('data-nonce') || '');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем заголовок в карточке
                    var titleElement = document.querySelector(".form-title[data-form-id='" + formId + "'] .title-text");
                    if (titleElement) {
                        titleElement.textContent = newTitle;
                    }
                    
                    // Показываем уведомление
                    var notice = document.createElement('div');
                    notice.className = 'notice notice-success is-dismissible';
                    notice.innerHTML = '<p>Название формы успешно обновлено.</p>';
                    document.querySelector('.wp-header-end').insertAdjacentElement('afterend', notice);
                    
                    setTimeout(function() {
                        notice.style.opacity = '0';
                        setTimeout(function() {
                            notice.remove();
                        }, 500);
                    }, 3000);
                    
                    // Закрываем модальное окно
                    if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                        jQuery("#edit-title-modal").dialog("close");
                    }
                } else {
                    // Показываем сообщение об ошибке
                    alert("Ошибка: " + data.data);
                }
            })
            .catch(error => {
                alert("Произошла ошибка при обновлении названия формы.");
            });
        });
    }
    
    // Инициализация диалоговых окон для просмотра деталей заявок
    if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
        jQuery(function($) {
            $(".request-details-modal").dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                maxHeight: 500
            });
        });
    }
    
    // Открытие модального окна с деталями заявки
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-details')) {
            var button = e.target.closest('.view-details');
            var requestId = button.getAttribute('data-request-id');
            if (typeof jQuery !== 'undefined' && jQuery.fn.dialog) {
                jQuery("#request-details-" + requestId).dialog("open");
            }
        }
    });
    
    // Добавляем анимацию для уведомлений
    setTimeout(function() {
        var notices = document.querySelectorAll('.notice');
        notices.forEach(function(notice) {
            if (!notice.classList.contains('is-dismissible')) {
                notice.style.transition = 'opacity 0.5s';
                notice.style.opacity = '0';
                setTimeout(function() {
                    notice.remove();
                }, 500);
            }
        });
    }, 3000);

    // Обработка удаления формы
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-form')) {
            e.preventDefault(); // Предотвращаем стандартное поведение
            
            var deleteLink = e.target.closest('.delete-form');
            var formId = deleteLink.getAttribute('data-form-id');
            var card = deleteLink.closest('.my-form-card');
            
            if (confirm('Вы уверены, что хотите удалить эту форму? Все связанные заявки также будут удалены.')) {
                // Создаем FormData для отправки
                var formData = new FormData();
                formData.append('action', 'delete_form');
                formData.append('form_id', formId);
                formData.append('security', window.myFormsAjax ? window.myFormsAjax.nonce : '');
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Удаляем карточку формы из DOM
                        card.style.transition = 'opacity 0.3s';
                        card.style.opacity = '0';
                        setTimeout(function() {
                            card.remove();
                            
                            // Если больше нет форм, показываем сообщение
                            var remainingCards = document.querySelectorAll('.my-form-card');
                            if (remainingCards.length === 0) {
                                var cardsContainer = document.querySelector('.my-forms-cards');
                                if (cardsContainer) {
                                    cardsContainer.innerHTML = '<div class="my-forms-empty-state"><p>У вас пока нет активных форм.</p><button class="button button-primary add-new-form">Создать первую форму</button></div>';
                                }
                            }
                        }, 300);
                        
                        // Показываем уведомление
                        var notice = document.createElement('div');
                        notice.className = 'notice notice-success is-dismissible';
                        notice.innerHTML = '<p>Форма успешно удалена</p>';
                        document.querySelector('.wp-header-end').insertAdjacentElement('afterend', notice);
                        
                        setTimeout(function() {
                            notice.style.opacity = '0';
                            setTimeout(function() {
                                notice.remove();
                            }, 500);
                        }, 3000);
                    } else {
                        alert('Ошибка: ' + (data.data || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    alert('Произошла ошибка при удалении формы');
                });
            }
        }
    });

    // Обработка изменения статуса заявки
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('request-status')) {
            var select = e.target;
            var requestId = select.getAttribute('data-request-id');
            var newStatus = select.value;
            
            // Создаем FormData для отправки
            var formData = new FormData();
            formData.append('action', 'update_request_status');
            formData.append('request_id', requestId);
            formData.append('status', newStatus);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Выделяем выпадающий список на короткое время
                    select.parentElement.classList.add('status-updated');
                    setTimeout(function() {
                        select.parentElement.classList.remove('status-updated');
                    }, 1500);
                } else {
                    alert('Ошибка: ' + data.data);
                    // Возвращаем предыдущее значение
                    select.value = select.getAttribute('data-prev-status');
                }
            })
            .catch(error => {
                alert('Произошла ошибка при обновлении статуса');
                select.value = select.getAttribute('data-prev-status');
            });
            
            // Сохраняем текущее значение
            select.setAttribute('data-prev-status', newStatus);
        }
    });

    // Сохраняем начальные значения статусов
    var statusSelects = document.querySelectorAll('.request-status');
    statusSelects.forEach(function(select) {
        select.setAttribute('data-prev-status', select.value);
    });
    
    // Обработка предварительного просмотра логотипа
    var logoInput = document.getElementById('form_logo');
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            var preview = document.getElementById('logo-preview');
            
            if (file && file.type.startsWith('image/')) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo preview">';
                };
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
    }
});