<?php

return array(
    'starToZotero' => array(
        'configure' => array(
            'api_key' => 'Zotero API key',
            'user_id' => 'Zotero user ID',
            'collection_key' => '“To Read” collection key',
            'translation_server_url' => 'Translation Server URL (optional)',
            'extra_tags' => 'Extra tags (comma-separated)',
            'keyboard_shortcut' => 'Keyboard shortcut',
            'extension_disabled' => 'You need to enable the extension before configuration takes effect!',
            'connected' => 'Configured with user ID <b>%s</b>, collection <b>%s</b>.',
            'save_hint' => 'Save your settings and then star an entry to send it to Zotero.',
        ),
        'notifications' => array(
            'added_article_to_zotero' => 'Successfully added <i>\'%s\'</i> to Zotero!',
            'failed_to_add_article_to_zotero' => 'Adding to Zotero failed! Error: %s',
            'ajax_request_failed' => 'Ajax request failed!',
            'article_not_found' => 'Can\'t find article!',
        )
    ),
);
