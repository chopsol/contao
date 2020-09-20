<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Security;

final class ContaoCorePermissions
{
    /**
     * Access is granted if the current user can edit the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_EDIT_PAGE = 'contao_user.can_edit_page';

    /**
     * Access is granted if the current user can change the hierarchy of the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_EDIT_PAGE_HIERARCHY = 'contao_user.can_edit_page_hierarchy';

    /**
     * Access is granted if the current user can can delete the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_DELETE_PAGE = 'contao_user.can_delete_page';

    /**
     * Access is granted if the current user can edit articles of the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_EDIT_ARTICLES = 'contao_user.can_edit_articles';

    /**
     * Access is granted if the current user can change the hierarchy of articles of the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_EDIT_ARTICLE_HIERARCHY = 'contao_user.can_edit_article_hierarchy';

    /**
     * Access is granted if the current user can delete articles of the given page.
     * Subject must be a PageModel or a tl_page record as array.
     */
    public const USER_CAN_DELETE_ARTICLES = 'contao_user.can_delete_articles';

    /**
     * Access is granted if the current user can upload files to the server.
     */
    public const USER_CAN_UPLOAD_FILES = 'contao_user.fop.f1';

    /**
     * Access is granted if the current user can edit, copy or move files and folders.
     */
    public const USER_CAN_RENAME_FILE = 'contao_user.fop.f2';

    /**
     * Access is granted if the current user can delete single files and empty folders.
     */
    public const USER_CAN_DELETE_FILE = 'contao_user.fop.f3';

    /**
     * Access is granted if the current user can delete folders including all files and subfolders.
     */
    public const USER_CAN_DELETE_RECURSIVELY = 'contao_user.fop.f4';

    /**
     * Access is granted if the current user can edit files in the source editor.
     */
    public const USER_CAN_EDIT_FILE = 'contao_user.fop.f5';

    /**
     * Access is granted if the current user can synchronize the file system with the database.
     */
    public const USER_CAN_SYNC_DBAFS = 'contao_user.fop.f6';

    /**
     * Access is granted if the current user can edit at least one field of the table.
     * Subject must be a table name (e.g. "tl_page").
     */
    public const USER_CAN_EDIT_FIELDS_OF_TABLE = 'contao_user.can_edit_fields';

    /**
     * Access is granted if the current user can edit the field of a table.
     * Subject must be a table and field separated by two colons (e.g. "tl_page::title").
     */
    public const USER_CAN_EDIT_FIELD_OF_TABLE = 'contao_user.alexf';

    /**
     * Access is granted if the current user can access the back end module.
     * Subject must be a module name (e.g. "article").
     */
    public const USER_CAN_ACCESS_MODULE = 'contao_user.modules';

    /**
     * Access is granted if the current user can access the content element type.
     * Subject must be a content element type (e.g. "text").
     */
    public const USER_CAN_ACCESS_ELEMENT_TYPE = 'contao_user.elements';

    /**
     * Access is granted if the current user can access the form field type.
     * Subject must be a content element type (e.g. "hidden").
     */
    public const USER_CAN_ACCESS_FIELD_TYPE = 'contao_user.fields';

    /**
     * Access is granted if the current user can access the theme.
     * Subject must be an ID of table tl_theme.
     */
    public const USER_CAN_ACCESS_THEME = 'contao_user.themes';

    /**
     * Access is granted if the current user can access the page type.
     * Subject must be a page type as string (e.g. "regular").
     */
    public const USER_CAN_ACCESS_PAGE_TYPE = 'contao_user.alpty';

    /**
     * Access is granted if the given path is mounted for the current user.
     * Subject must be path as string (e.g. "files/content/foo").
     */
    public const USER_CAN_ACCESS_PATH = 'contao_user.filemounts';

    /**
     * Access is granted if the current user can access the image size.
     * Subject must be an image size ID from tl_image_size or a configuration name (e.g. "crop").
     */
    public const USER_CAN_ACCESS_IMAGE_SIZE = 'contao_user.imageSizes';

    /**
     * Access is granted if the current user can access the form.
     * Subject must be a form ID from tl_form.
     */
    public const USER_CAN_ACCESS_FORM = 'contao_user.forms';

    /**
     * Access is granted if the current user can create forms.
     */
    public const USER_CAN_CREATE_FORMS = 'contao_user.formp.create';

    /**
     * Access is granted if the current user can delete forms.
     */
    public const USER_CAN_DELETE_FORMS = 'contao_user.formp.delete';
}
