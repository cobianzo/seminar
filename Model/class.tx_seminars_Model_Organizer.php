<?php
/***************************************************************
* Copyright notice
*
* (c) 2009-2010 Niels Pardon (mail@niels-pardon.de)
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

/**
 * Class 'tx_seminars_Model_Organizer' for the 'seminars' extension.
 *
 * This class represents an organizer.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_Model_Organizer extends tx_oelib_Model implements tx_oelib_Interface_MailRole {
	/**
	 * Returns our name.
	 *
	 * @return string our namee, will not be empty
	 *
	 * @see EXT:oelib/Interface/tx_oelib_Interface_MailRole#getName()
	 */
	public function getName() {
		return $this->getAsString('title');
	}

	/**
	 * Sets our name.
	 *
	 * @param string our name to set, must not be empty
	 */
	public function setName($name) {
		if ($name == '') {
			throw new InvalidArgumentException('The parameter $name must not be empty.', 1333296852);
		}

		$this->setAsString('title', $name);
	}

	/**
	 * Returns our homepage.
	 *
	 * @return string our homepage, may be empty
	 */
	public function getHomepage() {
		return $this->getAsString('homepage');
	}

	/**
	 * Sets our homepage.
	 *
	 * @param string our homepage, may be empty
	 */
	public function setHomepage($homepage) {
		$this->setAsString('homepage', $homepage);
	}

	/**
	 * Returns whether this organizer has a homepage.
	 *
	 * @return boolean TRUE if this organizer has a homepage, FALSE otherwise
	 */
	public function hasHomepage() {
		return $this->hasString('homepage');
	}

	/**
	 * Returns our e-mail address.
	 *
	 * @return string our e-mail address, will not be empty
	 *
	 * @see EXT:oelib/Interface/tx_oelib_Interface_MailRole#getEMailAddress()
	 */
	public function getEMailAddress() {
		return $this->getAsString('email');
	}

	/**
	 * Sets out e-mail address.
	 *
	 * @param string our e-mail address, must not be empty
	 */
	public function setEMailAddress($eMailAddress) {
		if ($eMailAddress == '') {
			throw new InvalidArgumentException('The parameter $eMailAddress must not be empty.', 1333296861);
		}

		$this->setAsString('email', $eMailAddress);
	}

	/**
	 * Returns our e-mail footer.
	 *
	 * @return string our e-mail footer, may be empty
	 */
	public function getEMailFooter() {
		return $this->getAsString('email_footer');
	}

	/**
	 * Sets our e-mail footer.
	 *
	 * @param string our e-mail footer, may be empty
	 */
	public function setEMailFooter($eMailFooter) {
		$this->setAsString('email_footer', $eMailFooter);
	}

	/**
	 * Returns whether this organizer has an e-mail footer.
	 *
	 * @return boolean TRUE if this organizer has an e-mail footer, FALSE otherwise
	 */
	public function hasEMailFooter() {
		return $this->hasString('email_footer');
	}

	/**
	 * Returns our attendances PID.
	 *
	 * @return integer our attendances PID, will be >= 0
	 */
	public function getAttendancesPID() {
		return $this->getAsInteger('attendances_pid');
	}

	/**
	 * Sets our attendances PID.
	 *
	 * @param integer our attendances PID, must be >= 0
	 */
	public function setAttendancesPID($attendancesPID) {
		if ($attendancesPID < 0) {
			throw new InvalidArgumentException('The parameter $attendancesPID must not be < 0.', 1333296869);
		}

		$this->setAsInteger('attendances_pid', $attendancesPID);
	}

	/**
	 * Returns whether this organizer has an attendances PID.
	 *
	 * @return boolean TRUE if this organizer has an attendances PID, FALSE otherwise
	 */
	public function hasAttendancesPID() {
		return $this->hasInteger('attendances_pid');
	}

	/**
	 * Checks whether this organizer has a description.
	 *
	 * @return boolean TRUE if this organizer has a description, FALSE otherwise
	 */
	public function hasDescription() {
		return $this->hasString('description');
	}

	/**
	 * Returns the description of the organizer.
	 *
	 * @return string the description of the organizer in raw format, will be
	 *                empty if organizer has no description
	 */
	public function getDescription() {
		return $this->getAsString('description');
	}

	/**
	 * Returns our name.
	 *
	 * @return string our name, will not be empty
	 */
	public function getTitle() {
		return $this->getName();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Model/class.tx_seminars_Model_Organizer.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Model/class.tx_seminars_Model_Organizer.php']);
}
?>