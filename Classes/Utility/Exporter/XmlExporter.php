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

/**
 * Class XmlExporter.
 */
class XmlExporter extends Exporter
{
    /**
     * @var array
     */
    protected $pattern = [];

    /**
     * @var array
     */
    protected $replacement = [];

    /**
     * @param \tx_bib_pi1 $pi1
     */
    public function initialize($pi1)
    {
        parent::initialize($pi1);

        $this->pattern[] = '/&/';
        $this->replacement[] = '&amp;';
        $this->pattern[] = '/</';
        $this->replacement[] = '&lt;';
        $this->pattern[] = '/>/';
        $this->replacement[] = '&gt;';

        $this->setFileName($this->pi1->extKey . '_' . $this->filterKey . '.xml');
    }

    /**
     * @param $publication
     * @param array $infoArr
     *
     * @return string
     */
    protected function formatPublicationForExport($publication, $infoArr = [])
    {
        $charset = $this->pi1->extConf['charset']['lower'];

        if ($charset != 'utf-8') {
            $publication = $this->getReferenceReader()->change_pub_charset($publication, $charset, 'utf-8');
        }

        $content = '<reference>' . PHP_EOL;

        foreach ($this->getReferenceReader()->getPublicationFields() as $key) {
            $append = true;

            switch ($key) {
                case 'authors':
                    $value = $publication['authors'];
                    if (sizeof($value) == 0) {
                        $append = false;
                    }
                    break;
                default:
                    $value = trim($publication[$key]);
                    if ((strlen($value) == 0) || ($value == '0')) {
                        $append = false;
                    }
            }

            if ($append) {
                $content .= $this->xmlFormatField($key, $value);
            }
        }

        $content .= '</reference>' . PHP_EOL;

        return $content;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    protected function xmlFormatField($key, $value)
    {
        $content = '';
        switch ($key) {
            case 'authors':
                $authors = is_array($value) ? $value : explode(' and ', $value);
                $value = '';
                $aXML = [];
                foreach ($authors as $author) {
                    $a_str = '';
                    $foreName = $this->xmlFormatString($author['forename']);
                    $surName = $this->xmlFormatString($author['surname']);
                    if (strlen($foreName)) {
                        $a_str .= '<fn>' . $foreName . '</fn>';
                    }
                    if (strlen($surName)) {
                        $a_str .= '<sn>' . $surName . '</sn>';
                    }
                    if (strlen($a_str)) {
                        $aXML[] = $a_str;
                    }
                }
                if (sizeof($aXML)) {
                    $value .= PHP_EOL;
                    foreach ($aXML as $author) {
                        $value .= '<person>' . $author . '</person>' . PHP_EOL;
                    }
                }
                break;
            case 'bibtype':
                $value = $this->getReferenceReader()->allBibTypes[$value];
                $value = $this->xmlFormatString($value);
                break;
            case 'state':
                $value = $this->getReferenceReader()->allStates[$value];
                $value = $this->xmlFormatString($value);
                break;
            default:
                $value = $this->xmlFormatString($value);
        }
        $content .= '<' . $key . '>' . $value . '</' . $key . '>' . PHP_EOL;

        return $content;
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected function xmlFormatString($value)
    {
        $value = preg_replace($this->pattern, $this->replacement, $value);

        return $value;
    }

    /**
     * @param array $infoArr
     *
     * @return string
     */
    protected function fileIntro($infoArr = [])
    {
        $content = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
        $content .= '<bib>' . PHP_EOL;
        $content .= '<comment>' . PHP_EOL;
        $content .= $this->xmlFormatString($this->getGeneralInformationText($infoArr));
        $content .= '</comment>' . PHP_EOL;

        return $content;
    }

    /**
     * @param array $infoArr
     *
     * @return string
     */
    protected function fileOutro($infoArr = [])
    {
        $content = '</bib>' . PHP_EOL;

        return $content;
    }
}
