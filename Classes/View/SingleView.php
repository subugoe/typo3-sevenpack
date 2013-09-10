<?php
namespace Ipf\Bib\View;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Ingo Pfennigstorf <pfennigstorf@sub-goettingen.de>
 *      Goettingen State Library
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

class SingleView {

	/**
	 * @var \tx_bib_pi1
	 */
	public $pi1;

	/**
	 * @var array
	 */
	public $conf;

	/**
	 * @var \Ipf\Bib\Utility\ReferenceReader
	 */
	public $referenceReader;

	/**
	 * @var \Ipf\Bib\Utility\DbUtility
	 */
	public $databaseUtility;

	/**
	 * @var string
	 */
	public $LLPrefix = 'editor_';

	/**
	 * @var bool
	 */
	public $idGenerator = FALSE;

	/**
	 * @var bool
	 */
	public $isNew = FALSE;

	/**
	 * @var bool
	 */
	public $isNewFirst = FALSE;


	/**
	 * Initializes this class
	 *
	 * @param tx_bib_pi1
	 * @return void
	 */
	public function initialize($pi1) {
		$this->pi1 =& $pi1;
		$this->conf =& $pi1->conf['single_view.'];
		$this->referenceReader =& $pi1->referenceReader;
		// Load editor language data
		$this->pi1->extend_ll('EXT:' . $this->pi1->extKey . '/Resources/Private/Language/locallang_editor.xml');
	}


	/**
	 * Returns the single view
	 *
	 * @return string
	 */
	public function singleView() {
		$pi1 =& $this->pi1;
		$content = '';

		$uid = intval($pi1->extConf['single_view']['uid']);
		$ref = $this->referenceReader->getPublicationDetails($uid);

		if (is_array($ref)) {
			try {
				$content .= $this->typeReference($ref);
			} catch (\Exception $e) {
				$content .= $e->getMessage();
			}
		} else {
			$content .= '<p>';
			$content .= 'No publication with uid ' . $uid;
			$content .= '</p>' . "\n";
		}

		$content .= '<p>';
		$content .= $pi1->get_link($pi1->get_ll('link_back_to_list'));
		$content .= '</p>' . "\n";

		// remove multiple line breaks
		$content = preg_replace("/\n+/", "\n", $content);

		return $content;
	}

	/**
	 *
	 * @throws \Exception
	 * @param $ref
	 * @return string
	 */
	protected function typeReference($ref) {
		$pi1 =& $this->pi1;
		$conf =& $this->conf;
		$cObj =& $pi1->cObj;

		$warnings = array();

		$templateFile = $conf['template'];
		$template = $cObj->fileResource($templateFile);
		if (strlen($template) == 0) {
			throw new \Exception('The HTML single view template file \'' . $templateFile . '\' is not readable or empty', 1378818667);
		}

		$template = $cObj->getSubpart($template, '##SINGLE_VIEW###');

		// Store the cObj Data for later recovery
		$contentObjectBackup = $cObj->data;

		// Prepare the publication data and environment
		$pi1->prepareItemSetup();
		$pdata = $pi1->prepare_pub_display($ref, $warnings, true);
		$pi1->prepare_pub_cObj_data($pdata);

		$bib_str = $pdata['bibtype_short'];

		// The translator array
		$translator = array();

		// The filed list
		$fields = $this->referenceReader->pubAllFields;
		$dont_show = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $conf['dont_show'], TRUE);

		// Remove condition fields and setup the translator
		foreach ($fields as $field) {
			$field_up = strtoupper($field);

			// "Has field" conditions
			$has_str = '';
			if ((strlen($pdata[$field]) > 0)) {
				if (!in_array($field, $dont_show)) {
					$has_str = array('', '');
					$label = $this->getFieldLabel($field, $bib_str);
					$label = $pi1->cObj->stdWrap($label, $this->conf['all_labels.']);

					$value = strval($pdata[$field]);
					$stdWrap = $pi1->conf['field.'][$field . '.'];

					if (isset ($pi1->conf['field.'][$bib_str . '.'][$field . '.'])) {
						$stdWrap = $pi1->conf['field.'][$bib_str . '.'][$field . '.'];
					}

					if (isset ($this->conf['field_wrap.'][$field . '.'])) {
						$stdWrap = $this->conf['field_wrap.'][$field . '.'];
					}

					if (isset ($stdWrap['single_view_link'])) {
						$value = $pi1->get_link(
							$value,
							array(
								'show_uid' => strval($pdata['uid']
								)
							)
						);
					}

					$value = $cObj->stdWrap($value, $stdWrap);


					$translator['###' . $field_up . '###'] = $value;
					$translator['###FL_' . $field_up . '###'] = $label;
				}
			}
			$template = $cObj->substituteSubpart($template, '###HAS_' . $field_up . '###', $has_str);
		}

		// Insert field data
		$template = $cObj->substituteMarkerArrayCached($template, $translator);

		// Single view title
		$title = $pi1->get_ll('single_view_title');
		$title = $pi1->cObj->stdWrap($title, $this->conf['title.']);
		$template = $cObj->substituteMarker($template, '###SINGLE_VIEW_TITLE###', $title);

		// Pre and post text
		$txt = strval($this->conf['pre_text']);
		$txt = $pi1->cObj->stdWrap($txt, $this->conf['pre_text.']);
		$template = $cObj->substituteMarker($template, '###PRE_TEXT###', $txt);

		$txt = strval($this->conf['post_text']);
		$txt = $pi1->cObj->stdWrap($txt, $this->conf['post_text.']);
		$template = $cObj->substituteMarker($template, '###POST_TEXT###', $txt);


		// Restore cObj data
		$pi1->cObj->data = $contentObjectBackup;

		return $template;
	}


	/**
	 * Depending on the bibliography type this function returns
	 * The label for a field
	 *
	 * @param string $field The field
	 * @param string $bib_str The bibtype identifier string
	 * @return string
	 */
	protected function getFieldLabel($field, $bib_str) {
		$pi1 =& $this->pi1;
		$label = $this->referenceReader->getReferenceTable() . '_' . $field;

		switch ($field) {
			case 'authors':
				$label = $this->referenceReader->getAuthorTable() . '_' . $field;
				break;
		}

		$over = array(
			$pi1->conf['editor.']['olabel.']['all.'][$field],
			$pi1->conf['editor.']['olabel.'][$bib_str . '.'][$field]
		);

		foreach ($over as $lvar) {
			if (is_string($lvar)) {
				$label = $lvar;
			}
		}

		$label = trim($label);
		if (strlen($label) > 0) {
			$label = $pi1->get_ll($label, $label, TRUE);
		}

		return $label;
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/bib/Classes/View/SingleView.php"]) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/bib/Classes/View/SingleView.php"]);
}

?>