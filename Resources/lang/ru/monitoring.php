<?php

return [
    'widget' => 'Мониторинг',
    'single_server' => [
        'widget' => 'Сервер мониторинга',
        'select_server_help' => 'Выберите сервер, который будет отображаться в виджете.',
        'hide_modal' => 'Скрыть модальное окно',
        'hide_modal_help' => 'Не показывать модальное окно с информацией о сервере при нажатии на кнопку "Подробнее".',
    ],
    'server' => [
        'our_servers' => 'Наши серверы',
        'details' => 'Детали сервера',
        'ip' => 'IP адрес',
        'status' => 'Статус',
        'map' => 'Карта',
        'players' => 'Игроков',
        'inactive' => 'Неактивен',
        'online' => 'Онлайн',
        'offline' => 'Офлайн',
        'error' => 'Ошибка соединения',
        'maintenance' => 'Сервер в данный момент отключен или находится на техническом обслуживании.',
        'unavailable' => 'Не удалось получить данные о сервере. Возможно, сервер временно недоступен.',
        'no_players' => 'Нет игроков на сервере',
        'player_list' => 'Игроки на сервере',
        'empty_players_alert' => 'Если в мониторинге не показываются имена игроков, то проверьте наличие <a target="_blank" href=":url">этого плагина</a> на сервере.',
        'player' => [
            'name' => 'Имя',
            'score' => 'Счет',
            'time' => 'Время',
            'unknown' => 'Unknown',
            'total' => 'Всего игроков',
            'search' => 'Поиск игрока',
            'no_results' => 'Игроки не найдены'
        ],
        'csgo' => [
            'counter_terrorists' => 'Спецназ',
            'terrorists' => 'Террористы',
            'kills' => 'Убийства',
            'deaths' => 'Смерти',
            'headshots' => 'Хедшоты',
            'ping' => 'Пинг',
            'team' => 'Команда',
            'prime' => 'Прайм-статус',
            'profile' => 'Профиль Steam',
            'playtime' => 'Время игры',
            'time' => 'Время на сервере',
            'rank' => 'Ранг',
        ],
        'actions' => [
            'play' => 'Играть',
            'close' => 'Закрыть',
            'copy_ip' => 'Скопировать в буфер обмена',
            'more' => 'Подробнее',
            'copy_ip_success' => 'IP сервера скопирован в буфер обмена'
        ],
        'status_icon' => [
            'inactive' => 'Сервер неактивен',
            'error' => 'Ошибка соединения с сервером',
            'online' => 'Сервер онлайн'
        ],
        'no_servers' => 'Нет доступных серверов',
        'unknown_server' => 'Неизвестный сервер',
        'no_server_selected' => 'Не выбран сервер для отображения',
        'select_server' => 'Выберите сервер'
    ],
    'settings' => [
        'hide_inactive_servers' => 'Скрыть неактивные серверы',
        'servers_limit' => 'Количество серверов для отображения',
        'servers_limit_help' => 'Кол-во серверов, которые будут отображаться в виджете.',
        'display_mode' => 'Режим отображения',
        'display_mode_standard' => 'Стандартный',
        'display_mode_compact' => 'Компактный',
        'display_mode_ultracompact' => 'Ультракомпактный',
        'display_mode_table' => 'Таблица',
        'display_mode_help' => 'Выберите способ отображения серверов',
        'show_count_players' => 'Показывать общее количество игроков',
        'show_count_players_help' => 'Показывать общее количество игроков на всех серверах в виджете.',
        'show_placeholders' => 'Показывать заглушки',
        'show_placeholders_help' => 'Показывать заглушки чтобы заполнить пространство.'
    ],
    'tabs' => [
        'statistics' => 'Статистика серверов',
        'daily_stats' => 'День',
        'weekly_stats' => 'Неделя',
        'monthly_stats' => 'Месяц',
        'all_servers' => 'Все серверы',
    ],
    'charts' => [
        'day_stats' => 'Активность серверов (24 часа)',
        'week_stats' => 'Активность серверов (7 дней)',
        'month_stats' => 'Активность серверов (30 дней)',
        'players' => 'Игроки',
        'servers' => 'Онлайн серверы',
        'game_distribution' => 'Распределение игр',
        'server_distribution' => 'Распределение игроков по серверам',
        'max_players' => 'Макс. игроков',
        'max_players_period' => 'Пиковое количество игроков',
        'players_vs_capacity' => 'Игроки vs Вместимость',
        'capacity_utilization' => 'Загруженность серверов',
        'hourly_traffic' => 'Почасовой трафик',
    ],
    'descriptions' => [
        'day_stats' => 'Количество игроков и серверов за последние 24 часа',
        'week_stats' => 'Количество игроков и серверов за последние 7 дней',
        'month_stats' => 'Количество игроков и серверов за последние 30 дней',
        'game_distribution' => 'Распределение игроков по разным играм',
        'server_distribution' => 'Распределение игроков по серверам',
        'period_multi_server_stats' => 'Количество игроков по серверам за период',
        'day_multi_server_stats' => 'Количество игроков по серверам за последние 24 часа',
        'week_multi_server_stats' => 'Количество игроков по серверам за последние 7 дней',
        'month_multi_server_stats' => 'Количество игроков по серверам за последние 30 дней',
        'players_vs_capacity' => 'Текущее и максимальное количество игроков на сервере',
        'capacity_utilization' => 'Загруженность сервера по времени',
        'hourly_traffic' => 'Среднее количество игроков по часам дня',
    ],
    'metrics' => [
        'total_servers' => 'Всего серверов',
        'online_servers' => 'Онлайн серверы',
        'total_players' => 'Всего игроков',
        'servers_fill' => 'Заполненность серверов',
    ],
    'no_data' => 'Нет данных',
    'total_online' => [
        'widget' => 'Общий онлайн',
        'title' => 'Игроков онлайн',
        'players_online' => 'Игроков онлайн',
        'servers_online' => ':active из :total серверов онлайн',
    ],
    'navbar' => [
        'logo' => 'Игроков онлайн',
    ],
];
