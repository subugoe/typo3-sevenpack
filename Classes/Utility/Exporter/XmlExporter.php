<?php
namespace Ipf\Bib\Utility\Exporter;

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

class XmlExporter extends Exporter {

	/**
	 * @var array
	 */
	public $pattern = array();

	/**
	 * @var array
	 */
	public $replacement = array();

	/**
	 * @param \tx_bib_pi1 $pi1
	 * @return void
	 */
	public function initialize($pi1) {

		parent::initialize($pi1);

		$this->pattern[] = '/&/';
		$this->replacement[] = '&amp;';
		$this->pattern[] = '/</';
		$this->replacement[] = '&lt;';
		$this->pattern[] = '/>/';
		$this->replacement[] = '&gt;';

		$this->file_name = $this->pi1->extKey . '_' . $this->filter_key . '.xml';
	}

	/**
	 * @param $pub
	 * @param array $infoArr
	 * @return string
	 */
	public function export_format_publication($pub, $infoArr = array()) {
		$content = '';

		$pi1 =& $this->pi1;

		$charset = $pi1->extConf['charset']['lower'];

		if ($charset != 'utf-8') {
			$pub = $this->referenceReader->change_pub_charset($pub, $charset, 'utf-8');
		}

		$content .= '<reference>' . "\n";

		$entries = array();
		foreach ($this->referenceReader->pubFields as $key) {
			$value = '';
			$append = TRUE;

			switch ($key) {
				case 'authors':
					$value = $pub['authors'];
					if (sizeof($value) == 0)
						$append = FALSE;
					break;
				default:
					$value = trim($pub[$key]);
					if ((strlen($value) == 0) || ($value == '0'))
						$append = FALSE;
			}

			if ($append) {
				$content .= $this->xmlFormatField($key, $value);
			}
		}

		$content .= '</reference>' . "\n";

		return $content;
	}

	/**
	 * @param string $value
	 * @return mixed
	 */
	protected function xmlFormatString($value) {
		$value = preg_replace($this->pattern, $this->replacement, $value);
		return $value;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	protected function xmlFormatField($key, $value) {
		$content = '';
		switch ($key) {
			case 'authors':
				$authors = is_array($value) ? $value : explode(' and ', $value);
				$value = '';
				$aXML = array();
				foreach ($authors as $author) {
					$a_str = '';
					$foreName = $this->xmlFormatString($author['forename']);
					$surName = $this->xmlFormatString($author['surname']);
					if (strlen($foreName))
						$a_str .= '<fn>' . $foreName . '</fn>';
					if (strlen($surName))
						$a_str .= '<sn>' . $surName . '</sn>';
					if (strlen($a_str))
						$aXML[] = $a_str;
				}
				if (sizeof($aXML)) {
					$value .= "\n";
					foreach ($aXML as $author) {
						$value .= '<person>' . $author . '</person>' . "\n";
					}
				}
				break;
			case 'bibtype':
				$value = $this->referenceReader->allBibTypes[$value];
				$value = $this->xmlFormatString($value);
				break;
			case 'state':
				$value = $this->referenceReader->allStates[$value];
				$value = $this->xmlFormatString($value);
				break;
			default:
				$value = $this->xmlFormatString($value);
		}
		$content .= '<' . $key . '>' . $value . '</' . $key . '>' . "\n";

		return $content;
	}

	/**
	 * @param array $infoArr
	 * @return string
	 */
	protected function file_intro($infoArr = array()) {
		$content = '';
		$content .= '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		$content .= '<bib>' . "\n";
		$content .= '<comment>' . "\n";
		$content .= $this->xmlFormatString($this->info_text($infoArr));
		$content .= '</comment>' . "\n";
		return $content;
	}

	/**
	 * @param array $infoArr
	 * @return string
	 */
	protected function file_outtro($infoArr = array()) {
		$content = '';
		$content .= '</bib>' . "\n";
		return $content;
	}

}

?>