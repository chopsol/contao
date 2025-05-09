<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

/**
 * Provide methods to handle modules of a page layout.
 */
class ModuleWizard extends Widget
{
	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';

	/**
	 * Check if there is a module without a column
	 */
	public function validate()
	{
		$varValue = $this->getPost($this->strName);

		foreach ($varValue as $v)
		{
			if (empty($v['col']))
			{
				$this->addError($GLOBALS['TL_LANG']['ERR']['moduleWithoutColumn']);
			}
		}

		parent::validate();
	}

	/**
	 * Generate the widget and return it as string
	 *
	 * @return string
	 */
	public function generate()
	{
		$db = Database::getInstance();

		// Get all modules of the current theme
		$objModules = $db
			->prepare("SELECT id, name, type FROM tl_module WHERE pid=(SELECT pid FROM " . $this->strTable . " WHERE id=?) ORDER BY name")
			->execute($this->currentRecord);

		// Add the articles module
		$modules[] = array('id'=>0, 'name'=>$GLOBALS['TL_LANG']['MOD']['article'][0], 'type'=>'article');

		if ($objModules->numRows)
		{
			$modules = array_merge($modules, $objModules->fetchAllAssoc());
		}

		// Get all content elements of the current theme
		$elements = $db
			->prepare("SELECT * FROM tl_content WHERE ptable=? AND pid=(SELECT pid FROM " . $this->strTable . " WHERE id=?)")
			->execute('tl_theme', $this->currentRecord)
			->fetchAllAssoc()
		;

		$recordLabeler = System::getContainer()->get('contao.data_container.record_labeler');

		$elements = array_map(
			static function (array $element) use ($recordLabeler) {
				return array(
					'id' => 'content-' . $element['id'],
					'title' => $recordLabeler->getLabel('contao.db.tl_content.' . $element['id'], $element),
					'type' => $GLOBALS['TL_LANG']['CTE'][$element['type']][0] ?? $element['type'],
				);
			},
			$elements
		);

		usort($elements, static function (array $a, array $b) {
			return strcmp($a['title'], $b['title']);
		});

		$GLOBALS['TL_LANG']['FMD']['article'] = $GLOBALS['TL_LANG']['MOD']['article'];

		// Add the module type (see #3835)
		foreach ($modules as $k=>$v)
		{
			if (isset($GLOBALS['TL_LANG']['FMD'][$v['type']][0]))
			{
				$v['type'] = $GLOBALS['TL_LANG']['FMD'][$v['type']][0];
			}

			$modules[$k] = $v;
		}

		// Show slots if they are explicitly defined, otherwise fall back to the legacy logic
		if (null !== ($slots = $this->arrConfiguration['slots'] ?? null))
		{
			$cols = array();

			foreach ($slots as $slot)
			{
				$cols[$slot] = "{% slot $slot %}";
			}
		}
		else
		{
			$objRow = $db
				->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
				->limit(1)
				->execute($this->currentRecord)
			;

			$cols = array('main');

			if (\in_array($objRow->rows, array('2rwh', '3rw')))
			{
				$cols[] = 'header';
			}

			if (\in_array($objRow->cols, array('2cll', '3cl')))
			{
				$cols[] = 'left';
			}

			if (\in_array($objRow->cols, array('2clr', '3cl')))
			{
				$cols[] = 'right';
			}

			if (\in_array($objRow->rows, array('2rwf', '3rw')))
			{
				$cols[] = 'footer';
			}

			$positions = array();

			// Add custom layout sections
			if ($objRow->sections)
			{
				$arrSections = StringUtil::deserialize($objRow->sections);

				if (!empty($arrSections) && \is_array($arrSections))
				{
					foreach ($arrSections as $v)
					{
						if (!empty($v['id']))
						{
							$cols[] = $v['id'];
							$positions[$v['id']] = $v['position'];
						}
					}
				}
			}

			$cols = Backend::convertLayoutSectionIdsToAssociativeArray($cols);
		}

		// Get the new value
		if (Input::post('FORM_SUBMIT') == $this->strTable)
		{
			$this->varValue = Input::post($this->strId);
		}

		// Make sure there is at least an empty array
		if (!\is_array($this->varValue) || !$this->varValue[0])
		{
			$this->varValue = array(array('mod'=>0, 'col'=>'main'));
		}
		else
		{
			// Initialize the sorting order
			$arrCols = array
			(
				'top' => array(),
				'header' => array(),
				'before' => array(),
				'left' => array(),
				'right' => array(),
				'main' => array(),
				'after' => array(),
				'footer' => array(),
				'bottom' => array(),
				'manual' => array()
			);

			foreach ($this->varValue as $v)
			{
				$key = $positions[$v['col']] ?? $v['col'];

				$arrCols[$key][] = $v;
			}

			$this->varValue = array_merge(...array_values($arrCols));
		}

		$rows = array();

		// Compile rows
		foreach ($this->varValue as $value)
		{
			$elementOptions = array();

			foreach ($elements as $v)
			{
				$elementOptions[] = array(
					'value' => self::specialcharsValue($v['id']),
					'label' => $v['title'] . ' [' . $v['type'] . ']',
					'selected' => '' !== static::optionSelected($v['id'], $value['mod'] ?? null),
				);
			}

			$moduleOptions = array();

			foreach ($modules as $v)
			{
				$moduleOptions[] = array(
					'value' => self::specialcharsValue($v['id']),
					'label' => $v['name'] . ' [' . $v['type'] . ']',
					'selected' => '' !== static::optionSelected($v['id'], $value['mod'] ?? null),
				);
			}

			$layoutOptions = array(
				array('value' => '', 'label' => '-', 'selected' => false),
			);

			foreach ($cols as $k => $v)
			{
				$layoutOptions[] = array(
					'value' => self::specialcharsValue($k),
					'label' => $v,
					'selected' => '' !== static::optionSelected($k, $value['col'] ?? null),
				);
			}

			$id = $value['mod'] ?? null;
			$isContentElement = str_starts_with((string) $id, 'content-');
			$id = (int) str_replace('content-', '', $id);

			$rows[] = array(
				'id' => $id,
				'is_content_element' => $isContentElement,
				'element_options' => $elementOptions,
				'module_options' => $moduleOptions,
				'layout_options' => $layoutOptions,
				'controls' => array(
					'edit' => $id > 0,
					'enable' => $value['enable'] ?? false,
				),
			);
		}

		return System::getContainer()->get('twig')->render('@Contao/backend/widget/module_wizard.html.twig', array(
			'id' => $this->strId,
			'rows' => $rows,
		));
	}
}
