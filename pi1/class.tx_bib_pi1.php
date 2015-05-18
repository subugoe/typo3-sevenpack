<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Sebastian Holtermann (sebholt@web.de)
 *  (c) 2013 Ingo Pfennigstorf <i.pfennigstorf@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
 ***************************************************************/

use Ipf\Bib\Utility\Utility;
use Ipf\Bib\Utility\Importer\Importer;
use Ipf\Bib\View\EditorView;
use Ipf\Bib\View\SingleView;
use Ipf\Bib\View\DialogView;
use Ipf\Bib\View\View;
use Ipf\Bib\View\ListView;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plugin 'Publication List' for the 'bib' extension.
 */
class tx_bib_pi1 extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin {

	public $prefixId = 'tx_bib_pi1';
	public $scriptRelPath = 'pi1/class.tx_bib_pi1.php';
	public $extKey = 'bib'; // The extension key.

	// http://forum.typo3.org/index.php/t/152665/
	public $pi_checkCHash = FALSE;

	public $prefixShort = 'tx_bib';
	public $prefix_pi1 = 'tx_bib_pi1';

	// Enumeration for list modes
	const D_SIMPLE = 0;
	const D_Y_SPLIT = 1;
	const D_Y_NAV = 2;

	// Statistic modes
	const STAT_NONE = 0;
	const STAT_TOTAL = 1;
	const STAT_YEAR_TOTAL = 2;

	// citeid generation modes
	const AUTOID_OFF = 0;
	const AUTOID_HALF = 1;
	const AUTOID_FULL = 2;

	// Sorting modes
	const SORT_DESC = 0;
	const SORT_ASC = 1;

	/**
	 * @var string
	 */
	public $template;

	/**
	 * @var string
	 */
	public $itemTemplate;

	/**
	 * These are derived/extra configuration values
	 *
	 * @var array
	 */
	public $extConf;

	/**
	 * The reference database reader
	 *
	 * @var \Ipf\Bib\Utility\ReferenceReader
	 */
	public $referenceReader;

	/**
	 * @var array
	 */
	public $icon_src = [];

	/**
	 * Statistices
	 *
	 * @var array
	 */
	public $stat = [];

	/**
	 * @var array
	 */
	public $labelTranslator = [];

	/**
	 * @var array
	 */
	protected $flexFormData;

	/**
	 * @var array
	 */
	protected $pidList;

	/**
	 * @var array
	 */
	protected $flexForm;

	/**
	 * @var \TYPO3\CMS\Fluid\View\StandaloneView
	 */
	protected $view;

	/**
	 * @var string
	 */
	protected $flexFormFilterSheet;

	/**
	 * The main function merges all configuration options and
	 * switches to the appropriate request handler
	 *
	 * @param string $content
	 * @param array $conf
	 *
	 * @return string The plugin HTML content
	 */
	public function main($content, $conf) {
		$this->conf = $conf;
		$this->extConf = [];
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->extend_ll('EXT:' . $this->extKey . '/Resources/Private/Language/locallang_db.xml');
		$this->pi_initPIflexForm();

		$this->flexFormData = $this->cObj->data['pi_flexform'];

		$this->initializeFluidTemplate();

		$this->includeCss();

		$this->initializeReferenceReader();

		$this->getExtensionConfiguration();

		$this->getTypoScriptConfiguration();

		$this->getCharacterSet();

		$this->getFrontendEditorConfiguration();

		$this->getStoragePid();

		$this->getPidList();

		$this->makeAdjustments();

		$this->setupNavigations();

		// Enable the edit mode
		$validBackendUser = $this->isValidBackendUser();

		// allow FE-user editing from special groups (set via TS)
		$validFrontendUser = $this->isValidFrontendUser($validBackendUser);

		$this->extConf['edit_mode'] = (($validBackendUser || $validFrontendUser) && $this->extConf['editor']['enabled']);

		$this->setEnumerationMode();

		$this->initializeRestrictions();

		$this->initializeListViewIcons();

		$this->initializeFilters();

		$this->showHiddenEntries();

		$this->getEditMode();

		$this->switchToExportView();

		$this->switchToSingleView();

		$this->callSearchNavigationHook();

		$this->setReferenceReaderConfiguration();

		$this->callAuthorNavigationHook();

		$this->getYearNavigation();

		$this->determineNumberOfPublications();

		$this->getPageNavigation();

		$this->getSortFilter();

		$this->disableNavigationOnDemand();

		// Initialize the html templates
		try {
			$this->initializeHtmlTemplate();
		} catch (\Ipf\Bib\Exception\RenderingException $e) {
			return $this->finalize($e->getMessage());
		}

		// Switch to requested view mode
		try {
			return $this->finalize($this->switchToRequestedViewMode());
		} catch (\Exception $e) {
			return $this->finalize($e->getMessage());
		}

	}

	/**
	 * Calls the hook_filter in the author navigation instance
	 *
	 * @return void
	 */
	protected function callAuthorNavigationHook() {
		if ($this->extConf['show_nav_author']) {
			$this->extConf['author_navi']['obj']->hook_filter();
		}
	}

	/**
	 * Calls the hook_filter in the search navigation instance
	 *
	 * @return void
	 */
	protected function callSearchNavigationHook() {
		if ($this->extConf['show_nav_search']) {
			$this->extConf['search_navi']['obj']->hook_filter();
		}
	}

	/**
	 * Disable navigations om demand
	 *
	 * @return void
	 */
	protected function disableNavigationOnDemand() {

		if ($this->stat['num_all'] == 0) {
			$this->extConf['show_nav_export'] = FALSE;
		}

		if ($this->stat['num_page'] == 0) {
			$this->extConf['show_nav_stat'] = FALSE;
		}
	}

	/**
	 * Initialize a ReferenceReader instance and pass it to the class variable
	 * @return void
	 */
	protected function initializeReferenceReader() {
		/** @var \Ipf\Bib\Utility\ReferenceReader $referenceReader */
		$referenceReader = GeneralUtility::makeInstance(\Ipf\Bib\Utility\ReferenceReader::class);
		$referenceReader->set_cObj($this->cObj);
		$this->referenceReader = $referenceReader;
	}

	/**
	 * @return void
	 */
	protected function initializeFluidTemplate() {
		/** @var \TYPO3\CMS\Fluid\View\StandaloneView $view */
		$view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
		$view->setTemplatePathAndFilename(ExtensionManagementUtility::extPath($this->extKey) . '/Resources/Private/Templates/');
		$this->view = $view;
	}

