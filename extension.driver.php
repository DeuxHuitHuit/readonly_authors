<?php
/*
 * Copyrights: Deux Huit Huit 2019
 * LICENCE: MIT https://deuxhuithuit.mit-license.org
 */

if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

/**
 *
 * @author Deux Huit Huit
 * https://deuxhuithuit.com/
 *
 */
class extension_readonly_authors extends Extension
{
    /**
     * Name of the extension
     * @var string
     */
    const EXT_NAME = 'Read Only Authors';

    /**
     * Name of the table
     * @var string
     */
    const TBL_NAME = 'tbl_authors_readonly_authors';
    
    /**
     * Symphony utility function that permits to
     * implement the Observer/Observable pattern.
     * We register here delegate that will be fired by Symphony
     */
    public function getSubscribedDelegates()
    {
        return array(
            // assets
            array(
                'page' => '/backend/',
                'delegate' => 'InitaliseAdminPageHead',
                'callback' => 'appendAssets',
            ),
            // authors index
            array(
                'page' => '/system/authors/',
                'delegate' => 'AddCustomAuthorColumn',
                'callback' => 'addCustomAuthorColumn',
            ),
            array(
                'page' => '/system/authors/',
                'delegate' => 'AddCustomAuthorColumnData',
                'callback' => 'addCustomAuthorColumnData',
            ),
            // authors form
            array(
                'page' => '/system/authors/',
                'delegate' => 'AddElementstoAuthorForm',
                'callback' => 'addElementstoAuthorForm',
            ),
            // author delete
            array(
                'page' => '/system/authors/',
                'delegate' => 'AuthorPostDelete',
                'callback' => 'authorPostDelete',
            ),
            // author create
            array(
                'page' => '/system/authors/',
                'delegate' => 'AuthorPostCreate',
                'callback' => 'authorPostCreate',
            ),
            // author edit
            array(
                'page' => '/system/authors/',
                'delegate' => 'AuthorPostEdit',
                'callback' => 'authorPostEdit',
            ),
            // entries
            array(
                'page'      => '/publish/new/',
                'delegate'  => 'EntryPreCreate',
                'callback'  => 'entryPreCreate'
            ),
            array(
                'page'      => '/publish/edit/',
                'delegate'  => 'EntryPreEdit',
                'callback'  => 'entryPreEdit'
            ),
            array(
                'page'      => '/publish/',
                'delegate'  => 'EntryPreDelete',
                'callback'  => 'entryPreDelete'
            ),
        );
    }

    protected static function isAllowedToEdit($author_id = null)
    {
        $curAuthor = Symphony::Author();
        
        // Unauthenticated user can not edit
        if (!$curAuthor) {
            return false;
        }
        
        // Takes privileges to edit this
        if (!$curAuthor->isDeveloper() &&
            !$curAuthor->isManager() &&
            !$curAuthor->isPrimaryAccount()) {
            return false;
        }
        
        // Even manager should not edit their own
        if ($author_id &&
            !$curAuthor->isDeveloper() &&
            !$curAuthor->isPrimaryAccount() &&
            $curAuthor->get('id') == $author_id) {
            return false;
        }
        return true;
    }

    /**
     *
     * Appends javascript/css files references into the head, if needed
     * @param array $context
     */
    public function appendAssets(array $context)
    {
        // store the callback array locally
        $c = Administration::instance()->getPageCallback();
        // publish page
        if($c['driver'] === 'systemauthors') {
            return;
        }
        Administration::instance()->Page->addScriptToHead(URL.'/extensions/readonly_authors/assets/readonly_authors.js');
    }

    public function addCustomAuthorColumn(array $context)
    {
        $context['columns'][] = array(
            'label' => __('Read only?'),
            'sortable' => false,
            'handle' => 'read-only'
        );
    }

    public function addCustomAuthorColumnData(array $context)
    {
        $author = $context['author'];
        $inactive = true;
        $text = __('No');
        
        if (self::isReadOnly($author->get('id'))) {
            $inactive = false;
            $text = __('Yes');
        }
        
        if ($inactive) {
            $class = 'inactive';
        }
        $context['tableData'][] = Widget::TableData($text, $class);
    }


    public function addElementstoAuthorForm(array $context)
    {
        /*
        'form' => &$this->Form,
        'author' => $author,
        'fields' => $_POST['fields'],
        */
        $author = $context['author'];

        if (!static::isAllowedToEdit($author->get('id'))) {
            return;
        }

        if ($author->isDeveloper() || $author->isPrimaryAccount()) {
            return;
        }

        $sections = SectionManager::fetch();

        $group = static::createAuthorFormElements($author, $sections, $context['errors']);

        $context['form']->insertChildAt($context['form']->getNumberOfChildren() - 2, $group);
    }

    public function authorPostDelete(array $context)
    {
        self::delete($context['author_id']);
    }

