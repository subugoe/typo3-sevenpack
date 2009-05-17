<?php

if ( !isset($GLOBALS['TSFE']) )
	die ('This file is no meant to be executed');


require_once ( $GLOBALS['TSFE']->tmpl->getFileName (
	'EXT:sevenpack/pi1/class.tx_sevenpack_navi.php') );


class tx_sevenpack_navi_search extends tx_sevenpack_navi  {

	public $hidden_input;

	/*
	 * Intialize
	 */
	function initialize ( $pi1 ) {
		parent::initialize( $pi1 );
		if ( is_array ( $pi1->conf['searchNav.'] ) )
			$this->conf =& $pi1->conf['searchNav.'];

		$this->extConf = array();
		if ( is_array ( $pi1->extConf['search_navi'] ) )
			$this->extConf =& $pi1->extConf['search_navi'];


		$this->hidden_input = array();
		$this->pref = 'SEARCH_NAVI';
		$this->load_template ( '###SEARCH_NAVI_BLOCK###' );
	}


	/*
	 * Returns content
	 */
	function get ( ) {
		$cObj =& $this->pi1->cObj;
		$con = '';

		$cfg =& $this->conf;
		$extConf =& $this->extConf;

		// The data
		$charset = $this->pi1->extConf['charset']['upper'];

		// The label
		$label = $this->pi1->get_ll ( 'searchNav_label' );
		$label = $cObj->stdWrap ( $label, $cfg['label.'] );

		// Form start
		$attribs = array ( 
			'search' => ''
		);
		$txt = '';
		$txt .= '<form name="'.$this->pi1->prefix_pi1.'-search_form" ';
		$txt .= 'action="'.$this->pi1->get_link_url ( $attribs, FALSE ).'"';
		$txt .= ' method="post"';
		$txt .= strlen ( $cfg['form_class'] ) ? ' class="'.$cfg['form_class'].'"' : '';
		$txt .= '>' . "\n";
		$form_start = $txt;

		//
		// The search bar
		//
		$lcfg =& $cfg['search.'];
		$attribs = array ( 'size' => 42, 'maxlength' => 1024 );

		$value = '';
		if ( strlen ( $this->extConf['string'] ) > 0 ) {
			$value = htmlspecialchars ( $extConf['string'], ENT_QUOTES, $charset );
		}
		$btn = tx_sevenpack_utility::html_text_input (
			$this->pi1->prefix_pi1.'[search][text]', $value, $attribs
		);
		$btn = $cObj->stdWrap ( $btn, $lcfg['input.'] );
		$sea = $btn;

		// The search button
		$txt = $this->pi1->get_ll ( 'searchNav_search' );

		$attribs = array ();
		if ( strlen ( $lcfg['search_btn_class'] ) > 0 )
			$attribs['class'] = $lcfg['search_btn_class'];
		$btn = tx_sevenpack_utility::html_submit_input ( 
			$this->pi1->prefix_pi1.'[action][search]', $txt, $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['search_btn.'] );
		$sea .= $btn;


		// The clear button
		$txt = $this->pi1->get_ll ( 'searchNav_clear' );

		$attribs = array ();
		if ( strlen ( $lcfg['clear_btn_class'] ) > 0 )
			$attribs['class'] = $lcfg['clear_btn_class'];
		$btn = tx_sevenpack_utility::html_submit_input ( 
			$this->pi1->prefix_pi1.'[action][clear_search]', $txt, $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['clear_btn.'] );
		$sea .= $btn;

		// Search widget wrap
		$sea = $cObj->stdWrap ( $sea, $lcfg['widget.'] );


		//
		// The extra check
		//
		$lcfg =& $cfg['extra.'];
		$txt = $this->pi1->get_ll ( 'searchNav_extra' );
		$txt = $cObj->stdWrap ( $txt, $lcfg['label.'] );

		$attribs = array (
			'onchange' => 'this.form.submit()'
		);
		if ( strlen ( $lcfg['btn_class'] ) > 0 )
			$attribs['class'] = $lcfg['btn_class'];
		$btn = tx_sevenpack_utility::html_check_input ( 
			$this->pi1->prefix_pi1.'[search][extra]', '1', $extConf['extra'], $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['btn.'] );

		$extra = $cObj->stdWrap ( $txt . $btn, $lcfg['widget.'] );

		// Append hidden input
		$this->append_hidden( 'extra_b', TRUE );
		if ( !$extConf['extra'] ) {
			$this->append_hidden( 'abstracts', $extConf['abstracts'] );
		}

		// End of form
		$form_end = implode ( "\n", $this->hidden_input );
		$form_end .= '</form>';


		$trans = array();
		$trans['###NAVI_LABEL###'] = $label;
		$trans['###FORM_START###'] = $form_start;
		$trans['###SEARCH_BAR###'] = $sea;
		$trans['###EXTRA_BTN###'] = $extra;
		$trans['###FORM_END###'] = $form_end;
		if ( $extConf['extra'] ) {
			$this->get_extra ( $trans );
		}

		$has_extra = $extConf['extra'] ? array ( '', '' ) : '';

		$tmpl = $this->pi1->enum_condition_block ( $this->template );
		$tmpl = $cObj->substituteSubpart ( $tmpl, '###HAS_EXTRA###', $has_extra );
		$con = $cObj->substituteMarkerArrayCached ( $tmpl, $trans );

		return $con;
	}


