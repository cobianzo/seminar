<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2010 Oliver Klee (typo3-coding@oliverklee.de)
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

require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');

/**
 * Class 'tx_seminars_category' for the 'seminars' extension.
 *
 * This class represents an event category.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_seminars_category extends tx_seminars_objectfromdb {
	/**
	 * @var string the name of the SQL table this class corresponds to
	 */
	protected $tableName = SEMINARS_TABLE_CATEGORIES;

	/**
	 * Returns the icon of this category.
	 *
	 * @return string the file name of the icon (relative to the extension
	 *                upload path) of the category, will be empty if the
	 *                category has no icon
	 */
	public function getIcon() {
		return $this->getRecordPropertyString('icon');
	}

	/**
	 * Returns our owner.
	 *
	 * @return tx_seminars_Model_FrontEndUser the owner of this model, will be null
	 *                                     if this model has no owner
	 */
	public function getOwner() {
		if (!$this->hasRecordPropertyInteger('owner')) {
			return null;
		}

		return tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_FrontEndUser'
		)->find($this->getRecordPropertyInteger('owner'));
	}

	/**
	 * Sets our owner.
	 *
	 * @param tx_seminars_Model_FrontEndUser $frontEndUser the owner of this model
	 *                                                  to set
	 */
	public function setOwner(tx_seminars_Model_FrontEndUser $frontEndUser) {
		$this->setRecordPropertyInteger('owner', $frontEndUser->getUid());
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_category.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_category.php']);
}
?>