<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('saved_searches');
output_source('saved_searches');

add_handler('ajax_save_search', 'login', false, 'core');
add_handler('ajax_save_search', 'load_user_data', true, 'core');
add_handler('ajax_save_search', 'save_search', true, 'core');
add_handler('ajax_save_search', 'language', true);
add_handler('ajax_save_search', 'date', true, 'core');
add_handler('ajax_save_search', 'http_headers', true, 'core');
add_output('ajax_save_search', 'filter_saved_search_result', true);

add_handler('ajax_update_search', 'login', false, 'core');
add_handler('ajax_update_search', 'load_user_data', true, 'core');
add_handler('ajax_update_search', 'update_search', true, 'core');
add_handler('ajax_update_search', 'language', true);
add_handler('ajax_update_search', 'date', true, 'core');
add_handler('ajax_update_search', 'http_headers', true, 'core');
add_output('ajax_update_search', 'filter_saved_search_result', true);

add_handler('ajax_delete_search', 'login', false, 'core');
add_handler('ajax_delete_search', 'load_user_data', true, 'core');
add_handler('ajax_delete_search', 'delete_search', true, 'core');
add_handler('ajax_delete_search', 'language', true);
add_handler('ajax_delete_search', 'date', true, 'core');
add_handler('ajax_delete_search', 'http_headers', true, 'core');
add_output('ajax_delete_search', 'filter_saved_search_result', true);

add_handler('ajax_update_save_search_label', 'login', false, 'core');
add_handler('ajax_update_save_search_label', 'load_user_data', true, 'core');
add_handler('ajax_update_save_search_label', 'update_save_search_label', true, 'core');
add_handler('ajax_update_save_search_label', 'language', true);
add_handler('ajax_update_save_search_label', 'date', true, 'core');
add_handler('ajax_update_save_search_label', 'http_headers', true, 'core');
add_output('ajax_update_save_search_label', 'filter_saved_search_result', true);


add_handler('ajax_hm_folders', 'saved_search_folder_data',  true, 'saved_searches', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'search_folders',  true, 'saved_searches', 'folder_list_content_start', 'before');

add_handler('search', 'save_searches_data', true, 'saved_searches', 'load_user_data', 'after');
add_output('search', 'save_search_icon', true, 'saved_searches', 'search_results_table_end', 'after');
add_output('search', 'update_search_label_icon', true, 'saved_searches', 'search_results_table_end', 'after');
add_output('search', 'search_name_fld', true, 'saved_searches', 'search_form_content', 'after');
add_output('search', 'delete_search_icon', true, 'saved_searches', 'search_form_end', 'after');
add_output('search', 'update_search_icon', true, 'saved_searches', 'search_form_end', 'after');

add_handler('advanced_search', 'save_searches_data', true, 'saved_searches', 'load_user_data', 'after');
add_output('advanced_search', 'save_search_icon', true, 'saved_searches', 'advanced_search_results_table_end', 'after');
add_output('advanced_search', 'update_search_label_icon', true, 'saved_searches', 'advanced_search_results_table_end', 'after');
add_output('advanced_search', 'search_name_fld', true, 'saved_searches', 'advanced_search_form_content', 'after');
add_output('advanced_search', 'delete_search_icon', true, 'saved_searches', 'advanced_search_form_end', 'after');
add_output('advanced_search', 'update_search_icon', true, 'saved_searches', 'advanced_search_form_end', 'after');


return array(
    'allowed_pages' => array(
        'ajax_save_search',
        'ajax_update_search',
        'ajax_delete_search',
        'ajax_update_save_search_label',
    ),
    'allowed_get' => array(
        'search_name' => FILTER_SANITIZE_STRING
    ),
    'allowed_post' => array(
        'search_name' => FILTER_SANITIZE_STRING,
        'search_terms' => FILTER_SANITIZE_STRING,
        'search_fld' => FILTER_SANITIZE_STRING,
        'search_since' => FILTER_SANITIZE_STRING,
        'search_terms_label' => FILTER_SANITIZE_STRING,
        'old_search_terms_label' => FILTER_SANITIZE_STRING
    ),
    'allowed_output' => array(
        'saved_search_result' => array(FILTER_VALIDATE_INT, false),
        'new_saved_search_label' => array(FILTER_SANITIZE_STRING, false),
    ),
);