	/**
	 * Determines and applies sorting filters to the ReferenceReader
	 *
	 * @return void
	 */
	protected function getSortFilter() {
		$this->extConf['filters']['sort'] = [];
		$this->extConf['filters']['sort']['sorting'] = [];

		// Default sorting
		$defaultSorting = 'DESC';

		if ($this->extConf['date_sorting'] == self::SORT_ASC) {
			$defaultSorting = 'ASC';
		}

		// add custom sorting with values from flexform
		if (!empty($this->extConf['sorting'])) {
			$sortFields = GeneralUtility::trimExplode(',', $this->extConf['sorting']);
			foreach ($sortFields as $sortField) {

				if ($sortField == 'surname') {
					$sort = ['field' => $this->referenceReader->getAuthorTable() . '.' . $sortField . ' ', 'dir' => 'ASC'];
				} else {
					$sort = ['field' => $this->referenceReader->getReferenceTable() . '.' . $sortField . ' ', 'dir' => $defaultSorting];
				}
				$this->extConf['filters']['sort']['sorting'][] = $sort;
			}
		} else {
			// pre-defined sorting
			$this->extConf['filters']['sort']['sorting'] = [
					['field' => $this->referenceReader->getReferenceTable() . '.year', 'dir' => $defaultSorting],
					['field' => $this->referenceReader->getReferenceTable() . '.month', 'dir' => $defaultSorting],
					['field' => $this->referenceReader->getReferenceTable() . '.day', 'dir' => $defaultSorting],
					['field' => $this->referenceReader->getReferenceTable() . '.bibtype', 'dir' => 'ASC'],
					['field' => $this->referenceReader->getReferenceTable() . '.state', 'dir' => 'ASC'],
					['field' => $this->referenceReader->getReferenceTable() . '.sorting', 'dir' => 'ASC'],
					['field' => $this->referenceReader->getReferenceTable() . '.title', 'dir' => 'ASC']
			];
		}
		// Adjust sorting for bibtype split
		if ($this->extConf['split_bibtypes']) {
			if ($this->extConf['d_mode'] == self::D_SIMPLE) {
				$this->extConf['filters']['sort']['sorting'] = [
						['field' => $this->referenceReader->getReferenceTable() . '.bibtype', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.year', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.month', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.day', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.state', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.sorting', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.title', 'dir' => 'ASC']
				];
			} else {
				$this->extConf['filters']['sort']['sorting'] = [
						['field' => $this->referenceReader->getReferenceTable() . '.year', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.bibtype', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.month', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.day', 'dir' => $defaultSorting],
						['field' => $this->referenceReader->getReferenceTable() . '.state', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.sorting', 'dir' => 'ASC'],
						['field' => $this->referenceReader->getReferenceTable() . '.title', 'dir' => 'ASC']
				];
			}
		}
		$this->referenceReader->set_filters($this->extConf['filters']);
	}

	/**
	 * Determines the number of publications
	 *
	 * @return void
	 */
	protected function determineNumberOfPublications() {
		if (!is_numeric($this->stat['num_all'])) {
			$this->stat['num_all'] = $this->referenceReader->getNumberOfPublications();
			$this->stat['num_page'] = $this->stat['num_all'];
		}
	}

	/**
	 * Switch to single view on demand
	 *
	 * @return void
	 */
	protected function switchToSingleView() {
		if (is_numeric($this->piVars['show_uid'])) {
			$this->extConf['view_mode'] = View::VIEW_SINGLE;
			$this->extConf['single_view']['uid'] = intval($this->piVars['show_uid']);
			unset ($this->piVars['editor_mode']);
			unset ($this->piVars['dialog_mode']);
		}
	}

	/**
	 * Switch to export mode on demand
	 *
	 * @return void
	 */
	protected function switchToExportView() {
		if (is_string($this->extConf['export_navi']['do'])) {
			$this->extConf['view_mode'] = View::VIEW_DIALOG;
			$this->extConf['dialog_mode'] = DialogView::DIALOG_EXPORT;
		}
	}

	/**
	 * Determines whether hidden entries are displayed or not
	 *
	 * @return void
	 */
	protected function showHiddenEntries() {
		$this->extConf['show_hidden'] = FALSE;
		if ($this->extConf['edit_mode']) {
			$this->extConf['show_hidden'] = TRUE;
		}
		$this->referenceReader->setShowHidden($this->extConf['show_hidden']);
	}

	/**
	 * Set the enumeration mode
	 * @return void
	 */
	protected function setEnumerationMode() {
		$this->extConf['has_enum'] = TRUE;
		if (($this->extConf['enum_style'] == ListView::ENUM_EMPTY)) {
			$this->extConf['has_enum'] = FALSE;
		}
	}

	/**
	 * Retrieves and optimizes the pid list and passes it to the referenceReader
	 *
	 * @return void
	 */
	protected function getPidList() {
		$pidList = array_unique($this->pidList);
		if (in_array(0, $pidList)) {
			unset ($pidList[array_search(0, $pidList)]);
		}

		if (sizeof($pidList) > 0) {
			// Determine the recursive depth
			$this->extConf['recursive'] = $this->cObj->data['recursive'];
			if (isset ($this->conf['recursive'])) {
				$this->extConf['recursive'] = $this->conf['recursive'];
			}
			$this->extConf['recursive'] = intval($this->extConf['recursive']);

			$pidList = $this->pi_getPidList(implode(',', $pidList), $this->extConf['recursive']);

			$pidList = GeneralUtility::intExplode(',', $pidList);

			// Due to how recursive prepends the folders
			$pidList = array_reverse($pidList);

			$this->extConf['pid_list'] = $pidList;
		} else {
			// Use current page as storage
			$this->extConf['pid_list'] = [intval($GLOBALS['TSFE']->id)];
		}
		$this->referenceReader->setPidList($this->extConf['pid_list']);
	}

	/**
	 * Get the character set and write it to the configuration
	 *
	 * @return void
	 */
	protected function getCharacterSet() {
		$this->extConf['charset'] = ['upper' => 'UTF-8', 'lower' => 'utf-8'];
		if (strlen($this->conf['charset']) > 0) {
			$this->extConf['charset']['upper'] = strtoupper($this->conf['charset']);
			$this->extConf['charset']['lower'] = strtolower($this->conf['charset']);
		}
	}

	/**
	 * @return void
	 */
	protected function getTypoScriptConfiguration() {

		if (intval($this->extConf['d_mode']) < 0) {
			$this->extConf['d_mode'] = intval($this->conf['display_mode']);
		}

		if (intval($this->extConf['enum_style']) < 0) {
			$this->extConf['enum_style'] = intval($this->conf['enum_style']);
		}

		if (intval($this->extConf['date_sorting']) < 0) {
			$this->extConf['date_sorting'] = intval($this->conf['date_sorting']);
		}

		if (intval($this->extConf['stat_mode']) < 0) {
			$this->extConf['stat_mode'] = intval($this->conf['statNav.']['mode']);
		}

		if (intval($this->extConf['sub_page']['ipp']) < 0) {
			$this->extConf['sub_page']['ipp'] = intval($this->conf['items_per_page']);
		}

		if (intval($this->extConf['max_authors']) < 0) {
			$this->extConf['max_authors'] = intval($this->conf['max_authors']);
		}
	}

	/**
	 * Determine the requested view mode (List, Single, Editor, Dialog)
	 *
	 * @throws \Exception
	 * @return string
	 */
	protected function switchToRequestedViewMode() {

		switch ($this->extConf['view_mode']) {
			case View::VIEW_LIST :
				return $this->listView();
				break;
			case View::VIEW_SINGLE :
				return $this->singleView();
				break;
			case View::VIEW_EDITOR :
				return $this->editorView();
				break;
			case View::VIEW_DIALOG :
				return $this->dialogView();
				break;
			default:
				throw new \Exception('An illegal view mode occurred', 1379064350);
		}
	}

	/**
	 * Setup and initialize Navigation types
	 *
	 * Search Navigation
	 * Year Navigation
	 * Author Navigation
	 * Preference Navigation
	 * Statistic Navigation
	 * Export Navigation
	 *
	 * @return void
	 */
	protected function setupNavigations() {
		// Search Navigation
		if ($this->extConf['show_nav_search']) {
			$this->initializeSearchNavigation();
		}

		// Year Navigation
		if ($this->extConf['d_mode'] == self::D_Y_NAV) {
			$this->enableYearNavigation();
		}

		// Author Navigation
		if ($this->extConf['show_nav_author']) {
			$this->initializeAuthorNavigation();
		}

		// Preference Navigation
		if ($this->extConf['show_nav_pref']) {
			$this->initializePreferenceNavigation();
		}

		// Statistic Navigation
		if (intval($this->extConf['stat_mode']) != self::STAT_NONE) {
			$this->enableStatisticsNavigation();
		}

		// Export navigation
		if ($this->extConf['show_nav_export']) {
			$this->getExportNavigation();
		}
	}

	/**
	 * Make adjustments to different modes
	 * @todo find a better method name or split up
	 *
	 * @return void
	 */
	protected function makeAdjustments() {
		switch ($this->extConf['d_mode']) {
			case self::D_SIMPLE:
			case self::D_Y_SPLIT:
			case self::D_Y_NAV:
				break;
			default:
				$this->extConf['d_mode'] = self::D_SIMPLE;
		}
		switch ($this->extConf['enum_style']) {
			case ListView::ENUM_PAGE:
			case ListView::ENUM_ALL:
			case ListView::ENUM_BULLET:
			case ListView::ENUM_EMPTY:
			case ListView::ENUM_FILE_ICON:
				break;
			default:
				$this->extConf['enum_style'] = ListView::ENUM_ALL;
		}
		switch ($this->extConf['date_sorting']) {
			case self::SORT_DESC:
			case self::SORT_ASC:
				break;
			default:
				$this->extConf['date_sorting'] = self::SORT_DESC;
		}
		switch ($this->extConf['stat_mode']) {
			case self::STAT_NONE:
			case self::STAT_TOTAL:
			case self::STAT_YEAR_TOTAL:
				break;
			default:
				$this->extConf['stat_mode'] = self::STAT_TOTAL;
		}
		$this->extConf['sub_page']['ipp'] = max(intval($this->extConf['sub_page']['ipp']), 0);
		$this->extConf['max_authors'] = max(intval($this->extConf['max_authors']), 0);
	}

	/**
	 * This is the last function called before ouptput
	 *
	 * @param string $pluginContent
	 * @return string The input string with some extra data
	 */
	protected function finalize($pluginContent) {
		if ($this->extConf['debug']) {
			$pluginContent .= \TYPO3\CMS\Core\Utility\DebugUtility::viewArray(
					[
							'extConf' => $this->extConf,
							'conf' => $this->conf,
							'piVars' => $this->piVars,
							'stat' => $this->stat,
							'HTTP_POST_VARS' => $GLOBALS['HTTP_POST_VARS'],
							'HTTP_GET_VARS' => $GLOBALS['HTTP_GET_VARS'],
					]
			);
		}
		return $this->pi_wrapInBaseClass($pluginContent);
	}

	protected function includeCss() {
		/** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
		$pageRenderer = $GLOBALS['TSFE']->getPageRenderer();
		$pageRenderer->addCssFile(ExtensionManagementUtility::siteRelPath('bib') . '/Resources/Public/Css/bib.css') ;
	}

	/**
	 * @return void
	 */
	protected function enableYearNavigation() {
		$this->extConf['show_nav_year'] = TRUE;
	}

	/**
	 * @return void
	 */
	protected function enableStatisticsNavigation() {
		$this->extConf['show_nav_stat'] = TRUE;
	}

	/**
	 * @return void
	 */
	protected function initializePreferenceNavigation() {
		$this->extConf['pref_navi'] = [];
		$this->extConf['pref_navi']['obj'] = Utility::getAndInitializeNavigationInstance('PreferenceNavigation', $this);
		$this->extConf['pref_navi']['obj']->hook_init();
	}

	/**
	 * @return void
	 */
	protected function initializeAuthorNavigation() {
		$this->extConf['dynamic'] = TRUE;
		$this->extConf['author_navi'] = [];
		$this->extConf['author_navi']['obj'] = Utility::getAndInitializeNavigationInstance('AuthorNavigation', $this);
		$this->extConf['author_navi']['obj']->hook_init();
	}

	/**
	 * @return void
	 */
	protected function getEditMode() {
		if ($this->extConf['edit_mode']) {

			// Disable caching in edit mode
			$GLOBALS['TSFE']->set_no_cache();

			// Load edit labels
			$this->extend_ll('EXT:' . $this->extKey . '/Resources/Private/Language/locallang.xml');

			// Do an action type evaluation
			if (is_array($this->piVars['action'])) {
				$actionName = implode('', array_keys($this->piVars['action']));

				switch ($actionName) {
					case 'new':
						$this->extConf['view_mode'] = View::VIEW_EDITOR;
						$this->extConf['editor_mode'] = EditorView::EDIT_NEW;
						break;
					case 'edit':
						$this->extConf['view_mode'] = View::VIEW_EDITOR;
						$this->extConf['editor_mode'] = EditorView::EDIT_EDIT;
						break;
					case 'confirm_save':
						$this->extConf['view_mode'] = View::VIEW_EDITOR;
						$this->extConf['editor_mode'] = EditorView::EDIT_CONFIRM_SAVE;
						break;
					case 'save':
						$this->extConf['view_mode'] = View::VIEW_DIALOG;
						$this->extConf['dialog_mode'] = DialogView::DIALOG_SAVE_CONFIRMED;
						break;
					case 'confirm_delete':
						$this->extConf['view_mode'] = View::VIEW_EDITOR;
						$this->extConf['editor_mode'] = EditorView::EDIT_CONFIRM_DELETE;
						break;
					case 'delete':
						$this->extConf['view_mode'] = View::VIEW_DIALOG;
						$this->extConf['dialog_mode'] = DialogView::DIALOG_DELETE_CONFIRMED;
						break;
					case 'confirm_erase':
						$this->extConf['view_mode'] = View::VIEW_EDITOR;
						$this->extConf['editor_mode'] = EditorView::EDIT_CONFIRM_ERASE;
						break;
					case 'erase':
						$this->extConf['view_mode'] = View::VIEW_DIALOG;
						$this->extConf['dialog_mode'] = DialogView::DIALOG_ERASE_CONFIRMED;
						break;
					case 'hide':
						$this->hidePublication(TRUE);
						break;
					case 'reveal':
						$this->hidePublication(FALSE);
						break;
					default:
				}
			}

			// Set unset extConf and piVars editor mode
			if ($this->extConf['view_mode'] == View::VIEW_DIALOG) {
				unset ($this->piVars['editor_mode']);
			}

			if (isset ($this->extConf['editor_mode'])) {
				$this->piVars['editor_mode'] = $this->extConf['editor_mode'];
			} else if (isset ($this->piVars['editor_mode'])) {
				$this->extConf['view_mode'] = View::VIEW_EDITOR;
				$this->extConf['editor_mode'] = $this->piVars['editor_mode'];
			}

			// Initialize edit icons
			$this->initializeEditIcons();

			// Switch to an import view on demand
			$allImport = intval(Importer::IMP_BIBTEX | Importer::IMP_XML);
			if (isset($this->piVars['import']) && (intval($this->piVars['import']) & $allImport)) {
				$this->extConf['view_mode'] = View::VIEW_DIALOG;
				$this->extConf['dialog_mode'] = DialogView::DIALOG_IMPORT;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function getYearNavigation() {
		if ($this->extConf['show_nav_year']) {

			// Fetch a year histogram
			$histogram = $this->referenceReader->getHistogram('year');
			$this->stat['year_hist'] = $histogram;
			$this->stat['years'] = array_keys($histogram);
			sort($this->stat['years']);

			$this->stat['num_all'] = array_sum($histogram);
			$this->stat['num_page'] = $this->stat['num_all'];

			// Determine the year to display
			$this->extConf['year'] = intval(date('Y')); // System year

			$exportPluginVariables = strtolower($this->piVars['year']);
			if (is_numeric($exportPluginVariables)) {
				$this->extConf['year'] = intval($exportPluginVariables);
			} else {
				if ($exportPluginVariables == 'all') {
					$this->extConf['year'] = $exportPluginVariables;
				}
			}

			if ($this->extConf['year'] == 'all') {
				if ($this->conf['yearNav.']['selection.']['all_year_split']) {
					$this->extConf['split_years'] = TRUE;
				}
			}

			// The selected year has no publications so select the closest year
			if (($this->stat['num_all'] > 0) && is_numeric($this->extConf['year'])) {
				$this->extConf['year'] = Utility::find_nearest_int($this->extConf['year'], $this->stat['years']);
			}
			// Append default link variable
			$this->extConf['link_vars']['year'] = $this->extConf['year'];

			if (is_numeric($this->extConf['year'])) {
				// Adjust num_page
				$this->stat['num_page'] = $this->stat['year_hist'][$this->extConf['year']];

				// Adjust year filter
				$this->extConf['filters']['br_year'] = [];
				$this->extConf['filters']['br_year']['year'] = [];
				$this->extConf['filters']['br_year']['year']['years'] = [$this->extConf['year']];
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeSearchNavigation() {
		$this->extConf['dynamic'] = TRUE;
		$this->extConf['search_navi'] = [];
		$this->extConf['search_navi']['obj'] =& Utility::getAndInitializeNavigationInstance('SearchNavigation', $this);
		$this->extConf['search_navi']['obj']->hook_init();
	}

	/**
	 * @return void
	 */
	protected function getPageNavigation() {
		$this->extConf['sub_page']['max'] = 0;
		$this->extConf['sub_page']['current'] = 0;

		if ($this->extConf['sub_page']['ipp'] > 0) {
			$this->extConf['sub_page']['max'] = floor(($this->stat['num_page'] - 1) / $this->extConf['sub_page']['ipp']);
			$this->extConf['sub_page']['current'] = Utility::crop_to_range(
					$this->piVars['page'],
					0,
					$this->extConf['sub_page']['max']
			);
		}

		if ($this->extConf['sub_page']['max'] > 0) {
			$this->extConf['show_nav_page'] = TRUE;

			$this->extConf['filters']['br_page'] = [];

			// Adjust the browse filter limit
			$this->extConf['filters']['br_page']['limit'] = [];
			$this->extConf['filters']['br_page']['limit']['start'] = $this->extConf['sub_page']['current'] * $this->extConf['sub_page']['ipp'];
			$this->extConf['filters']['br_page']['limit']['num'] = $this->extConf['sub_page']['ipp'];
		}
	}

	/**
	 * Retrieve and optimize Extension configuration
	 *
	 * @return void
	 */
	protected function getExtensionConfiguration() {
		$this->extConf = [];
		// Initialize current configuration
		$this->extConf['link_vars'] = [];
		$this->extConf['sub_page'] = [];

		$this->extConf['view_mode'] = View::VIEW_LIST;
		$this->extConf['debug'] = $this->conf['debug'] ? TRUE : FALSE;
		$this->extConf['ce_links'] = $this->conf['ce_links'] ? TRUE : FALSE;

		// Retrieve general FlexForm values
		$fSheet = 'sDEF';
		$this->extConf['d_mode'] = $this->pi_getFFvalue($this->flexFormData, 'display_mode', $fSheet);
		$this->extConf['enum_style'] = $this->pi_getFFvalue($this->flexFormData, 'enum_style', $fSheet);
		$this->extConf['show_nav_search'] = $this->pi_getFFvalue($this->flexFormData, 'show_search', $fSheet);
		$this->extConf['show_nav_author'] = $this->pi_getFFvalue($this->flexFormData, 'show_authors', $fSheet);
		$this->extConf['show_nav_pref'] = $this->pi_getFFvalue($this->flexFormData, 'show_pref', $fSheet);
		$this->extConf['sub_page']['ipp'] = $this->pi_getFFvalue($this->flexFormData, 'items_per_page', $fSheet);
		$this->extConf['max_authors'] = $this->pi_getFFvalue($this->flexFormData, 'max_authors', $fSheet);
		$this->extConf['split_bibtypes'] = $this->pi_getFFvalue($this->flexFormData, 'split_bibtypes', $fSheet);
		$this->extConf['stat_mode'] = $this->pi_getFFvalue($this->flexFormData, 'stat_mode', $fSheet);
		$this->extConf['show_nav_export'] = $this->pi_getFFvalue($this->flexFormData, 'export_mode', $fSheet);
		$this->extConf['date_sorting'] = $this->pi_getFFvalue($this->flexFormData, 'date_sorting', $fSheet);
		$this->extConf['sorting'] = $this->pi_getFFvalue($this->flexFormData, 'sorting', $fSheet);
		$this->extConf['search_fields'] = $this->pi_getFFvalue($this->flexFormData, 'search_fields', $fSheet);
		$this->extConf['separator'] = $this->pi_getFFvalue($this->flexFormData, 'separator', $fSheet);
		$this->extConf['editor_stop_words'] = $this->pi_getFFvalue($this->flexFormData, 'editor_stop_words', $fSheet);
		$this->extConf['title_stop_words'] = $this->pi_getFFvalue($this->flexFormData, 'title_stop_words', $fSheet);

		$show_fields = $this->pi_getFFvalue($this->flexFormData, 'show_textfields', $fSheet);
		$show_fields = explode(',', $show_fields);

		$this->extConf['hide_fields'] = [
				'abstract' => 1,
				'annotation' => 1,
				'note' => 1,
				'keywords' => 1,
				'tags' => 1
		];

		foreach ($show_fields as $f) {
			$field = FALSE;
			switch ($f) {
				case 1:
					$field = 'abstract';
					break;
				case 2:
					$field = 'annotation';
					break;
				case 3:
					$field = 'note';
					break;
				case 4:
					$field = 'keywords';
					break;
				case 5:
					$field = 'tags';
					break;
			}
			if ($field) {
				$this->extConf['hide_fields'][$field] = 0;
			}
		}
	}

	/**
	 * Get configuration from FlexForms
	 *
	 * @return void
	 */
	protected function getFrontendEditorConfiguration() {
		$flexFormSheet = 's_fe_editor';
		$this->extConf['editor']['enabled'] = $this->pi_getFFvalue($this->flexFormData, 'enable_editor', $flexFormSheet);
		$this->extConf['editor']['citeid_gen_new'] = $this->pi_getFFvalue($this->flexFormData, 'citeid_gen_new', $flexFormSheet);
		$this->extConf['editor']['citeid_gen_old'] = $this->pi_getFFvalue($this->flexFormData, 'citeid_gen_old', $flexFormSheet);
		$this->extConf['editor']['clear_page_cache'] = $this->pi_getFFvalue($this->flexFormData, 'clear_cache', $flexFormSheet);

		// Overwrite editor configuration from TSsetup
		if (is_array($this->conf['editor.'])) {
			if (array_key_exists('enabled', $this->conf['editor.'])) {
				$this->extConf['editor']['enabled'] = $this->conf['editor.']['enabled'] ? TRUE : FALSE;
			}
			if (array_key_exists('citeid_gen_new', $this->conf['editor.'])) {
				$this->extConf['editor']['citeid_gen_new'] = $this->conf['editor.']['citeid_gen_new'] ? TRUE : FALSE;
			}
			if (array_key_exists('citeid_gen_old', $this->conf['editor.'])) {
				$this->extConf['editor']['citeid_gen_old'] = $this->conf['editor.']['citeid_gen_old'] ? TRUE : FALSE;
			}
		}
		$this->referenceReader->setClearCache($this->extConf['editor']['clear_page_cache']);
	}

	/**
	 * Get storage pages
	 *
	 * @return void
	 */
	public function getStoragePid() {
		$pidList = [];
		if (isset ($this->conf['pid_list'])) {
			$this->pidList = GeneralUtility::intExplode(',', $this->conf['pid_list']);
		}
		if (isset ($this->cObj->data['pages'])) {
			$tmp = GeneralUtility::intExplode(',', $this->cObj->data['pages']);
			$this->pidList = array_merge($pidList, $tmp);
		}
	}

	/**
	 * Builds the export navigation
	 *
	 * @return void
	 */
	protected function getExportNavigation() {
		$this->extConf['export_navi'] = [];

		// Check group restrictions
		$groups = $this->conf['export.']['FE_groups_only'];
		$validFrontendUser = TRUE;
		if (strlen($groups) > 0) {
			$validFrontendUser = Utility::check_fe_user_groups($groups);
		}

		// Acquire export modes
		$modes = $this->conf['export.']['enable_export'];
		if (strlen($modes) > 0) {
			$modes = Utility::explode_trim_lower(
					',',
					$modes,
					TRUE
			);
		}

		// Add export modes
		$this->extConf['export_navi']['modes'] = [];
		$exportModules =& $this->extConf['export_navi']['modes'];
		if (is_array($modes) && $validFrontendUser) {
			$availableExportModes = ['bibtex', 'xml'];
			$exportModules = array_intersect($availableExportModes, $modes);
		}

		if (sizeof($exportModules) == 0) {
			$extConf['show_nav_export'] = FALSE;
		} else {
			$exportPluginVariables = trim($this->piVars['export']);
			if ((strlen($exportPluginVariables) > 0) && in_array($exportPluginVariables, $exportModules)) {
				$this->extConf['export_navi']['do'] = $exportPluginVariables;
			}
		}
	}

	/**
	 * Determine whether a valid backend user with write access to the reference table is logged in
	 *
	 * @return bool
	 */
	protected function isValidBackendUser() {

		$validBackendUser = FALSE;

		if (is_object($GLOBALS['BE_USER'])) {
			if ($GLOBALS['BE_USER']->isAdmin())
				$validBackendUser = TRUE;
			else {
				$validBackendUser = $GLOBALS['BE_USER']->check('tables_modify', $this->referenceReader->getReferenceTable());
			}
		}
		return $validBackendUser;
	}

	/**
	 * @param bool $validBackendUser
	 * @return bool
	 */
	protected function isValidFrontendUser($validBackendUser) {

		$validFrontendUser = FALSE;

		if (!$validBackendUser && isset ($this->conf['FE_edit_groups'])) {
			$groups = $this->conf['FE_edit_groups'];
			if (Utility::check_fe_user_groups($groups)) {
				$validFrontendUser = TRUE;
			}
		}
		return $validFrontendUser;
	}


	/**
	 * Returns the error message wrapped into a message container
	 *
	 * @deprecated Since 1.3.0 will be removed in 1.5.0. Use TYPO3 Flash Messaging Service
	 * @param String $errorString
	 * @return String The wrapper error message
	 */
	public function errorMessage($errorString) {
		GeneralUtility::logDeprecatedFunction();
		$errorMessage = '<div class="' . $this->prefixShort . '-warning_box">';
		$errorMessage .= '<h3>' . $this->prefix_pi1 . ' error</h3>';
		$errorMessage .= '<div>' . $errorString . '</div>';
		$errorMessage .= '</div>';
		return $errorMessage;
	}


	/**
	 * This initializes field restrictions
	 *
	 * @return void
	 */
	protected function initializeRestrictions() {
		$this->extConf['restrict'] = [];
		$restrictions =& $this->extConf['restrict'];

		$restrictionConfiguration =& $this->conf['restrictions.'];
		if (!is_array($restrictionConfiguration)) {
			return;
		}

		// This is a nested array containing fields
		// that may have restrictions
		$fields = [
				'ref' => [],
				'author' => []
		];
		$allFields = [];
		// Acquire field configurations
		foreach ($restrictionConfiguration as $table => $data) {
			if (is_array($data)) {
				$table = substr($table, 0, -1);

				switch ($table) {
					case 'ref':
						$allFields = $this->referenceReader->getReferenceFields();
						break;
					case 'authors':
						$allFields = $this->referenceReader->getAuthorFields();
						break;
					default:
						continue;
				}

				foreach ($data as $t_field => $t_data) {
					if (is_array($t_data)) {
						$t_field = substr($t_field, 0, -1);
						if (in_array($t_field, $allFields)) {
							$fields[$table][] = $t_field;
						}
					}
				}
			}
		}

		// Process restriction requests
		foreach ($fields as $table => $tableFields) {
			$restrictions[$table] = [];
			$d_table = $table . '.';
			foreach ($tableFields as $field) {
				$d_field = $field . '.';
				$rcfg = $restrictionConfiguration[$d_table][$d_field];

				// Hide all
				$all = ($rcfg['hide_all'] != 0);

				// Hide on string extensions
				$ext = Utility::explode_trim_lower(',', $rcfg['hide_file_ext'], TRUE);

				// Reveal on FE user groups
				$groups = strtolower($rcfg['FE_user_groups']);
				if (strpos($groups, 'all') === FALSE) {
					$groups = GeneralUtility::intExplode(',', $groups);
				} else {
					$groups = 'all';
				}

				if ($all || (sizeof($ext) > 0)) {
					$restrictions[$table][$field] = [
							'hide_all' => $all,
							'hide_ext' => $ext,
							'fe_groups' => $groups
					];
				}
			}
		}
	}


	/**
	 * This initializes all filters before the browsing filter
	 *
	 * @return void
	 */
	protected function initializeFilters() {
		$this->extConf['filters'] = [];
		$this->initializeFlexformFilter();

		try {
			$this->initializeSelectionFilter();
		} catch (\Exception $e) {
			$message = GeneralUtility::makeInstance(FlashMessage::class,
					$e->getMessage(),
					'',
					FlashMessage::ERROR
			);
			FlashMessageQueue::addMessage($message);
		}
	}

	/**
	 * @return void
	 */
	protected function initializeYearFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_year', $this->flexFormFilterSheet) > 0) {
			$flexFormFilter = [];
			$flexFormFilter['years'] = [];
			$flexFormFilter['ranges'] = [];
			$ffStr = $this->pi_getFFvalue($this->flexForm, 'years', $this->flexFormFilterSheet);
			$arr = Utility::multi_explode_trim(
					[',', "\r", "\n"],
					$ffStr,
					TRUE
			);

			foreach ($arr as $year) {
				if (strpos($year, '-') === FALSE) {
					if (is_numeric($year)) {
						$flexFormFilter['years'][] = intval($year);
					}
				} else {
					$range = [];
					$elms = GeneralUtility::trimExplode('-', $year, FALSE);
					if (is_numeric($elms[0])) {
						$range['from'] = intval($elms[0]);
					}
					if (is_numeric($elms[1])) {
						$range['to'] = intval($elms[1]);
					}
					if (sizeof($range) > 0) {
						$flexFormFilter['ranges'][] = $range;
					}
				}
			}
			if ((sizeof($flexFormFilter['years']) + sizeof($flexFormFilter['ranges'])) > 0) {
				$this->extConf['filters']['flexform']['year'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeAuthorFilter() {
		$this->extConf['highlight_authors'] = $this->pi_getFFvalue($this->flexForm, 'highlight_authors', $this->flexFormFilterSheet);

		if ($this->pi_getFFvalue($this->flexForm, 'enable_author', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['authors'] = [];
			$flexFormFilter['rule'] = $this->pi_getFFvalue($this->flexForm, 'author_rule', $this->flexFormFilterSheet);
			$flexFormFilter['rule'] = intval($flexFormFilter['rule']);

			$authors = $this->pi_getFFvalue($this->flexForm, 'authors', $this->flexFormFilterSheet);
			$authors = Utility::multi_explode_trim(
					["\r", "\n"],
					$authors,
					TRUE
			);

			foreach ($authors as $a) {
				$parts = GeneralUtility::trimExplode(',', $a);
				$author = [];
				if (strlen($parts[0]) > 0) {
					$author['surname'] = $parts[0];
				}
				if (strlen($parts[1]) > 0) {
					$author['forename'] = $parts[1];
				}
				if (sizeof($author) > 0) {
					$flexFormFilter['authors'][] = $author;
				}
			}
			if (sizeof($flexFormFilter['authors']) > 0) {
				$this->extConf['filters']['flexform']['author'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeStateFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_state', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['states'] = [];
			$states = intval($this->pi_getFFvalue($this->flexForm, 'states', $this->flexFormFilterSheet));

			$j = 1;
			$referenceReaderStateSize = sizeof($this->referenceReader->allStates);
			for ($i = 0; $i < $referenceReaderStateSize; $i++) {
				if ($states & $j) {
					$flexFormFilter['states'][] = $i;
				}
				$j = $j * 2;
			}
			if (sizeof($flexFormFilter['states']) > 0) {
				$this->extConf['filters']['flexform']['state'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeBibliographyTypeFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_bibtype', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['types'] = [];
			$types = $this->pi_getFFvalue($this->flexForm, 'bibtypes', $this->flexFormFilterSheet);
			$types = explode(',', $types);
			foreach ($types as $type) {
				$type = intval($type);
				if (($type >= 0) && ($type < sizeof($this->referenceReader->allBibTypes))) {
					$flexFormFilter['types'][] = $type;
				}
			}
			if (sizeof($flexFormFilter['types']) > 0) {
				$this->extConf['filters']['flexform']['bibtype'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeOriginFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_origin', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['origin'] = $this->pi_getFFvalue($this->flexForm, 'origins', $this->flexFormFilterSheet);

			if ($flexFormFilter['origin'] == 1) {
				// Legacy value
				$flexFormFilter['origin'] = 0;
			} else if ($flexFormFilter['origin'] == 2) {
				// Legacy value
				$flexFormFilter['origin'] = 1;
			}

			$this->extConf['filters']['flexform']['origin'] = $flexFormFilter;
		}
	}

	/**
	 * @return void
	 */
	protected function initializePidFilter() {
		$this->extConf['filters']['flexform']['pid'] = $this->extConf['pid_list'];
	}

	/**
	 * @return void
	 */
	protected function initializeReviewFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_reviewes', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['value'] = $this->pi_getFFvalue($this->flexForm, 'reviewes', $this->flexFormFilterSheet);
			$this->extConf['filters']['flexform']['reviewed'] = $flexFormFilter;
		}
	}

	/**
	 * @return void
	 */
	protected function initializeInLibraryFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_in_library', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['value'] = $this->pi_getFFvalue($this->flexForm, 'in_library', $this->flexFormFilterSheet);
			$this->extConf['filters']['flexform']['in_library'] = $flexFormFilter;
		}
	}

	/**
	 * @return void
	 */
	protected function initializeBorrowedFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_borrowed', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$flexFormFilter['value'] = $this->pi_getFFvalue($this->flexForm, 'borrowed', $this->flexFormFilterSheet);
			$this->extConf['filters']['flexform']['borrowed'] = $flexFormFilter;
		}
	}

	/**
	 * @return void
	 */
	protected function initializeCiteIdFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_citeid', $this->flexFormFilterSheet) != 0) {
			$flexFormFilter = [];
			$ids = $this->pi_getFFvalue($this->flexForm, 'citeids', $this->flexFormFilterSheet);
			if (strlen($ids) > 0) {
				$ids = \Ipf\Bib\Utility\Utility::multi_explode_trim(
						[
								',',
								"\r",
								"\n"
						],
						$ids,
						TRUE
				);
				$flexFormFilter['ids'] = array_unique($ids);
				$this->extConf['filters']['flexform']['citeid'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeTagFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_tags', $this->flexFormFilterSheet)) {
			$flexFormFilter = [];
			$flexFormFilter['rule'] = $this->pi_getFFvalue($this->flexForm, 'tags_rule', $this->flexFormFilterSheet);
			$flexFormFilter['rule'] = intval($flexFormFilter['rule']);
			$kw = $this->pi_getFFvalue($this->flexForm, 'tags', $this->flexFormFilterSheet);
			if (strlen($kw) > 0) {
				$words = Utility::multi_explode_trim(
						[
								',',
								"\r",
								"\n"
						],
						$kw,
						TRUE
				);
				foreach ($words as &$word) {
					$word = $this->referenceReader->getSearchTerm($word, $this->extConf['charset']['upper']);
				}
				$flexFormFilter['words'] = $words;
				$this->extConf['filters']['flexform']['tags'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeKeywordsFilter() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_keywords', $this->flexFormFilterSheet)) {
			$flexFormFilter = [];
			$flexFormFilter['rule'] = $this->pi_getFFvalue($this->flexForm, 'keywords_rule', $this->flexFormFilterSheet);
			$flexFormFilter['rule'] = intval($flexFormFilter['rule']);
			$kw = $this->pi_getFFvalue($this->flexForm, 'keywords', $this->flexFormFilterSheet);
			if (strlen($kw) > 0) {
				$words = Utility::multi_explode_trim([',', "\r", "\n"], $kw, TRUE);
				foreach ($words as &$word) {
					$word = $this->referenceReader->getSearchTerm($word, $this->extConf['charset']['upper']);
				}
				$flexFormFilter['words'] = $words;
				$this->extConf['filters']['flexform']['keywords'] = $flexFormFilter;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function initializeGeneralKeywordSearch() {
		if ($this->pi_getFFvalue($this->flexForm, 'enable_search_all', $this->flexFormFilterSheet)) {
			$flexFormFilter = [];
			$flexFormFilter['rule'] = $this->pi_getFFvalue($this->flexForm, 'search_all_rule', $this->flexFormFilterSheet);
			$flexFormFilter['rule'] = intval($flexFormFilter['rule']);
			$kw = $this->pi_getFFvalue($this->flexForm, 'search_all', $this->flexFormFilterSheet);
			if (strlen($kw) > 0) {
				$words = Utility::multi_explode_trim([',', "\r", "\n"], $kw, TRUE);
				foreach ($words as &$word) {
					$word = $this->referenceReader->getSearchTerm($word, $this->extConf['charset']['upper']);
				}
				$flexFormFilter['words'] = $words;
				$this->extConf['filters']['flexform']['all'] = $flexFormFilter;
			}
		}
	}

	/**
	 * This initializes filter array from the flexform
	 *
	 * @return void
	 */
	protected function initializeFlexformFilter() {
		// Create and select the flexform filter
		$this->extConf['filters']['flexform'] = [];

		// Filtersheet and flexform data into variable
		$this->flexForm = $this->cObj->data['pi_flexform'];
		$this->flexFormFilterSheet = 's_filter';

		$this->initializePidFilter();

		$this->initializeYearFilter();

		$this->initializeAuthorFilter();

		$this->initializeStateFilter();

		$this->initializeBibliographyTypeFilter();

		$this->initializeOriginFilter();

		$this->initializeReviewFilter();

		$this->initializeInLibraryFilter();

		$this->initializeBorrowedFilter();

		$this->initializeCiteIdFilter();

		$this->initializeTagFilter();

		$this->initializeKeywordsFilter();

		$this->initializeGeneralKeywordSearch();

	}


	/**
	 * This initializes the selection filter array from the piVars
	 *
	 * @throws \Exception
	 * @return void
	 */
	protected function initializeSelectionFilter() {
		$this->extConf['filters']['selection'] = [];
		$filter =& $this->extConf['filters']['selection'];

		// Publication ids
		if (is_string($this->piVars['search']['ref_ids'])) {
			$ids = $this->piVars['search']['ref_ids'];
			$ids = GeneralUtility::intExplode(',', $ids);

			if (sizeof($ids) > 0) {
				$filter['uid'] = $ids;
			}
		}

		// General search
		if (is_string($this->piVars['search']['all'])) {
			$words = $this->piVars['search']['all'];
			$words = GeneralUtility::trimExplode(',', $words, TRUE);
			if (sizeof($words) > 0) {
				$filter['all']['words'] = $words;

				// AND
				$filter['all']['rule'] = 1;
				$rule = strtoupper(trim($this->piVars['search']['all_rule']));
				if (strpos($rule, 'AND') === FALSE) {
					// OR
					$filter['all']['rule'] = 0;
				}
			}
		}
	}


	/**
	 * Initializes an array which contains subparts of the
	 * html templates.
	 *
	 * @throws \Ipf\Bib\Exception\RenderingException
	 * @return array
	 */
	protected function initializeHtmlTemplate() {
		$error = [];

		// Already initialized?
		if (isset ($this->template['LIST_VIEW'])) {
			return $error;
		}

		$this->template = [];
		$this->itemTemplate = [];

		// List blocks
		$list_blocks = [
				'YEAR_BLOCK', 'BIBTYPE_BLOCK', 'SPACER_BLOCK'
		];

		// Bibtype data blocks
		$bib_types = [];
		foreach ($this->referenceReader->allBibTypes as $val) {
			$bib_types[] = strtoupper($val) . '_DATA';
		}
		$bib_types[] = 'DEFAULT_DATA';
		$bib_types[] = 'ITEM_BLOCK';

		// Misc navigation blocks
		$navi_blocks = [
				'EXPORT_NAVI_BLOCK',
				'IMPORT_NAVI_BLOCK',
				'NEW_ENTRY_NAVI_BLOCK'
		];

		// Fetch the template file list
		$templateList =& $this->conf['templates.'];
		if (!is_array($templateList)) {
			throw new \Ipf\Bib\Exception\RenderingException('HTML templates are not set in TypoScript', 1378817757);
		}

		$info = [
				'main' => [
						'file' => $templateList['main'],
						'parts' => ['LIST_VIEW']
				],
				'list_blocks' => [
						'file' => $templateList['list_blocks'],
						'parts' => $list_blocks
				],
				'list_items' => [
						'file' => $templateList['list_items'],
						'parts' => $bib_types,
						'no_warn' => TRUE
				],
				'navi_misc' => [
						'file' => $templateList['navi_misc'],
						'parts' => $navi_blocks,
				]
		];

		foreach ($info as $key => $val) {
			if (strlen($val['file']) == 0) {
				throw new \Ipf\Bib\Exception\RenderingException('HTML template file for \'' . $key . '\' is not set', 1378817806);
			}
			$tmpl = $this->cObj->fileResource($val['file']);
			if (strlen($tmpl) == 0) {
				throw new \Ipf\Bib\Exception\RenderingException('The HTML template file \'' . $val['file'] . '\' for \'' . $key . '\' is not readable or empty', 1378817895);
			}
			foreach ($val['parts'] as $part) {
				$ptag = '###' . $part . '###';
				$pstr = $this->cObj->getSubpart($tmpl, $ptag);
				// Error message
				if ((strlen($pstr) == 0) && !$val['no_warn']) {
					throw new \Ipf\Bib\Exception\RenderingException('The subpart \'' . $ptag . '\' in the HTML template file \'' . $val['file'] . '\' is empty', 1378817933);
				}
				$this->template[$part] = $pstr;
			}
		}

		return $error;
	}


	/**
	 * Initialize the edit icons
	 *
	 * @return void
	 */
	protected function initializeEditIcons() {
		$list = [];
		$more = $this->conf['edit_icons.'];
		if (is_array($more)) {
			$list = array_merge($list, $more);
		}

		// @todo can't figure out $base
		foreach ($list as $key => $val) {
			$this->icon_src[$key] = $GLOBALS['TSFE']->tmpl->getFileName($val);
		}
	}


	/**
	 * Initialize the list view icons
	 *
	 * @return void
	 */
	protected function initializeListViewIcons() {
		$list = ['default' => 'typo3/gfx/fileicons/default.gif'];
		$more = $this->conf['file_icons.'];
		if (is_array($more)) {
			$list = array_merge($list, $more);
		}

		$this->icon_src['files'] = [];

		foreach ($list as $key => $val) {
			$this->icon_src['files']['.' . $key] = $GLOBALS['TSFE']->tmpl->getFileName($val);
		}
	}


	/**
	 * Extend the $this->LOCAL_LANG label with another language set
	 *
	 * @param string $file
	 * @return void
	 */
	public function extend_ll($file) {
		if (!is_array($this->extConf['LL_ext']))
			$this->extConf['LL_ext'] = [];
		if (!in_array($file, $this->extConf['LL_ext'])) {

			$tmpLang = GeneralUtility::readLLfile($file, $this->LLkey);
			foreach ($this->LOCAL_LANG as $lang => $list) {
				foreach ($list as $key => $word) {
					$tmpLang[$lang][$key] = $word;
				}
			}
			$this->LOCAL_LANG = $tmpLang;

			if ($this->altLLkey) {
				$tmpLang = GeneralUtility::readLLfile($file, $this->altLLkey);
				foreach ($this->LOCAL_LANG as $lang => $list) {
					foreach ($list as $key => $word) {
						$tmpLang[$lang][$key] = $word;
					}
				}
				$this->LOCAL_LANG = $tmpLang;
			}

			$this->extConf['LL_ext'][] = $file;
		}
	}


	/**
	 * Get the string in the local language to a given key .
	 *
	 * @param string $key
	 * @param string $alt
	 * @param bool $hsc
	 * @return string The string in the local language
	 */
	public function get_ll($key, $alt = '', $hsc = FALSE) {
		return $this->pi_getLL($key, $alt, $hsc);
	}


	/**
	 * Composes a link of an url an some attributes
	 *
	 * @param string $url
	 * @param string $content
	 * @param array $attributes
	 * @return string The link (HTML <a> element)
	 */
	protected function composeLink($url, $content, $attributes = NULL) {
		$linkString = '<a href="' . $url . '"';
		if (is_array($attributes)) {
			foreach ($attributes as $k => $v) {
				$linkString .= ' ' . $k . '="' . $v . '"';
			}
		}
		$linkString .= '>' . $content . '</a>';
		return $linkString;
	}


	/**
	 * Wraps the content into a link to the current page with
	 * extra link arguments given in the array $linkVariables
	 *
	 * @param string $content
	 * @param array $linkVariables
	 * @param bool $autoCache
	 * @param array $attributes
	 * @return string The link to the current page
	 */
	public function get_link($content, $linkVariables = [], $autoCache = TRUE, $attributes = NULL) {
		$url = $this->get_link_url($linkVariables, $autoCache);
		return $this->composeLink($url, $content, $attributes);
	}


	/**
	 * Same as get_link but returns just the URL
	 *
	 * @param array $linkVariables
	 * @param bool $autoCache
	 * @param bool $currentRecord
	 * @return string The url
	 */
	public function get_link_url($linkVariables = [], $autoCache = TRUE, $currentRecord = TRUE) {
		if ($this->extConf['edit_mode']) $autoCache = FALSE;

		$linkVariables = array_merge($this->extConf['link_vars'], $linkVariables);
		$linkVariables = [$this->prefix_pi1 => $linkVariables];

		$record = '';
		if ($this->extConf['ce_links'] && $currentRecord) {
			$record = '#c' . strval($this->cObj->data['uid']);
		}

		$this->pi_linkTP('x', $linkVariables, $autoCache);
		$url = $this->cObj->lastTypoLinkUrl . $record;

		$url = preg_replace('/&([^;]{8})/', '&amp;\\1', $url);
		return $url;
	}

	/**
	 * Same as get_link_url() but for edit mode urls
	 *
	 * @param array $linkVariables
	 * @param bool $autoCache
	 * @param bool $currentRecord
	 * @return string The url
	 */
	public function get_edit_link_url($linkVariables = [], $autoCache = TRUE, $currentRecord = TRUE) {
		$parametersToBeKept = ['uid', 'editor_mode', 'editor'];
		foreach ($parametersToBeKept as $parameter) {
			if (is_string($this->piVars[$parameter]) || is_array($this->piVars[$parameter]) || is_numeric($this->piVars[$parameter])) {
				$linkVariables[$parameter] = $this->piVars[$parameter];
			}
		}
		return $this->get_link_url($linkVariables, $autoCache, $currentRecord);
	}

	/**
	 * Prepares database publication data for displaying
	 *
	 * @param array $publication
	 * @param array $warnings
	 * @param bool $showHidden
	 * @return array The processed publication data array
	 */
	public function preparePublicationData($publication, &$warnings = [], $showHidden = FALSE) {
		// The error list
		$d_err = [];

		// Prepare processed row data
		$publicationData = $publication;
		foreach ($this->referenceReader->getReferenceFields() as $referenceField) {
			$publicationData[$referenceField] = Utility::filter_pub_html_display($publicationData[$referenceField], FALSE, $this->extConf['charset']['upper']);
		}

		// Preprocess some data

		// File url
		// Check file existance
		$fileUrl = trim(strval($publication['file_url']));
		if (Utility::check_file_nexist($fileUrl)) {
			$publicationData['file_url'] = '';
			$publicationData['_file_nexist'] = TRUE;
		} else {
			$publicationData['_file_nexist'] = FALSE;
		}

		// Bibtype
		$publicationData['bibtype_short'] = $this->referenceReader->allBibTypes[$publicationData['bibtype']];
		$publicationData['bibtype'] = $this->get_ll(
				$this->referenceReader->getReferenceTable() . '_bibtype_I_' . $publicationData['bibtype'],
				'Unknown bibtype: ' . $publicationData['bibtype'],
				TRUE
		);

		// External
		$publicationData['extern'] = ($publication['extern'] == 0 ? '' : 'extern');

		// Day
		if (($publication['day'] > 0) && ($publication['day'] <= 31)) {
			$publicationData['day'] = strval($publication['day']);
		} else {
			$publicationData['day'] = '';
		}

		// Month
		if (($publication['month'] > 0) && ($publication['month'] <= 12)) {
			$tme = mktime(0, 0, 0, intval($publication['month']), 15, 2008);
			$publicationData['month'] = $tme;
		} else {
			$publicationData['month'] = '';
		}

		// State
		switch ($publicationData['state']) {
			case 0 :
				$publicationData['state'] = '';
				break;
			default :
				$publicationData['state'] = $this->get_ll(
						$this->referenceReader->getReferenceTable() . '_state_I_' . $publicationData['state'],
						'Unknown state: ' . $publicationData['state'],
						TRUE
				);
		}

		// Bool strings
		$b_yes = $this->get_ll('label_yes', 'Yes', TRUE);
		$b_no = $this->get_ll('label_no', 'No', TRUE);

		// Bool fields
		$publicationData['reviewed'] = ($publication['reviewed'] > 0) ? $b_yes : $b_no;
		$publicationData['in_library'] = ($publication['in_library'] > 0) ? $b_yes : $b_no;

		// Copy field values
		$charset = $this->extConf['charset']['upper'];
		$url_max = 40;
		if (is_numeric($this->conf['max_url_string_length'])) {
			$url_max = intval($this->conf['max_url_string_length']);
		}

		// Iterate through reference fields
		foreach ($this->referenceReader->getReferenceFields() as $referenceField) {
			// Trim string
			$val = trim(strval($publicationData[$referenceField]));

			if (strlen($val) == 0) {
				$publicationData[$referenceField] = $val;
				continue;
			}

			// Treat some fields
			switch ($referenceField) {
				case 'file_url':
				case 'web_url':
				case 'web_url2':
					$publicationData[$referenceField] = Utility::fix_html_ampersand($val);
					$val = Utility::crop_middle($val, $url_max, $charset);
					$publicationData[$referenceField . '_short'] = Utility::fix_html_ampersand($val);
					break;
				case 'DOI':
					$publicationData[$referenceField] = $val;
					$publicationData['DOI_url'] = sprintf('http://dx.doi.org/%s', $val);
					break;
				default:
					$publicationData[$referenceField] = $val;
			}
		}

		// Multi fields
		$multi = [
				'authors' => $this->referenceReader->getAuthorFields()
		];
		foreach ($multi as $table => $fields) {
			$elements =& $publicationData[$table];
			if (!is_array($elements)) {
				continue;
			}
			foreach ($elements as &$element) {
				foreach ($fields as $field) {
					$val = $element[$field];
					// Check restrictions
					if (strlen($val) > 0) {
						if ($this->checkFieldRestriction($table, $field, $val)) {
							$val = '';
							$element[$field] = $val;
						}
					}
				}
			}
		}

		// Format the author string
		$publicationData['authors'] = $this->getItemAuthorsHtml($publicationData['authors']);

		// store editor's data before processing it
		$cleanEditors = $publicationData['editor'];

		// Editors
		if (strlen($publicationData['editor']) > 0) {
			$editors = Utility::explodeAuthorString($publicationData['editor']);
			$lst = [];
			foreach ($editors as $ed) {
				$app = '';
				if (strlen($ed['forename']) > 0) {
					$app .= $ed['forename'] . ' ';
				}
				if (strlen($ed['surname']) > 0) {
					$app .= $ed['surname'];
				}
				$app = $this->cObj->stdWrap($app, $this->conf['field.']['editor_each.']);
				$lst[] = $app;
			}

			$and = ' ' . $this->get_ll('label_and', 'and', TRUE) . ' ';
			$publicationData['editor'] = Utility::implode_and_last(
					$lst,
					', ',
					$and
			);

			// reset processed data @todo check if the above block may be removed
			$publicationData['editor'] = $cleanEditors;

		}

		// Automatic url
		$order = GeneralUtility::trimExplode(',', $this->conf['auto_url_order'], TRUE);
		$publicationData['auto_url'] = $this->getAutoUrl($publicationData, $order);
		$publicationData['auto_url_short'] = Utility::crop_middle(
				$publicationData['auto_url'],
				$url_max,
				$charset
		);

		// Do data checks
		if ($this->extConf['edit_mode']) {

			// Local file does not exist
			$type = 'file_nexist';
			if ($this->conf['editor.']['list.']['warnings.'][$type]) {
				if ($publicationData['_file_nexist']) {
					$msg = $this->get_ll('editor_error_file_nexist');
					$msg = str_replace('%f', $fileUrl, $msg);
					$d_err[] = ['type' => $type, 'msg' => $msg];
				}
			}

		}

		$warnings = $d_err;

		return $publicationData;
	}


	/**
	 * Prepares the cObj->data array for a reference
	 *
	 * @param array $pdata
	 * @return array The procesed publication data array
	 */
	public function prepare_pub_cObj_data($pdata) {
		// Item data
		$this->cObj->data = $pdata;

		// Needed since stdWrap/Typolink applies htmlspecialchars to url data
		$this->cObj->data['file_url'] = htmlspecialchars_decode($pdata['file_url'], ENT_QUOTES);
		$this->cObj->data['DOI_url'] = htmlspecialchars_decode($pdata['DOI_url'], ENT_QUOTES);
		$this->cObj->data['auto_url'] = htmlspecialchars_decode($pdata['auto_url'], ENT_QUOTES);
	}

	/**
	 * Returns the authors string for a publication
	 *
	 * @param array $authors
	 * @return string
	 */
	protected function getItemAuthorsHtml(&$authors) {

		$charset = $this->extConf['charset']['upper'];

		$contentObjectBackup = $this->cObj->data;

		// Format the author string$this->
		$separator = $this->extConf['separator'];
		if (isset($separator) && !empty($separator)) {
			$name_separator = $separator;
		} else {
			$name_separator = ' ' . $this->get_ll('label_and', 'and', TRUE) . ' ';
		}
		$max_authors = abs(intval($this->extConf['max_authors']));
		$lastAuthor = sizeof($authors) - 1;
		$cutAuthors = FALSE;
		if (($max_authors > 0) && (sizeof($authors) > $max_authors)) {
			$cutAuthors = TRUE;
			if (sizeof($authors) == ($max_authors + 1)) {
				$lastAuthor = $max_authors - 2;
			} else {
				$lastAuthor = $max_authors - 1;
			}
			$name_separator = '';
		}
		$lastAuthor = max($lastAuthor, 0);

		$highlightAuthors = $this->extConf['highlight_authors'] ? TRUE : FALSE;

		$link_fields = $this->extConf['author_sep'];
		$a_sep = $this->extConf['author_sep'];
		$authorTemplate = $this->extConf['author_tmpl'];

		$filter_authors = [];
		if ($highlightAuthors) {
			// Collect filter authors
			foreach ($this->extConf['filters'] as $filter) {
				if (is_array($filter['author']['authors'])) {
					$filter_authors = array_merge($filter_authors, $filter['author']['authors']);
				}
			}
		}

		$elements = [];
		// Iterate through authors
		for ($i_a = 0; $i_a <= $lastAuthor; $i_a++) {
			$author = $authors[$i_a];

			// Init cObj data
			$this->cObj->data = $author;
			$this->cObj->data['url'] = htmlspecialchars_decode($author['url'], ENT_QUOTES);

			// The forename
			$authorForename = trim($author['forename']);
			if (strlen($authorForename) > 0) {
				$authorForename = Utility::filter_pub_html_display($authorForename, FALSE, $this->extConf['charset']['upper']);
				$authorForename = $this->cObj->stdWrap($authorForename, $this->conf['authors.']['forename.']);
			}

			// The surname
			$authorSurname = trim($author['surname']);
			if (strlen($authorSurname) > 0) {
				$authorSurname = Utility::filter_pub_html_display($authorSurname, FALSE, $this->extConf['charset']['upper']);
				$authorSurname = $this->cObj->stdWrap($authorSurname, $this->conf['authors.']['surname.']);
			}

			// The link icon
			$cr_link = FALSE;
			$authorIcon = '';
			foreach ($this->extConf['author_lfields'] as $field) {
				$val = trim(strval($author[$field]));
				if ((strlen($val) > 0) && ($val != '0')) {
					$cr_link = TRUE;
					break;
				}
			}
			if ($cr_link && (strlen($this->extConf['author_icon_img']) > 0)) {
				$wrap = $this->conf['authors.']['url_icon.'];
				if (is_array($wrap)) {
					if (is_array($wrap['typolink.'])) {
						$title = $this->get_ll('link_author_info', 'Author info', TRUE);
						$wrap['typolink.']['title'] = $title;
					}
					$authorIcon = $this->cObj->stdWrap($this->extConf['author_icon_img'], $wrap);
				}
			}

			// Compose names
			$a_str = str_replace(
					['###SURNAME###', '###FORENAME###', '###URL_ICON###'],
					[$authorSurname, $authorForename, $authorIcon], $authorTemplate);

			// apply stdWrap
			$stdWrap = $this->conf['field.']['author.'];
			if (is_array($this->conf['field.'][$bib_str . '.']['author.'])) {
				$stdWrap = $this->conf['field.'][$bib_str . '.']['author.'];
			}
			$a_str = $this->cObj->stdWrap($a_str, $stdWrap);

			// Wrap the filtered authors with a highlighting class on demand
			if ($highlightAuthors) {
				foreach ($filter_authors as $fa) {
					if ($author['surname'] == $fa['surname']) {
						if (!$fa['forename'] || ($author['forename'] == $fa['forename'])) {
							$a_str = $this->cObj->stdWrap($a_str, $this->conf['authors.']['highlight.']);
							break;
						}
					}
				}
			}

			// Append author name
			if (!empty($authorSurname)) {
				$elements[] = $authorSurname . ', ' . $authorForename;
			}

			// Append 'et al.'
			if ($cutAuthors && ($i_a == $lastAuthor)) {
				// Append et al.
				$etAl = $this->get_ll('label_et_al', 'et al.', TRUE);
				$etAl = (strlen($etAl) > 0) ? ' ' . $etAl : '';

				if (strlen($etAl) > 0) {

					// Highlight "et al." on demand
					if ($highlightAuthors) {
						$authorsSize = sizeof($authors);
						for ($j = $lastAuthor + 1; $j < $authorsSize; $j++) {
							$a_et = $authors[$j];
							foreach ($filter_authors as $fa) {
								if ($a_et['surname'] == $fa['surname']) {
									if (!$fa['forename'] || ($a_et['forename'] == $fa['forename'])) {
										$wrap = $this->conf['authors.']['highlight.'];
										$j = sizeof($authors);
										break;
									}
								}
							}
						}
					}

					$wrap = $this->conf['authors.']['et_al.'];
					$etAl = $this->cObj->stdWrap($etAl, $wrap);
					$elements[] = $etAl;
				}
			}
		}

		$res = Utility::implode_and_last($elements, $a_sep, $name_separator);
		// Restore cObj data
		$this->cObj->data = $contentObjectBackup;

		return $res;
	}


	/**
	 * Setup items in the html-template
	 *
	 * @return void
	 */
	public function prepareItemSetup() {

		$charset = $this->extConf['charset']['upper'];

		// The author name template
		$this->extConf['author_tmpl'] = '###FORENAME### ###SURNAME###';
		if (isset ($this->conf['authors.']['template'])) {
			$this->extConf['author_tmpl'] = $this->cObj->stdWrap(
				$this->conf['authors.']['template'], $this->conf['authors.']['template.']
			);
		}
		$this->extConf['author_sep'] = ', ';
		if (isset ($this->conf['authors.']['separator'])) {
			$this->extConf['author_sep'] = $this->cObj->stdWrap(
				$this->conf['authors.']['separator'], $this->conf['authors.']['separator.']
			);
		}
		$this->extConf['author_lfields'] = 'url';
		if (isset ($this->conf['authors.']['url_icon_fields'])) {
			$this->extConf['author_lfields'] = GeneralUtility::trimExplode(',', $this->conf['authors.']['url_icon_fields'], TRUE);
		}

		// Acquire author url icon
		$authorsUrlIconFile = trim($this->conf['authors.']['url_icon_file']);
		$imageTag = '';
		if (strlen($authorsUrlIconFile) > 0) {
			$authorsUrlIconFile = $GLOBALS['TSFE']->tmpl->getFileName($authorsUrlIconFile);
			$authorsUrlIconFile = htmlspecialchars($authorsUrlIconFile, ENT_QUOTES, $charset);
			$alt = $this->get_ll('img_alt_person', 'Author image', TRUE);
			$imageTag = '<img src="' . $authorsUrlIconFile . '" alt="' . $alt . '"';
			if (is_string($this->conf['authors.']['url_icon_class'])) {
				$imageTag .= ' class="' . $this->conf['authors.']['url_icon_class'] . '"';
			}
			$imageTag .= '/>';
		}
		$this->extConf['author_icon_img'] = $imageTag;

	}

	/**
	 * Returns TRUE if the field/value combination is restricted
	 * and should not be displayed
	 *
	 * @param String $table
	 * @param String $field
	 * @param String $value
	 * @param bool $showHidden
	 * @return bool TRUE (restricted) or FALSE (not restricted)
	 */
	protected function checkFieldRestriction($table, $field, $value, $showHidden = FALSE) {
		// No value no restriction
		if (strlen($value) == 0) {
			return FALSE;
		}

		// Field is hidden
		if (!$showHidden && $this->extConf['hide_fields'][$field]) {
			return TRUE;
		}

		// Are there restrictions at all?
		$restrictions =& $this->extConf['restrict'][$table];
		if (!is_array($restrictions) || (sizeof($restrictions) == 0)) {
			return FALSE;
		}

		// Check Field restrictions
		if (is_array($restrictions[$field])) {
			$restrictionConfiguration =& $restrictions[$field];

			// Show by default
			$show = TRUE;

			// Hide on 'hide all'
			if ($restrictionConfiguration['hide_all']) {
				$show = FALSE;
			}

			// Hide if any extensions matches
			if ($show && is_array($restrictionConfiguration['hide_ext'])) {
				foreach ($restrictionConfiguration['hide_ext'] as $ext) {
					// Sanitize input
					$len = strlen($ext);
					if (($len > 0) && (strlen($value) >= $len)) {
						$uext = strtolower(substr($value, -$len));

						if ($uext == $ext) {
							$show = FALSE;
							break;
						}
					}
				}
			}

			// Enable if usergroup matches
			if (!$show && isset ($restrictionConfiguration['fe_groups'])) {
				$groups = $restrictionConfiguration['fe_groups'];
				if (Utility::check_fe_user_groups($groups))
					$show = TRUE;
			}

			// Restricted !
			if (!$show) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Prepares the virtual auto_url from the data and field order
	 *
	 * @param array $processedPublicationData The processed publication data
	 * @param array $order
	 * @return string The generated url
	 */
	protected function getAutoUrl($processedPublicationData, $order) {

		$url = '';

		foreach ($order as $field) {
			if (strlen($processedPublicationData[$field]) == 0) {
				continue;
			}
			if ($this->checkFieldRestriction('ref', $field, $processedPublicationData[$field])) {
				continue;
			}

			switch ($field) {
				case 'file_url':
					if (!$processedPublicationData['_file_nexist']) {
						$url = $processedPublicationData[$field];
					}
					break;
				case 'DOI':
					$url = $processedPublicationData['DOI_url'];
					break;
				default:
					$url = $processedPublicationData[$field];
			}

			if (strlen($url) > 0) {
				break;
			}
		}

		return $url;
	}


	/**
	 * Returns the file url icon
	 *
	 * @param array $unprocessedDatabaseData The unprocessed db data
	 * @param array $processedDatabaseData The processed db data
	 * @return string The html icon img tag
	 */
	public function getFileUrlIcon($unprocessedDatabaseData, $processedDatabaseData) {

		$src = strval($this->icon_src['files']['.empty_default']);
		$alt = 'default';

		// Acquire file type
		$url = '';
		if (!$processedDatabaseData['_file_nexist']) {
			$url = $unprocessedDatabaseData['file_url'];
		}
		if (strlen($url) > 0) {
			$src = $this->icon_src['files']['.default'];

			foreach ($this->icon_src['files'] as $ext => $file) {
				$len = strlen($ext);
				if (strlen($url) >= $len) {
					$sub = strtolower(substr($url, -$len));
					if ($sub == $ext) {
						$src = $file;
						$alt = substr($ext, 1);
						break;
					}
				}
			}
		}

		if (strlen($src) > 0) {
			$imageTag = '<img src="' . $src . '" alt="' . $alt . '"';
			$fileIconClass = $this->conf['enum.']['file_icon_class'];
			if (is_string($fileIconClass)) {
				$imageTag .= ' class="' . $fileIconClass . '"';
			}
			$imageTag .= '/>';
		} else {
			$imageTag = '&nbsp;';
		}

		$wrap = $this->conf['enum.']['file_icon_image.'];
		if (is_array($wrap)) {
			if (is_array($wrap['typolink.'])) {
				$title = $this->get_ll('link_get_file', 'Get file', TRUE);
				$wrap['typolink.']['title'] = $title;
			}
			$imageTag = $this->cObj->stdWrap($imageTag, $wrap);
		}

		return $imageTag;
	}

	/**
	 * Hides or reveals a publication
	 *
	 * @param bool $hide
	 * @return void
	 */
	protected function hidePublication($hide = TRUE) {
		/** @var \Ipf\Bib\Utility\ReferenceWriter $referenceWriter */
		$referenceWriter = GeneralUtility::makeInstance(\Ipf\Bib\Utility\ReferenceWriter::class);
		$referenceWriter->initialize($this->referenceReader);
		$referenceWriter->hidePublication($this->piVars['uid'], $hide);
	}

	/**
	 * This function composes the html-view of a set of publications
	 *
	 * @return string The list view
	 */
	protected function listView() {
		/** @var ListView $listView */
		$listView =  GeneralUtility::makeInstance(ListView::class);
		$listView->initialize($this);

		return $listView->render();
	}

	/**
	 * This loads the single view
	 *
	 * @return String The single view
	 */
	protected function singleView() {
		/** @var SingleView $singleView */
		$singleView = GeneralUtility::makeInstance(SingleView::class);
		$singleView->initialize($this);

		return $singleView->render();
	}


	/**
	 * This loads the editor view
	 *
	 * @return String The editor view
	 */
	protected function editorView() {
		/** @var EditorView $editorView */
		$editorView = GeneralUtility::makeInstance(EditorView::class);
		$editorView->initialize($this);

		return $editorView->render();
	}


	/**
	 * This switches to the requested dialog
	 *
	 * @return String The requested dialog
	 */
	protected function dialogView() {
		/** @var \Ipf\Bib\View\ViewInterface $dialogView */
		$dialogView = GeneralUtility::makeInstance(DialogView::class);
		$dialogView->initialize($this);

		return $dialogView->render();
	}

	/**
	 * Set global configuration values
	 */
	protected function setReferenceReaderConfiguration() {
		$this->referenceReader->set_filters($this->extConf['filters']);
		$this->referenceReader->set_searchFields($this->extConf['search_fields']);
		$this->referenceReader->set_editorStopWords($this->extConf['editor_stop_words']);
		$this->referenceReader->set_titleStopWords($this->extConf['title_stop_words']);
	}


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/bib/pi1/class.tx_bib_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/bib/pi1/class.tx_bib_pi1.php']);
}
