<?php

class FreshExtension_starToZotero_Controller extends Minz_ActionController
{
    public function jsVarsAction()
    {
        $extension = Minz_ExtensionManager::findExtension('StarToZotero');

        $this->view->stz_vars = json_encode(array(
            'keyboard_shortcut' => FreshRSS_Context::$user_conf->zotero_keyboard_shortcut,
            'i18n' => array(
                'added_article_to_zotero' => _t('ext.starToZotero.notifications.added_article_to_zotero', '%s'),
                'failed_to_add_article_to_zotero' => _t('ext.starToZotero.notifications.failed_to_add_article_to_zotero', '%s'),
                'ajax_request_failed' => _t('ext.starToZotero.notifications.ajax_request_failed'),
                'article_not_found' => _t('ext.starToZotero.notifications.article_not_found'),
            )
        ));

        $this->view->_layout(false);
        header('Content-Type: application/javascript; charset=utf-8');
    }
}