    public function authorPostCreate(array $context)
    {
        if (!static::isAllowedToEdit()) {
            return;
        }
        $isReadOnly = static::getValueFromPost();
        if ($isReadOnly === null) {
            return;
        }
        static::save($isReadOnly, $context['author']);
    }

    public function authorPostEdit(array $context)
    {
        if (!static::isAllowedToEdit($context['author']->get('id'))) {
            return;
        }
        $isReadOnly = static::getValueFromPost();
        if ($isReadOnly === null) {
            return;
        }
        static::save($isReadOnly, $context['author']);
    }

    public function checkForReadOnly($action)
    {
        if (!self::isReadOnly(Symphony::Author()->get('id'))) {
            return;
        }
        $message = __('Read only authors cannot edit entries.') . ' ' . __('This incident has been reported.');
        $logMsg = '[readonly_authors] User `' . Symphony::Author()->get('username') . "` tried to $action via " . $_SERVER['REQUEST_URI'];
        Symphony::Log()->pushToLog($logMsg, E_WARNING, true, true, false);
        if (class_exists('Administration', false)) {
            Administration::throwCustomError(
                $message,
                __('Unauthorized'),
                Page::HTTP_STATUS_UNAUTHORIZED
            );
        } else {
            throw new Exception($message);
        }
    }

    public function entryPreCreate($context)
    {
        $this->checkForReadOnly('create');
    }

    public function entryPreEdit($context)
    {
        $this->checkForReadOnly('edit');
    }

    public function entryPreDelete($context)
    {
        $this->checkForReadOnly('delete');
    }

    /* ********* LIB ******* */

    protected static function createAuthorFormElements(Author &$author, array $sections, $errors)
    {
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', __('Read Only Authors')));
        $help = new XMLElement('p', __('Makes it impossible to edit or create content'), array('class' => 'help'));
        $group->appendChild($help);

        $isReadOnly = static::getValueFromPost();
        if ($isReadOnly === null) {
            // No value from post, use from author
            $isReadOnly = self::isReadOnly($author->get('id'));
        }
        
        $label = Widget::Checkbox('readonly_authors', $isReadOnly ? 'yes' : 'no', __('Read only?'));

        if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            is_array($errors) &&
            isset($errors['readonly_authors'])) {
            $group->appendChild(Widget::Error($label, $errors['readonly_authors']));
        }
        else {
            $group->appendChild($label);
        }

        return $group;
    }

    protected static function getValueFromPost()
    {
        if (!isset($_POST['readonly_authors']) ||
            empty($_POST['readonly_authors'])) {
            return null;
        }
        return $_POST['readonly_authors'] === 'yes';
    }

    protected static function save($isReadOnly, Author $author)
    {
        if (!$author->get('id')) {
            return false;
        }
        $ret = true;
        if ($isReadOnly) {
            $ret = Symphony::Database()->insert(array(
                'author_id' => intval($author->get('id')),
            ), self::TBL_NAME, true);
        } else {
            $ret = Symphony::Database()->delete(self::TBL_NAME, '`author_id` = ' . intval($author->get('id')));
        }
        return $ret;
    }

    public static function isReadOnly($author_id)
    {
        if (!$author_id) {
            return true;
        }
        $rows = Symphony::Database()->fetch(sprintf("
            SELECT `id` FROM `%s`
                WHERE `author_id` = %d
        ", self::TBL_NAME, intval($author_id)));
        return !empty($rows);
    }

    protected static function delete($author_id)
    {
        if (!$author_id) {
            return true;
        }
        return Symphony::Database()->fetch(sprintf("
            DELETE FROM `%s` WHERE `author_id` = %d
        ", self::TBL_NAME, intval($author_id)));
    }

    protected static function createTable()
    {
        $tbl = self::TBL_NAME;
        $ret = Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `$tbl` (
                `id` int(11) unsigned NOT NULL auto_increment,
                `author_id` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                KEY `author_id` (`author_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ");
        return $ret;
    }

    protected static function dropTable()
    {
        $tbl = self::TBL_NAME;
        $ret = Symphony::Database()->query("DROP TABLE IF EXISTS `$tbl`");
        return $ret;
    }

    /* ********* INSTALL/UPDATE/UNINSTALL ******* */

    /**
     * Creates the table needed for the settings of the field
     */
    public function install()
    {
        return static::createTable();
    }

    /**
     * This method will update the extension according to the
     * previous and current version parameters.
     * @param string $previousVersion
     */
    public function update($previousVersion = false)
    {
        $ret = true;
        
        if (!$previousVersion) {
            $previousVersion = '0.0.1';
        }
        
        // less than 0.1.0
        if ($ret && version_compare($previousVersion, '0.1.0') == -1) {
            
        }
        
        return $ret;
    }

    /**
     * Drops the table needed for the settings of the field
     */
    public function uninstall()
    {
        return static::dropTable();
    }
}
