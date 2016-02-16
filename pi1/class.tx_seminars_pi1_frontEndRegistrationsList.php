<?php
/***************************************************************
* Copyright notice
*
* (c) 2008-2010 Niels Pardon (mail@niels-pardon.de)
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

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_headerProxyFactory.php');

/**
 * Class 'frontEndRegistrationsList' for the 'seminars' extension.
 *
 * This class represents a list of registrations for the front-end.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_pi1_frontEndRegistrationsList extends tx_seminars_pi1_frontEndView {
	/**
	 * @var string path to this script relative to the extension dir
	 */
	public $scriptRelPath = 'pi1/class.tx_seminars_pi1_frontEndRegistrationsList.php';

	/**
	 * @var tx_seminars_seminar the seminar of which we want to list the
	 *                          registrations
	 */
	private $seminar = null;

	/**
	 * The constructor.
	 *
	 * @param array TypoScript configuration for the plugin, may be empty
	 * @param string a string selecting the flavor of the list view, either
	 *               "list_registrations" or "list_vip_registrations"
	 * @param integer the UID of the seminar of which we want to list the
	 *                registrations, invalid UIDs will be handled later
	 * @param tslib_cObj the parent cObj, needed for the flexforms
	 */
	public function __construct(
		array $configuration, $whatToDisplay, $seminarUid, tslib_cObj $cObj
	) {
		if (($whatToDisplay != 'list_registrations')
			&& ($whatToDisplay != 'list_vip_registrations')
		) {
			throw new InvalidArgumentException(
				'The value "' . $whatToDisplay . '" of the first parameter $whatToDisplay is not valid.', 1333293210
			);
		}
		$this->whatToDisplay = $whatToDisplay;

		parent::__construct($configuration, $cObj);

		$this->createSeminar($seminarUid);
	}

	/**
	 * The destructor.
	 */
	public function __destruct() {
		if ($this->seminar) {
			$this->seminar->__destruct();
			unset($this->seminar);
		}

		parent::__destruct();
	}

	/**
	 * Creates a seminar in $this->seminar.
	 *
	 * @param integer an event UID, invalid UIDs will be handled later
	 */
	private function createSeminar($seminarUid) {
		$this->seminar = tx_oelib_ObjectFactory::make(
			'tx_seminars_seminar', $seminarUid
		);
	}

	/**
	 * Creates a list of registered participants for an event.
	 * If there are no registrations yet, a localized message is displayed instead.
	 *
	 * @return string HTML code for the list, will not be empty
	 */
	public function render() {
		$errorMessage = '';
		$isOkay = FALSE;

		if ($this->seminar->isOk()) {
			// Okay, at least the seminar UID is valid so we can show the
			// seminar title and date.
			$this->setMarker('title', htmlspecialchars($this->seminar->getTitleAndDate()));

			// Lets warnings from the seminar bubble up to us.
			$this->setErrorMessage($this->seminar->checkConfiguration(TRUE));

			if ($this->seminar->canViewRegistrationsList(
				$this->whatToDisplay,
				0,
				0,
				$this->getConfValueInteger(
					'defaultEventVipsFeGroupID', 's_template_special'
				)
			)) {
				$isOkay = TRUE;
			} else {
				$errorMessage = $this->seminar->canViewRegistrationsListMessage(
					$this->whatToDisplay
				);
				tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader(
					'Status: 403 Forbidden'
				);
			}
		} else {
			$errorMessage = $this->translate('message_wrongSeminarNumber');
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader(
				'Status: 404 Not Found'
			);
			$this->setMarker('title', '');
		}

		if ($isOkay) {
			$this->hideSubparts('error', 'wrapper');
			$this->createRegistrationsList();
		} else {
			$this->setMarker('error_text', $errorMessage);
			$this->hideSubparts('registrations_list_message', 'wrapper');
			$this->hideSubparts('registrations_list_table', 'wrapper');
		}

		$this->setMarker('backlink',
			$this->cObj->getTypoLink(
				$this->translate('label_back'),
				$this->getConfValueInteger('listPID')
			)
		);

		$result = $this->getSubpart('REGISTRATIONS_LIST_VIEW');

		$this->checkConfiguration();
		$result .= $this->getWrappedConfigCheckMessage();

		return $result;
	}

	/**
	 * Creates the registration list (sorted by creation date) and fills in the
	 * corresponding subparts.
	 * If there are no registrations, a localized message is filled in instead.
	 *
	 * Before this function can be called, it must be ensured that $this->seminar
	 * is a valid seminar object.
	 */
	private function createRegistrationsList() {
		$builder = $this->createRegistrationBagBuilder();
		$builder->limitToRegular();

		$regularRegistrations = $builder->build();
		if (!$regularRegistrations->isEmpty()) {
			$this->setSubpart(
				'registrations_list_table_head_item',
				$this->createTableHeader(),
				'wrapper'
			);

			$tableRows = '';
			foreach ($regularRegistrations as $registration) {
				$cellContents = array();
				foreach ($this->getFrontEndUserFields() as $field) {
					$cellContents[] = $registration->getUserData($field);
				}
				foreach ($this->getRegistrationFields() as $field) {
					$cellContents[] =  $registration->getRegistrationData($field);
				}

				$tableCells = '';
				foreach ($cellContents as $cellContent) {
					$this->setMarker(
						'registrations_list_item', htmlspecialchars($cellContent)
					);
					$tableCells .= $this->getSubpart(
						'WRAPPER_REGISTRATIONS_LIST_TABLE_BODY_ITEM'
					);
				}

				$this->setMarker(
					'registrations_list_row',
					$tableCells
				);
				$tableRows .= $this->getSubpart(
					'WRAPPER_REGISTRATIONS_LIST_TABLE_BODY'
				);
			}

			$this->setSubpart(
				'registrations_list_table_body', $tableRows, 'wrapper'
			);
			$this->hideSubparts('registrations_list_message', 'wrapper');
		} else {
			$this->hideSubparts('registrations_list_table', 'wrapper');
			$this->setMarker(
				'message_no_registrations',
				$this->translate('message_noRegistrations')
			);
		}

		// Lets warnings from the registration bag bubble up to us.
		$this->setErrorMessage($regularRegistrations->checkConfiguration(TRUE));

		$regularRegistrations->__destruct();
		unset($registrationBag, $builder);
	}

	/**
	 * Creates a registration bag builder that will find all registrations
	 * (regular and on the queue) for the event in $this->seminar, ordered by
	 * creation date.
	 *
	 * @return tx_seminars_registrationBagBuilder the bag builder
	 */
	private function createRegistrationBagBuilder() {
		$builder = tx_oelib_ObjectFactory::make(
			'tx_seminars_registrationBagBuilder'
		);
		$builder->limitToEvent($this->seminar->getUid());
		$builder->limitToExistingUsers();
		$builder->setOrderBy('crdate');

		return $builder;
	}

	/**
	 * Creates the table header.
	 *
	 * @return string the table header HTML, will not be empty
	 */
	private function createTableHeader() {
		$labelKeys = array();
		foreach ($this->getFrontEndUserFields() as $field) {
			$labelKeys[] = 'label_' . $field;
		}
		foreach ($this->getRegistrationFields() as $field) {
			if ($field == 'uid') {
				$field = 'registration_' . $field;
			}
			$labelKeys[] = 'label_' . $field;
		}

		$tableHeader = '';
		foreach ($labelKeys as $labelKey) {
			$this->setMarker(
				'registrations_list_header',
				$this->translate($labelKey)
			);
			$tableHeader .= $this->getSubpart(
				'WRAPPER_REGISTRATIONS_LIST_TABLE_HEAD_ITEM'
			);
		}

		return $tableHeader;
	}

	/**
	 * Gets the keys of the front-end user fields that should be displayed in
	 * the list.
	 *
	 * @return array keys of the front-end user fields to display, might be empty
	 */
	private function getFrontEndUserFields() {
		return t3lib_div::trimExplode(
			',',
			$this->getConfValueString(
				'showFeUserFieldsInRegistrationsList', 's_template_special'
			),
			TRUE
		);
	}

	/**
	 * Gets the keys of the registration fields that should be displayed in
	 * the list.
	 *
	 * @return array keys of the registration fields to display, might be empty
	 */
	private function getRegistrationFields() {
		return t3lib_div::trimExplode(
			',',
			$this->getConfValueString(
				'showRegistrationFieldsInRegistrationList', 's_template_special'
			),
			TRUE
		);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_frontEndRegistrationsList.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_frontEndRegistrationsList.php']);
}
?>