	function get_extra ( &$trans ) {
		$cObj =& $this->pi1->cObj;
		$cfg =& $this->conf;
		$extConf =& $this->extConf;

		//
		// The abstract check
		//
		$lcfg =& $cfg['abstracts.'];
		$txt = $this->pi1->get_ll ( 'searchNav_abstract' );
		$txt = $cObj->stdWrap ( $txt, $lcfg['label.'] );

		$attribs = array (
			'onchange' => 'this.form.submit()'
		);
		if ( strlen ( $lcfg['btn_class'] ) > 0 )
			$attribs['class'] =  $lcfg['btn_class'];
		$btn = tx_sevenpack_utility::html_check_input ( 
			$this->pi1->prefix_pi1.'[search][abstracts]', '1', 
			$extConf['abstracts'], $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['btn.'] );

		$abstr = $cObj->stdWrap ( $txt . $btn, $lcfg['widget.'] );


		//
		// The full text check
		//
		$lcfg =& $cfg['full_text.'];
		$txt = $this->pi1->get_ll ( 'searchNav_full_text' );
		$txt = $cObj->stdWrap ( $txt, $lcfg['label.'] );

		$attribs = array (
			'onchange' => 'this.form.submit()'
		);
		if ( strlen ( $lcfg['btn_class'] ) > 0 )
			$attribs['class'] =  $lcfg['btn_class'];
		$btn = tx_sevenpack_utility::html_check_input ( 
			$this->pi1->prefix_pi1.'[search][full_text]', '1', 
			$extConf['full_text'], $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['btn.'] );

		$full_txt = $cObj->stdWrap ( $txt . $btn, $lcfg['widget.'] );


		//
		// The separator selection
		//
		$lcfg =& $cfg['separator.'];
		$txt = $this->pi1->get_ll ( 'searchNav_separator' );
		$txt = $cObj->stdWrap ( $txt, $lcfg['label.'] );

		$types = array ( 'space', 'semi', 'pipe' );
		$pairs = array (
			'none' => $this->pi1->get_ll ( 'searchNav_sep_none' . $type ),
			'space' => '&nbsp;', 'semi' => ';', 'pipe' => '|'
		);
		foreach ( $types as $type ) {
			$pairs[$type] .= ' (' . 
				$this->pi1->get_ll ( 'searchNav_sep_' . $type ) . ')';
		}

		$val = 'none';
		switch ( $extConf['separator'] ) {
			case ' ': $val = 'space'; break;
			case ';': $val = 'semi'; break;
			case '|': $val = 'pipe'; break;
		}

		$attribs = array (
			'name'     => $this->pi1->prefix_pi1.'[search][sep]',
			'onchange' => 'this.form.submit()'
		);
		if ( strlen ( $lcfg['select_class'] ) > 0 )
			$attribs['class'] = $lcfg['select_class'];
		$btn = tx_sevenpack_utility::html_select_input ( 
			$pairs, $val, $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['select.'] );

		$sep = $cObj->stdWrap ( $txt . $btn, $lcfg['widget.'] );


		//
		// The rule selection
		//
		$lcfg =& $cfg['rule.'];
		$txt = $this->pi1->get_ll ( 'searchNav_rule' );
		$txt = $cObj->stdWrap ( $txt, $lcfg['label.'] );

		$pairs = array (
			'OR' => $this->pi1->get_ll ( 'searchNav_OR' ),
			'AND' => $this->pi1->get_ll ( 'searchNav_AND' ),
		);

		$val = $extConf['rule'] ? 'AND' : 'OR';

		$attribs = array (
			'name'     => $this->pi1->prefix_pi1.'[search][rule]',
			'onchange' => 'this.form.submit()'
		);
		if ( strlen ( $lcfg['select_class'] ) > 0 )
			$attribs['class'] = $lcfg['select_class'];
		$btn = tx_sevenpack_utility::html_select_input ( 
			$pairs, $val, $attribs );
		$btn = $cObj->stdWrap ( $btn, $lcfg['select.'] );

		$rule = $cObj->stdWrap ( $txt . $btn, $lcfg['widget.'] );

		// Setup the translator
		$trans['###ABSTRACTS_BTN###'] = $abstr;
		$trans['###FULL_TEXT_BTN###'] = $full_txt;
		$trans['###SEPARATOR_SEL###'] = $sep;
		$trans['###RULE_SEL###'] = $rule;
	}


	function append_hidden ( $key, $val, $bool = TRUE ) {
		if ( $bool ) $val = $val ? '1' : '0';
		$this->hidden_input[] = tx_sevenpack_utility::html_hidden_input (
			$this->pi1->prefix_pi1.'[search]['.$key.']', $val );
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sevenpack/pi1/class.tx_sevenpack_navi_search.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/sevenpack/pi1/class.tx_sevenpack_navi_search.php']);
}

?>
