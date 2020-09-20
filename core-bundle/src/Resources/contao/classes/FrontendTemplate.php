<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use FOS\HttpCache\ResponseTagger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FrontendTemplate
 *
 * @property integer $id
 * @property string  $keywords
 * @property string  $content
 * @property array   $sections
 * @property array   $positions
 * @property array   $matches
 * @property string  $tag
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class FrontendTemplate extends Template
{
	/**
	 * Unsued $_GET check
	 * @var boolean
	 */
	protected $blnCheckRequest = false;

	/**
	 * Add a hook to modify the template output
	 *
	 * @return string The template markup
	 */
	public function parse()
	{
		$strBuffer = parent::parse();

		// HOOK: add custom parse filters
		if (isset($GLOBALS['TL_HOOKS']['parseFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['parseFrontendTemplate']))
		{
			foreach ($GLOBALS['TL_HOOKS']['parseFrontendTemplate'] as $callback)
			{
				$this->import($callback[0]);
				$strBuffer = $this->{$callback[0]}->{$callback[1]}($strBuffer, $this->strTemplate);
			}
		}

		return $strBuffer;
	}

	/**
	 * Send the response to the client
	 *
	 * @param bool $blnCheckRequest If true, check for unused $_GET parameters
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use FrontendTemplate::getResponse() instead.
	 */
	public function output($blnCheckRequest=false)
	{
		$this->blnCheckRequest = $blnCheckRequest;

		parent::output();
	}

	/**
	 * Return a response object
	 *
	 * @param bool $blnCheckRequest      If true, check for unused $_GET parameters
	 * @param bool $blnForceCacheHeaders
	 *
	 * @return Response The response object
	 */
	public function getResponse($blnCheckRequest=false, $blnForceCacheHeaders=false)
	{
		$this->blnCheckRequest = $blnCheckRequest;

		$response = parent::getResponse();

		if ($blnForceCacheHeaders || 0 === strncmp('fe_', $this->strTemplate, 3))
		{
			return $this->setCacheHeaders($response);
		}

		return $response;
	}

	/**
	 * Compile the template
	 *
	 * @throws \UnusedArgumentsException If there are unused $_GET parameters
	 *
	 * @internal Do not call this method in your code. It will be made private in Contao 5.0.
	 */
	protected function compile()
	{
		$this->keywords = '';
		$arrKeywords = StringUtil::trimsplit(',', $GLOBALS['TL_KEYWORDS']);

		// Add the meta keywords
		if (isset($arrKeywords[0]))
		{
			$this->keywords = str_replace(array("\n", "\r", '"'), array(' ', '', ''), implode(', ', array_unique($arrKeywords)));
		}

		// Parse the template
		$this->strBuffer = $this->parse();

		// HOOK: add custom output filters
		if (isset($GLOBALS['TL_HOOKS']['outputFrontendTemplate']) && \is_array($GLOBALS['TL_HOOKS']['outputFrontendTemplate']))
		{
			foreach ($GLOBALS['TL_HOOKS']['outputFrontendTemplate'] as $callback)
			{
				$this->import($callback[0]);
				$this->strBuffer = $this->{$callback[0]}->{$callback[1]}($this->strBuffer, $this->strTemplate);
			}
		}

		// Replace insert tags
		$this->strBuffer = $this->replaceInsertTags($this->strBuffer);
		$this->strBuffer = $this->replaceDynamicScriptTags($this->strBuffer); // see #4203

		// HOOK: allow to modify the compiled markup (see #4291)
		if (isset($GLOBALS['TL_HOOKS']['modifyFrontendPage']) && \is_array($GLOBALS['TL_HOOKS']['modifyFrontendPage']))
		{
			foreach ($GLOBALS['TL_HOOKS']['modifyFrontendPage'] as $callback)
			{
				$this->import($callback[0]);
				$this->strBuffer = $this->{$callback[0]}->{$callback[1]}($this->strBuffer, $this->strTemplate);
			}
		}

		// Check whether all $_GET parameters have been used (see #4277)
		if ($this->blnCheckRequest && Input::hasUnusedGet())
		{
			throw new \UnusedArgumentsException('Unused arguments: ' . implode(', ', Input::getUnusedGet()));
		}

		/** @var PageModel $objPage */
		global $objPage;

		// Minify the markup
		if ($objPage->minifyMarkup)
		{
			$this->strBuffer = $this->minifyHtml($this->strBuffer);
		}

		// Replace literal insert tags (see #670)
		$this->strBuffer = str_replace(array('[{]', '[}]'), array('{{', '}}'), $this->strBuffer);

		parent::compile();
	}

	/**
	 * Return a custom layout section
	 *
	 * @param string $key      The section name
	 * @param string $template An optional template name
	 */
	public function section($key, $template=null)
	{
		if (empty($this->sections[$key]))
		{
			return;
		}

		$this->id = $key;
		$this->content = $this->sections[$key];

		if ($template === null)
		{
			foreach ($this->positions as $position)
			{
				if (isset($position[$key]['template']))
				{
					$template = $position[$key]['template'];
				}
			}
		}

		if ($template === null)
		{
			$template = 'block_section';
		}

		include $this->getTemplate($template);
	}

	/**
	 * Return the custom layout sections
	 *
	 * @param string $key      An optional section name
	 * @param string $template An optional template name
	 */
	public function sections($key=null, $template=null)
	{
		if (!array_filter($this->sections))
		{
			return;
		}

		// The key does not match
		if ($key && !isset($this->positions[$key]))
		{
			return;
		}

		$matches = array();

		foreach ($this->positions[$key] as $id=>$section)
		{
			if (!empty($this->sections[$id]))
			{
				if (!isset($section['template']))
				{
					$section['template'] = 'block_section';
				}

				$section['content'] = $this->sections[$id];
				$matches[$id] = $section;
			}
		}

		// Return if the section is empty (see #1115)
		if (empty($matches))
		{
			return;
		}

		$this->matches = $matches;

		if ($template === null)
		{
			$template = 'block_sections';
		}

		include $this->getTemplate($template);
	}

	/**
	 * Point to `Frontend::addToUrl()` in front end templates (see #6736)
	 *
	 * @param string  $strRequest      The request string to be added
	 * @param boolean $blnIgnoreParams If true, the $_GET parameters will be ignored
	 * @param array   $arrUnset        An optional array of keys to unset
	 *
	 * @return string The new URI string
	 */
	public static function addToUrl($strRequest, $blnIgnoreParams=false, $arrUnset=array())
	{
		return Frontend::addToUrl($strRequest, $blnIgnoreParams, $arrUnset);
	}

	/**
	 * Check whether there is an authenticated back end user
	 *
	 * @return boolean True if there is an authenticated back end user
	 */
	public function hasAuthenticatedBackendUser()
	{
		return System::getContainer()->get('contao.security.token_checker')->hasBackendUser();
	}

	/**
	 * Add the template output to the cache and add the cache headers
	 *
	 * @deprecated Deprecated since Contao 4.3, to be removed in Contao 5.0.
	 *             Use proper response caching headers instead.
	 */
	protected function addToCache()
	{
		trigger_deprecation('contao/core-bundle', '4.3', 'Using "Contao\FrontendTemplate::addToCache()" has been deprecated and will no longer work in Contao 5.0. Use proper response caching headers instead.');
	}

	/**
	 * Add the template output to the search index
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use the kernel.terminate event instead.
	 */
	protected function addToSearchIndex()
	{
		trigger_deprecation('contao/core-bundle', '4.0', 'Using "Contao\FrontendTemplate::addToSearchIndex()" has been deprecated and will no longer work in Contao 5.0. Use the "kernel.terminate" event instead.');
	}

	/**
	 * Return a custom layout section
	 *
	 * @param string $strKey The section name
	 *
	 * @return string The section markup
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use FrontendTemplate::section() instead.
	 */
	public function getCustomSection($strKey)
	{
		trigger_deprecation('contao/core-bundle', '4.0', 'Using "Contao\FrontendTemplate::getCustomSection()" has been deprecated and will no longer work in Contao 5.0. Use "Contao\FrontendTemplate::section()" instead.');

		return '<div id="' . $strKey . '">' . $this->sections[$strKey] . '</div>' . "\n";
	}

	/**
	 * Return all custom layout sections
	 *
	 * @param string $strKey An optional section name
	 *
	 * @return string The section markup
	 *
	 * @deprecated Deprecated since Contao 4.0, to be removed in Contao 5.0.
	 *             Use FrontendTemplate::sections() instead.
	 */
	public function getCustomSections($strKey=null)
	{
		trigger_deprecation('contao/core-bundle', '4.0', 'Using "Contao\FrontendTemplate::getCustomSections()" has been deprecated and will no longer work in Contao 5.0. Use "Contao\FrontendTemplate::sections()" instead.');

		if ($strKey != '' && !isset($this->positions[$strKey]))
		{
			return '';
		}

		$tag = 'div';

		// Use the section tag for the main column
		if ($strKey == 'main')
		{
			$tag = 'section';
		}

		$sections = '';

		// Standardize the IDs (thanks to Tsarma) (see #4251)
		foreach ($this->positions[$strKey] as $sect)
		{
			if (isset($this->sections[$sect['id']]))
			{
				$sections .= "\n" . '<' . $tag . ' id="' . StringUtil::standardize($sect['id'], true) . '">' . "\n" . '<div class="inside">' . "\n" . $this->sections[$sect['id']] . "\n" . '</div>' . "\n" . '</' . $tag . '>' . "\n";
			}
		}

		if ($sections == '')
		{
			return '';
		}

		return '<div class="custom">' . "\n" . $sections . "\n" . '</div>' . "\n";
	}

	/**
	 * Set the cache headers according to the page settings.
	 *
	 * @param Response $response The response object
	 *
	 * @return Response The response object
	 */
	private function setCacheHeaders(Response $response)
	{
		/** @var PageModel $objPage */
		global $objPage;

		// Do not cache the response if caching was not configured at all or disabled explicitly
		if (($objPage->cache === false || $objPage->cache < 1) && ($objPage->clientCache === false || $objPage->clientCache < 1))
		{
			$response->headers->set('Cache-Control', 'no-cache, no-store');

			return $response->setPrivate(); // Make sure the response is private
		}

		// Private cache
		if ($objPage->clientCache > 0)
		{
			$response->setMaxAge($objPage->clientCache);
			$response->setPrivate(); // Make sure the response is private
		}

		// Shared cache
		if ($objPage->cache > 0)
		{
			$response->setSharedMaxAge($objPage->cache); // Automatically sets the response to public

			// We vary on cookies if a response is cacheable by the shared
			// cache, so a reverse proxy does not load a response from cache if
			// the _request_ contains a cookie.
			//
			// This DOES NOT mean that we generate a cache entry for every
			// response containing a cookie! Responses with cookies will always
			// be private (@see Contao\CoreBundle\EventListener\MakeResponsePrivateListener).
			//
			// However, we want to be able to force the reverse proxy to load a
			// response from cache, even if the request contains a cookie – in
			// case the admin has configured to do so. A typical use case would
			// be serving public pages from cache to logged in members.
			if (!$objPage->alwaysLoadFromCache)
			{
				$response->setVary(array('Cookie'));
			}

			// Tag the response with cache tags für the shared cache only
			if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger'))
			{
				/** @var ResponseTagger $responseTagger */
				$responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
				$responseTagger->addTags(array('contao.db.tl_page.' . $objPage->id));
			}
		}

		return $response;
	}
}

class_alias(FrontendTemplate::class, 'FrontendTemplate');
