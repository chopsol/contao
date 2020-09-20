<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Security\Exception\LockedException;
use Patchwork\Utf8;
use Scheb\TwoFactorBundle\Security\Authentication\Exception\InvalidTwoFactorCodeException;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Front end module "login".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleLogin extends Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_login';

	/**
	 * Flash type
	 * @var string
	 */
	protected $strFlashType = 'contao.FE.error';

	/**
	 * @var string
	 */
	private $targetPath = '';

	/**
	 * Display a login form
	 *
	 * @return string
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['login'][0]) . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		$container = System::getContainer();
		$request = $container->get('request_stack')->getCurrentRequest();

		// If the form was submitted and the credentials were wrong, take the target
		// path from the submitted data as otherwise it would take the current page
		if ($request->isMethod('POST'))
		{
			$this->targetPath = base64_decode($request->request->get('_target_path'));
		}
		elseif ($this->redirectBack && $request && $request->query->has('redirect'))
		{
			/** @var UriSigner $uriSigner */
			$uriSigner = $container->get('uri_signer');

			// We cannot use $request->getUri() here as we want to work with the original URI (no query string reordering)
			if ($uriSigner->check($request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo() . (null !== ($qs = $request->server->get('QUERY_STRING')) ? '?' . $qs : '')))
			{
				$this->targetPath = $request->query->get('redirect');
			}
		}

		return parent::generate();
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		/** @var PageModel $objPage */
		global $objPage;

		$container = System::getContainer();

		/** @var AuthenticationException|null $exception */
		$exception = $container->get('security.authentication_utils')->getLastAuthenticationError();
		$authorizationChecker = $container->get('security.authorization_checker');

		if ($authorizationChecker->isGranted('ROLE_MEMBER'))
		{
			$this->import(FrontendUser::class, 'User');

			$strRedirect = Environment::get('base') . Environment::get('request');

			// Redirect to last page visited
			if ($this->redirectBack && $this->targetPath)
			{
				$strRedirect = $this->targetPath;
			}

			// Redirect home if the page is protected
			elseif ($objPage->protected)
			{
				$strRedirect = Environment::get('base');
			}

			$this->Template->logout = true;
			$this->Template->formId = 'tl_logout_' . $this->id;
			$this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['logout']);
			$this->Template->loggedInAs = sprintf($GLOBALS['TL_LANG']['MSC']['loggedInAs'], $this->User->username);
			$this->Template->action = $container->get('security.logout_url_generator')->getLogoutPath();
			$this->Template->targetPath = StringUtil::specialchars($strRedirect);

			if ($this->User->lastLogin > 0)
			{
				$this->Template->lastLogin = sprintf($GLOBALS['TL_LANG']['MSC']['lastLogin'][1], Date::parse($objPage->datimFormat, $this->User->lastLogin));
			}

			return;
		}

		if ($exception instanceof LockedException)
		{
			$this->Template->hasError = true;
			$this->Template->message = sprintf($GLOBALS['TL_LANG']['ERR']['accountLocked'], $exception->getLockedMinutes());
		}
		elseif ($exception instanceof InvalidTwoFactorCodeException)
		{
			$this->Template->hasError = true;
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['invalidTwoFactor'];
		}
		elseif ($exception instanceof AuthenticationException)
		{
			$this->Template->hasError = true;
			$this->Template->message = $GLOBALS['TL_LANG']['ERR']['invalidLogin'];
		}

		$blnRedirectBack = false;
		$strRedirect = Environment::get('base') . Environment::get('request');

		// Redirect to the last page visited
		if ($this->redirectBack && $this->targetPath)
		{
			$blnRedirectBack = true;
			$strRedirect = $this->targetPath;
		}

		// Redirect to the jumpTo page
		elseif (($objTarget = $this->objModel->getRelated('jumpTo')) instanceof PageModel)
		{
			/** @var PageModel $objTarget */
			$strRedirect = $objTarget->getAbsoluteUrl();
		}

		$this->Template->formId = 'tl_login_' . $this->id;
		$this->Template->forceTargetPath = (int) $blnRedirectBack;
		$this->Template->targetPath = StringUtil::specialchars(base64_encode($strRedirect));

		if ($authorizationChecker->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS'))
		{
			// Dispatch 2FA form event to prepare 2FA providers
			$request = $container->get('request_stack')->getCurrentRequest();
			$token = $container->get('security.token_storage')->getToken();
			$event = new TwoFactorAuthenticationEvent($request, $token);
			$container->get('event_dispatcher')->dispatch($event, TwoFactorAuthenticationEvents::FORM);

			$this->Template->twoFactorEnabled = true;
			$this->Template->authCode = $GLOBALS['TL_LANG']['MSC']['twoFactorVerification'];
			$this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
			$this->Template->cancel = $GLOBALS['TL_LANG']['MSC']['cancelBT'];
			$this->Template->twoFactorAuthentication = $GLOBALS['TL_LANG']['MSC']['twoFactorAuthentication'];

			return;
		}

		$this->Template->username = $GLOBALS['TL_LANG']['MSC']['username'];
		$this->Template->password = $GLOBALS['TL_LANG']['MSC']['password'][0];
		$this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['login']);
		$this->Template->value = StringUtil::specialchars($container->get('security.authentication_utils')->getLastUsername());
		$this->Template->autologin = $this->autologin;
		$this->Template->autoLabel = $GLOBALS['TL_LANG']['MSC']['autologin'];
	}
}

class_alias(ModuleLogin::class, 'ModuleLogin');
