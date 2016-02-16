<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2010 Niels Pardon (mail@niels-pardon.de)
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
 * Class 'registrations list' for the 'seminars' extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class tx_seminars_BackEnd_RegistrationsList extends tx_seminars_BackEnd_List {
	/**
	 * @var string the name of the table we're working on
	 */
	protected $tableName = 'tx_seminars_attendances';

	/**
	 * @var string warnings from the registration bag configcheck
	 */
	private $configCheckWarnings = '';

	/**
	 * @var string the path to the template file of this list
	 */
	protected $templateFile = 'EXT:seminars/Resources/Private/Templates/BackEnd/RegistrationsList.html';

	/**
	 * @var integer parameter for setRegistrationTableMarkers to show the list
	 *              of registrations on the queue
	 */
	const REGISTRATIONS_ON_QUEUE = 1;

	/**
	 * @var integer parameter for setRegistrationTableMarkers to show the list
	 *              of regular registrations
	 */
	const REGULAR_REGISTRATIONS = 2;

	/**
	 * @var integer the UID of the event to show the registrations for
	 */
	private $eventUid = 0;

	/**
	 * Generates and prints out a registrations list.
	 *
	 * @return string the HTML source code to display
	 */
	public function show() {
		$content = '';

		$pageData = $this->page->getPageData();

		$this->template->setMarker(
			'label_attendee_full_name',
			$GLOBALS['LANG']->getLL('registrationlist.feuser.name')
		);
		$this->template->setMarker(
			'label_event_accreditation_number',
			$GLOBALS['LANG']->getLL('registrationlist.seminar.accreditation_number')
		);
		$this->template->setMarker(
			'label_event_title',
			$GLOBALS['LANG']->getLL('registrationlist.seminar.title')
		);
		$this->template->setMarker(
			'label_event_date',
			$GLOBALS['LANG']->getLL('registrationlist.seminar.date')
		);

		$eventUid = intval(t3lib_div::_GP('eventUid'));
		if (($eventUid > 0)
		 	&& tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Event')
		 		->existsModel($eventUid)
		) {
			$this->eventUid = $eventUid;
			$event = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Event')
				->find($eventUid);
			$registrationsHeading = sprintf(
				$GLOBALS['LANG']->getLL('registrationlist.label_registrationsHeading'),
				htmlspecialchars($event->getTitle()),
				$event->getUid()
			);
			$newButton = '';
		} else {
			$registrationsHeading = '';
			$newButton = $this->getNewIcon($pageData['uid']);
		}

		$areAnyRegularRegistrationsVisible = $this->setRegistrationTableMarkers(
			self::REGULAR_REGISTRATIONS
		);
		$registrationTables = $this->template->getSubpart('REGISTRATION_TABLE');
		$this->setRegistrationTableMarkers(self::REGISTRATIONS_ON_QUEUE);
		$registrationTables .= $this->template->getSubpart('REGISTRATION_TABLE');

		$this->template->setOrDeleteMarkerIfNotEmpty(
			'registrations_heading', $registrationsHeading, '','wrapper'
		);
		$this->template->setMarker('new_record_button', $newButton);
		$this->template->setMarker(
			'csv_export_button',
			($areAnyRegularRegistrationsVisible ? $this->getCsvIcon() : '')
		);
		$this->template->setMarker('complete_table', $registrationTables);

		$content .= $this->template->getSubpart('SEMINARS_REGISTRATION_LIST');
		$content .= $this->configCheckWarnings;

		return $content;
	}

	/**
	 * Gets the registration table for regular attendances and attendances on
	 * the registration queue.
	 *
	 * If an event UID > 0 in $this->eventUid is set, the registrations of this
	 * event will be listed, otherwise the registrations on the current page and
	 * subpages will be listed.
	 *
	 * @param integer $registrationsToShow
	 *        the switch to decide which registrations should be shown, must
	 *        be either
	 *        tx_seminars_BackEnd_RegistrationsList::REGISTRATIONS_ON_QUEUE or
	 *        tx_seminars_BackEnd_RegistrationsList::REGULAR_REGISTRATIONS
	 *
	 * @return boolean TRUE if the generated list is not empty, FALSE otherwise
	 */
	private function setRegistrationTableMarkers($registrationsToShow) {
		$builder = tx_oelib_ObjectFactory::make('tx_seminars_registrationBagBuilder');
		$pageData = $this->page->getPageData();

		switch ($registrationsToShow) {
			case self::REGISTRATIONS_ON_QUEUE:
				$builder->limitToOnQueue();
				$tableLabel = 'registrationlist.label_queueRegistrations';
				break;
			case self::REGULAR_REGISTRATIONS:
				$builder->limitToRegular();
				$tableLabel = 'registrationlist.label_regularRegistrations';
				break;
		}
		if ($this->eventUid > 0) {
			$builder->limitToEvent($this->eventUid);
		} else {
			$builder->setSourcePages($pageData['uid'], self::RECURSION_DEPTH);
		}

		$registrationBag = $builder->build();
		$result = !$registrationBag->isEmpty();

		$tableRows = '';

		foreach ($registrationBag as $registration) {
			try {
				$userName = htmlspecialchars($registration->getUserName());
			} catch (tx_oelib_Exception_NotFound $e) {
				$userName = $GLOBALS['LANG']->getLL('registrationlist.deleted');
			}
			$event = $registration->getSeminarObject();
			if ($event->isOk()) {
				$eventTitle = htmlspecialchars($event->getTitle());
				$eventDate = $event->getDate();
				$accreditationNumber = htmlspecialchars(
					$event->getAccreditationNumber()
				);
			} else {
				$eventTitle = $GLOBALS['LANG']->getLL('registrationlist.deleted');
				$eventDate = '';
				$accreditationNumber = '';
			}

			$this->template->setMarker('icon', $registration->getRecordIcon());
			$this->template->setMarker('attendee_full_name', $userName);
			$this->template->setMarker('event_accreditation_number', $accreditationNumber);
			$this->template->setMarker('event_title', $eventTitle);
			$this->template->setMarker('event_date', $eventDate);
			$this->template->setMarker(
				'edit_button',
				$this->getEditIcon(
					$registration->getUid(), $registration->getPageUid()
				)
			);
			$this->template->setMarker(
				'delete_button',
				$this->getDeleteIcon(
					$registration->getUid(), $registration->getPageUid()
				)
			);

			$tableRows .= $this->template->getSubpart('REGISTRATION_TABLE_ROW');
		}

		if ($this->configCheckWarnings == '') {
			$this->configCheckWarnings =
				$registrationBag->checkConfiguration();
		}

		$this->template->setMarker(
			'label_registrations', $GLOBALS['LANG']->getLL($tableLabel)
		);
		$this->template->setMarker(
			'number_of_registrations', $registrationBag->count()
		);
		$this->template->setMarker(
			'table_header',
			$this->template->getSubpart('REGISTRATION_TABLE_HEADING')
		);
		$this->template->setMarker('table_rows', $tableRows);

		$registrationBag->__destruct();

		return $result;
	}

	/**
	 * Returns the storage folder for new registration records.
	 *
	 * This will be determined by the registration folder storage setting of the
	 * currently logged-in BE-user.
	 *
	 * @return integer the PID for new registration records, will be >= 0
	 */
	protected function getNewRecordPid() {
		return $this->getLoggedInUser()->getRegistrationFolderFromGroup();
	}

	/**
	 * Returns the parameters to add to the CSV icon link.
	 *
	 * @return string the additional link parameters for the CSV icon link, will
	 *                always start with an &amp and be htmlspecialchared, may
	 *                be empty
	 */
	protected function getAdditionalCsvParameters() {
		if ($this->eventUid > 0) {
			$result = '&amp;tx_seminars_pi2[eventUid]=' . $this->eventUid;
		} else {
			$result = parent::getAdditionalCsvParameters();
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/BackEnd/class.tx_seminars_BackEnd_RegistrationsList.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/BackEnd/class.tx_seminars_BackEnd_RegistrationsList.php']);
}
?>