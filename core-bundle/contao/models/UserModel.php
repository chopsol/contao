<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\Model\Collection;

/**
 * Reads and writes users
 *
 * @property integer           $id
 * @property integer           $tstamp
 * @property string|null       $username
 * @property string            $name
 * @property string            $email
 * @property string            $language
 * @property string            $backendTheme
 * @property string            $uploader
 * @property boolean           $showHelp
 * @property boolean           $thumbnails
 * @property boolean           $useRTE
 * @property boolean           $useCE
 * @property boolean           $doNotCollapse
 * @property boolean           $doNotHideMessages
 * @property string            $password
 * @property boolean           $pwChange
 * @property boolean           $admin
 * @property string|array|null $groups
 * @property string            $inherit
 * @property string|array|null $modules
 * @property string|array|null $themes
 * @property string|array|null $elements
 * @property string|array|null $fields
 * @property string|array|null $pagemounts
 * @property string|array|null $alpty
 * @property string|array|null $filemounts
 * @property string|array|null $fop
 * @property string|array|null $imageSizes
 * @property string|array|null $forms
 * @property string|array|null $formp
 * @property string|array|null $amg
 * @property boolean           $disable
 * @property string|integer    $start
 * @property string|integer    $stop
 * @property string|array|null $session
 * @property integer           $dateAdded
 * @property string|null       $secret
 * @property boolean           $useTwoFactor
 * @property integer           $lastLogin
 * @property integer           $currentLogin
 * @property string|null       $backupCodes
 * @property integer           $trustedTokenVersion
 *
 * @method static UserModel|null findById($id, array $opt=array())
 * @method static UserModel|null findByPk($id, array $opt=array())
 * @method static UserModel|null findByIdOrAlias($val, array $opt=array())
 * @method static UserModel|null findByEmail($val, array $opt=array())
 * @method static UserModel|null findByUsername($val, array $opt=array())
 * @method static UserModel|null findOneBy($col, $val, array $opt=array())
 * @method static UserModel|null findOneByTstamp($val, array $opt=array())
 * @method static UserModel|null findOneByName($val, array $opt=array())
 * @method static UserModel|null findOneByEmail($val, array $opt=array())
 * @method static UserModel|null findOneByLanguage($val, array $opt=array())
 * @method static UserModel|null findOneByBackendTheme($val, array $opt=array())
 * @method static UserModel|null findOneByFullscreen($val, array $opt=array())
 * @method static UserModel|null findOneByUploader($val, array $opt=array())
 * @method static UserModel|null findOneByShowHelp($val, array $opt=array())
 * @method static UserModel|null findOneByThumbnails($val, array $opt=array())
 * @method static UserModel|null findOneByUseRTE($val, array $opt=array())
 * @method static UserModel|null findOneByUseCE($val, array $opt=array())
 * @method static UserModel|null findOneByDoNotCollapse($val, array $opt=array())
 * @method static UserModel|null findOneByDoNotHideMessages($val, array $opt=array())
 * @method static UserModel|null findOneByPassword($val, array $opt=array())
 * @method static UserModel|null findOneByPwChange($val, array $opt=array())
 * @method static UserModel|null findOneByAdmin($val, array $opt=array())
 * @method static UserModel|null findOneByGroups($val, array $opt=array())
 * @method static UserModel|null findOneByInherit($val, array $opt=array())
 * @method static UserModel|null findOneByModules($val, array $opt=array())
 * @method static UserModel|null findOneByThemes($val, array $opt=array())
 * @method static UserModel|null findOneByElements($val, array $opt=array())
 * @method static UserModel|null findOneByFields($val, array $opt=array())
 * @method static UserModel|null findOneByPagemounts($val, array $opt=array())
 * @method static UserModel|null findOneByAlpty($val, array $opt=array())
 * @method static UserModel|null findOneByFilemounts($val, array $opt=array())
 * @method static UserModel|null findOneByFop($val, array $opt=array())
 * @method static UserModel|null findOneByImageSizes($val, array $opt=array())
 * @method static UserModel|null findOneByForms($val, array $opt=array())
 * @method static UserModel|null findOneByFormp($val, array $opt=array())
 * @method static UserModel|null findOneByAmg($val, array $opt=array())
 * @method static UserModel|null findOneByDisable($val, array $opt=array())
 * @method static UserModel|null findOneByStart($val, array $opt=array())
 * @method static UserModel|null findOneByStop($val, array $opt=array())
 * @method static UserModel|null findOneBySession($val, array $opt=array())
 * @method static UserModel|null findOneByDateAdded($val, array $opt=array())
 * @method static UserModel|null findOneBySecret($val, array $opt=array())
 * @method static UserModel|null findOneByUseTwoFactor($val, array $opt=array())
 * @method static UserModel|null findOneByLastLogin($val, array $opt=array())
 * @method static UserModel|null findOneByCurrentLogin($val, array $opt=array())
 * @method static UserModel|null findOneByBackupCodes($val, array $opt=array())
 * @method static UserModel|null findOneByTrustedTokenVersion($val, array $opt=array())
 *
 * @method static Collection<UserModel>|null findByTstamp($val, array $opt=array())
 * @method static Collection<UserModel>|null findByName($val, array $opt=array())
 * @method static Collection<UserModel>|null findByLanguage($val, array $opt=array())
 * @method static Collection<UserModel>|null findByBackendTheme($val, array $opt=array())
 * @method static Collection<UserModel>|null findByFullscreen($val, array $opt=array())
 * @method static Collection<UserModel>|null findByUploader($val, array $opt=array())
 * @method static Collection<UserModel>|null findByShowHelp($val, array $opt=array())
 * @method static Collection<UserModel>|null findByThumbnails($val, array $opt=array())
 * @method static Collection<UserModel>|null findByUseRTE($val, array $opt=array())
 * @method static Collection<UserModel>|null findByUseCE($val, array $opt=array())
 * @method static Collection<UserModel>|null findByDoNotCollapse($val, array $opt=array())
 * @method static Collection<UserModel>|null findByDoNotHideMessages($val, array $opt=array())
 * @method static Collection<UserModel>|null findByPassword($val, array $opt=array())
 * @method static Collection<UserModel>|null findByPwChange($val, array $opt=array())
 * @method static Collection<UserModel>|null findByAdmin($val, array $opt=array())
 * @method static Collection<UserModel>|null findByGroups($val, array $opt=array())
 * @method static Collection<UserModel>|null findByInherit($val, array $opt=array())
 * @method static Collection<UserModel>|null findByModules($val, array $opt=array())
 * @method static Collection<UserModel>|null findByThemes($val, array $opt=array())
 * @method static Collection<UserModel>|null findByElements($val, array $opt=array())
 * @method static Collection<UserModel>|null findByFields($val, array $opt=array())
 * @method static Collection<UserModel>|null findByPagemounts($val, array $opt=array())
 * @method static Collection<UserModel>|null findByAlpty($val, array $opt=array())
 * @method static Collection<UserModel>|null findByFilemounts($val, array $opt=array())
 * @method static Collection<UserModel>|null findByFop($val, array $opt=array())
 * @method static Collection<UserModel>|null findByImageSizes($val, array $opt=array())
 * @method static Collection<UserModel>|null findByForms($val, array $opt=array())
 * @method static Collection<UserModel>|null findByFormp($val, array $opt=array())
 * @method static Collection<UserModel>|null findByAmg($val, array $opt=array())
 * @method static Collection<UserModel>|null findByDisable($val, array $opt=array())
 * @method static Collection<UserModel>|null findByStart($val, array $opt=array())
 * @method static Collection<UserModel>|null findByStop($val, array $opt=array())
 * @method static Collection<UserModel>|null findBySession($val, array $opt=array())
 * @method static Collection<UserModel>|null findByDateAdded($val, array $opt=array())
 * @method static Collection<UserModel>|null findBySecret($val, array $opt=array())
 * @method static Collection<UserModel>|null findByUseTwoFactor($val, array $opt=array())
 * @method static Collection<UserModel>|null findByLastLogin($val, array $opt=array())
 * @method static Collection<UserModel>|null findByCurrentLogin($val, array $opt=array())
 * @method static Collection<UserModel>|null findByBackupCodes($val, array $opt=array())
 * @method static Collection<UserModel>|null findByTrustedTokenVersion($val, array $opt=array())
 * @method static Collection<UserModel>|null findMultipleByIds($val, array $opt=array())
 * @method static Collection<UserModel>|null findBy($col, $val, array $opt=array())
 * @method static Collection<UserModel>|null findAll(array $opt=array())
 *
 * @method static integer countById($id, array $opt=array())
 * @method static integer countByTstamp($val, array $opt=array())
 * @method static integer countByUsername($val, array $opt=array())
 * @method static integer countByName($val, array $opt=array())
 * @method static integer countByEmail($val, array $opt=array())
 * @method static integer countByLanguage($val, array $opt=array())
 * @method static integer countByBackendTheme($val, array $opt=array())
 * @method static integer countByFullscreen($val, array $opt=array())
 * @method static integer countByUploader($val, array $opt=array())
 * @method static integer countByShowHelp($val, array $opt=array())
 * @method static integer countByThumbnails($val, array $opt=array())
 * @method static integer countByUseRTE($val, array $opt=array())
 * @method static integer countByUseCE($val, array $opt=array())
 * @method static integer countByDoNotCollapse($val, array $opt=array())
 * @method static integer countByDoNotHideMessages($val, array $opt=array())
 * @method static integer countByPassword($val, array $opt=array())
 * @method static integer countByPwChange($val, array $opt=array())
 * @method static integer countByAdmin($val, array $opt=array())
 * @method static integer countByGroups($val, array $opt=array())
 * @method static integer countByInherit($val, array $opt=array())
 * @method static integer countByModules($val, array $opt=array())
 * @method static integer countByThemes($val, array $opt=array())
 * @method static integer countByElements($val, array $opt=array())
 * @method static integer countByFields($val, array $opt=array())
 * @method static integer countByPagemounts($val, array $opt=array())
 * @method static integer countByAlpty($val, array $opt=array())
 * @method static integer countByFilemounts($val, array $opt=array())
 * @method static integer countByFop($val, array $opt=array())
 * @method static integer countByImageSizes($val, array $opt=array())
 * @method static integer countByForms($val, array $opt=array())
 * @method static integer countByFormp($val, array $opt=array())
 * @method static integer countByAmg($val, array $opt=array())
 * @method static integer countByDisable($val, array $opt=array())
 * @method static integer countByStart($val, array $opt=array())
 * @method static integer countByStop($val, array $opt=array())
 * @method static integer countBySession($val, array $opt=array())
 * @method static integer countByDateAdded($val, array $opt=array())
 * @method static integer countBySecret($val, array $opt=array())
 * @method static integer countByUseTwoFactor($val, array $opt=array())
 * @method static integer countByLastLogin($val, array $opt=array())
 * @method static integer countByCurrentLogin($val, array $opt=array())
 * @method static integer countByBackupCodes($val, array $opt=array())
 * @method static integer countByTrustedTokenVersion($val, array $opt=array())
 */
class UserModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_user';
}
