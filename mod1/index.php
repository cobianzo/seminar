<?php
/***************************************************************
* Copyright notice
*
* (c) 2005-2010 Oliver Klee (typo3-coding@oliverklee.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

unset($MCONF);
$MCONF = array();
require_once('conf.php');
require_once($BACK_PATH . 'init.php');
require_once($BACK_PATH . 'template.php');
$LANG->includeLLFile('EXT:seminars/mod1/locallang.xml');

require_once(PATH_t3lib . 'class.t3lib_scbase.php');
require_once(PATH_t3lib . 'class.t3lib_page.php');

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_Autoloader.php');

require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');

// This checks permissions and exits if the users has no permission for entry.
$BE_USER->modAccess($MCONF, 1);

/**
 * Module 'Seminars' for the 'seminars' extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_seminars_module1 extends t3lib_SCbase {
	private $pageinfo;

	/**
	 * @return [type] ...
	 */
	public function init() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		$this->id = intval($this->id);
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return [type] ...
	 */
	public function menuConfig() {
		global $LANG, $BE_USER;

		$functionMenu = array();

		$functionMenu['seminarDetails'] = $LANG->getLL('menu_seminarDetails');

		$this->MOD_MENU = array(
			'function' => $functionMenu
		);

		parent::menuConfig();
	}

	// If you chose 'web' as main module, you will need to consider the
	// $this->id parameter which will contain the uid-number of the page
	// clicked in the page tree
	/**
	 * Main function of the module. Writes the content to $this->content
	 *
	 * @return [type] ...
	 */
	public function main() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		$this->tableUsers = 'fe_users';

		try {
			// Access check!
			// The page will show only if there is a valid page and if this page
			// may be viewed by the user
			$this->pageinfo = t3lib_BEfunc::readPageAccess(
				$this->id,
				$this->perms_clause
			);
			$access = is_array($this->pageinfo) ? 1 : 0;

			$dbResult = $GLOBALS['TYPO3_DB']->sql_query(
				'(SELECT COUNT(*) AS num FROM '.SEMINARS_TABLE_SEMINARS
					.' WHERE deleted=0 AND pid='.$this->id.') UNION '
					.'(SELECT COUNT(*) AS num FROM '.SEMINARS_TABLE_ATTENDANCES
					.' WHERE deleted=0 AND pid='.$this->id.')'
			);
			if ($dbResult) {
				$dbResultRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
				$numberOfRecordsOnCurrentPage = $dbResultRow['num'];
			} else {
				$numberOfRecordsOnCurrentPage = 0;
			}

			if ($this->id && ($access || $BE_USER->user['admin'])
				&& ($numberOfRecordsOnCurrentPage)) {
				// Draw the header.
				$this->doc = t3lib_div::makeInstance('mediumDoc');
				$this->doc->backPath = $BACK_PATH;
				$this->doc->form='<form action="" method="post">';

				// JavaScript
				$this->doc->JScode = '
					<script type="text/javascript">/*<![CDATA[*/
						script_ended = 0;
						function jumpToUrl(URL) {
							document.location = URL;
						}
					/*]]>*/</script>
				';
				$this->doc->postCode='
					<script type="text/javascript">/*<![CDATA[*/
						script_ended = 1;
						if (top.fsMod) top.fsMod.recentIds["web"] = '
						.intval($this->id).';
					/*]]>*/</script>
				';

				$headerSection = $this->doc->getHeader(
					'pages',
					$this->pageinfo,
					$this->pageinfo['_thePath'])
						. '<br>'
						. $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path')
						. ': '
						. t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'], -50);

				$this->content.=$this->doc->startPage($LANG->getLL('title'));
				$this->content.=$this->doc->header($LANG->getLL('title'));
				$this->content.=$this->doc->spacer(5);
				$this->content.=$this->doc->section(
					'',
					$this->doc->funcMenu(
						$headerSection,
						t3lib_BEfunc::getFuncMenu(
							$this->id,
							'SET[function]',
							$this->MOD_SETTINGS['function'],
							$this->MOD_MENU['function']
						)
					)
				);
				$this->content.=$this->doc->divider(5);

				// Render content:
				$this->moduleContent();

				// ShortCut
				if ($BE_USER->mayMakeShortcut()) {
					$this->content.=$this->doc->spacer(20)
					.$this->doc->section(
						'',
						$this->doc->makeShortcutIcon(
							'id',
							implode(',',array_keys($this->MOD_MENU)),
							$this->MCONF['name']
						)
					);
				}

				$this->content.=$this->doc->spacer(10);
			} else {
				// Either the user has no acces, the page ID is zero or there
				// are no seminar or attendance records on the current page.
				$this->doc = t3lib_div::makeInstance('mediumDoc');
				$this->doc->backPath = $BACK_PATH;

				$this->content.=$this->doc->startPage($LANG->getLL('title'));
				$this->content.=$this->doc->header($LANG->getLL('title'));
				$this->content.=$this->doc->spacer(5);
				$this->content.=$this->doc->spacer(10);
			}
		} catch (Exception $exception) {
			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->content .= $this->doc->spacer(5);
			$this->content .= '<p style="border: 2px solid red; padding: 1em; ' .
				'font-weight: bold;">' . LF	.
				htmlspecialchars($exception->getMessage()) . LF .
				'<br /><br />' . LF .
				nl2br(htmlspecialchars($exception->getTraceAsString())) . LF .
				'</p>' . LF;
		}
	}

	/**
	 * Prints out the module HTML.
	 */
	public function printContent() {
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content.
	 */
	private function moduleContent() {
		global $LANG;

		switch ((string)$this->MOD_SETTINGS['function']) {
			case 'seminarDetails':
				$content = $this->listSeminarDetails();
				$this->content.=$this->doc->section(
					$LANG->getLL('menu_seminarDetails'),
					$content,
					0,
					1
				);
				break;
			default:
				// Do nothing.
				break;
		}
	}

	/**
	 * Returns a list of the e-mail addresses of the registered attendees.
	 *
	 * @return string HTML output (content of the module).
	 */
	private function listSeminarDetails() {
		// initialize the localization functionality
		global $LANG;

		$result = '';

		$result .= '<h3>'
			.$LANG->getLL('title_getEmailAddressesForAttendances').'</h3>';

		$builder = tx_oelib_ObjectFactory::make('tx_seminars_seminarbagbuilder');
		$builder->setBackEndMode();
		$builder->setSourcePages(intval($this->id));
		$seminarBag = $builder->build();

		foreach ($seminarBag as $seminar) {
			$result .= '<h4>' . $seminar->getTitleAndDate() . '</h4>';
			$seminarQuery = SEMINARS_TABLE_ATTENDANCES . '.seminar=' .
				$seminar->getUid();

			$result .= $LANG->getLL('label_all') . '<br />' .
				$this->generateEmailList($seminarQuery) . '<hr />';
			$result .= $LANG->getLL('label_paid') . '<br />' .
				$this->generateEmailList(
					$seminarQuery . ' AND datepaid != 0'
				) . '<hr />';
			$result .= $LANG->getLL('label_unpaid') . '<br />' .
				$this->generateEmailList(
					$seminarQuery . ' AND datepaid = 0'
				) . '<hr />';
		}

		$result .= $seminarBag->checkConfiguration();
		$seminarBag->__destruct();

		return $result;
	}

	/**
	 * Returns a comma separated list of names and e-mail addresses.
	 *
	 * @param string string that will be prepended to the WHERE clause
	 *               using AND, e.g. 'pid=42' (the AND and the enclosing
	 *               spaces are not necessary for this parameter)
	 *
	 * @return string a comma-separated list of names and e-mail addresses
	 *                or a localized messages if there are no registration
	 *                records
	 */
	private function generateEmailList($queryParameters) {
		// initialize the localization functionality
		global $LANG;

		$result = '';
		$emailList = '';
		$dividerInEmailList = ', ';

		$registrationBag = tx_oelib_ObjectFactory::make(
			'tx_seminars_registrationbag', $queryParameters, '', '', 'crdate'
			);

		if ($registrationBag->current()) {
			foreach ($registrationBag as $registration) {
				$currentEmail = htmlspecialchars(
					$registration->getUserNameAndEmail()
				);
				if (empty($emailList)) {
					$emailList = $currentEmail;
				} else {
					$emailList .= $dividerInEmailList . ' ' . $currentEmail;
				}
			}
			$result .= $emailList;
		} else {
			// Display a message if no attendances are found for this seminar.
			$result .= $LANG->getLL('msg_noAttendancesFound');
		}

		$result .= $registrationBag->checkConfiguration();

		return $result;
	}
} // END of class

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/mod1/index.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/mod1/index.php']);
}

// Make instance:
$SOBE = tx_oelib_ObjectFactory::make('tx_seminars_module1');
$SOBE->init();

// Include files?
foreach ($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();
?>