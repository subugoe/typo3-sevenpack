
<?php
defined('TYPO3_MODE') or die();
return [
    'ctrl' => [
        'title' => 'LLL:EXT:bib/Resources/Private/Language/locallang_db.xml:tx_bib_domain_model_authorships',
        'label' => 'pub_id',
        'label_userFunc' => 'Ipf\\Bib\\Utility\\LabelUtility->getAuthorshipLabel',
        'label_alt_force' => 1,
        'default_sortby' => 'ORDER BY pub_id DESC, sorting ASC',
        'delete' => 'deleted',
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('bib') . 'Resources/Public/Icons/icon_tx_bib_domain_model_reference.png',
    ],
    'interface' => [
        'showRecordFieldList' => 'pub_id,author_id,sorting',
    ],
    'feInterface' => [
        'fe_admin_fieldList' => 'pub_id,author_id,sorting',
    ],
    'columns' => [
        'pub_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:bib/Resources/Private/Language/locallang_db.xml:tx_bib_domain_model_authorships_pub_id',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_bib_domain_model_reference',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'selectedListStyle' => 'width:320px;',
                'wizards' => [
                    '_VALIGN' => 'top',
                    'ajax_search' => [
                        'type' => 'userFunc',
                        'userFunc' => 'tx_ajaxgroupsearch_client->renderAjaxSearch',
                        'params' => [
                            'client' => ['startLength' => 2],
                            'wrapStyle' => 'z-index:80;',
                            'inputStyle' => 'width:200px;',
                            'itemListStyle' => 'width:320px;',
                            'tables' => [
                                'tx_bib_domain_model_reference' => [
                                    'searchBySQL' => [
                                        'fields' => 'r.title, r.uid',
                                        'tables' => 'tx_bib_domain_model_reference AS r',
                                        'where' => 'r.title LIKE "%###SEARCHWORD###%" AND r.deleted=0 AND r.hidden=0',
                                        'group_by' => '',
                                        'order_by' => 'r.title DESC',
                                        'limit' => '10',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'author_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:bib/Resources/Private/Language/locallang_db.xml:tx_bib_domain_model_authorships_author_id',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_bib_domain_model_author',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'selectedListStyle' => 'width:320px;',
                'wizards' => [
                    '_VALIGN' => 'top',
                    'ajax_search' => [
                        'type' => 'userFunc',
                        'userFunc' => 'tx_ajaxgroupsearch_client->renderAjaxSearch',
                        'params' => [
                            'client' => ['startLength' => 2],
                            'wrapStyle' => 'z-index:80;',
                            'inputStyle' => 'width:200px;',
                            'itemListStyle' => 'width:320px;',
                            'tables' => [
                                'tx_bib_domain_model_author' => [
                                    'searchBySQL' => [
                                        'fields' => 'a.surname, a.uid',
                                        'tables' => 'tx_bib_domain_model_author AS a',
                                        'where' => 'a.surname LIKE "%###SEARCHWORD###%" AND a.deleted=0 AND a.hidden=0',
                                        'group_by' => '',
                                        'order_by' => 'a.surname DESC, a.forename DESC',
                                        'limit' => '10',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'sorting' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:bib/Resources/Private/Language/locallang_db.xml:tx_bib_domain_model_authorships_sorting',
            'config' => [
                'type' => 'input',
                'size' => '10',
                'max' => '255',
                'eval' => 'int',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'pub_id,author_id,sorting'],
    ],
];
