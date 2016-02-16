<?php
/***************************************************************
* Copyright notice
*
* (c) 2006-2010 Oliver Klee (typo3-coding@oliverklee.de)
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

require_once(PATH_t3lib . 'class.t3lib_basicfilefunc.php');

require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');
require(t3lib_extMgm::extPath('seminars') . 'tx_seminars_modifiedSystemTables.php');

/**
 * Class 'tx_seminars_pi1_eventEditor' for the 'seminars' extension.
 *
 * This class is a controller which allows to create and edit events on the FE.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_pi1_eventEditor extends tx_seminars_pi1_frontEndEditor {
	/**
	 * @var string path to this script relative to the extension directory
	 */
	public $scriptRelPath = 'pi1/class.tx_seminars_pi1_eventEditor.php';

	/**
	 * @var string stores a validation error message if there was one
	 */
	private $validationError = '';

	/**
	 * @var array currently attached files
	 */
	private $attachedFiles = array();

	/**
	 * @var string the prefix used for every subpart in the FE editor
	 */
	const SUBPART_PREFIX = 'fe_editor';

	/**
	 * @var array the fields required to file a new event.
	 */
	private $requiredFormFields = array();

	/**
	 * @var string the publication hash for the event to edit/create
	 */
	private $publicationHash = '';

	/**
	 * The constructor.
	 *
	 * After the constructor has been called, hasAccessMessage() must be called
	 * to ensure that the logged-in user is allowed to edit a given seminar.
	 *
	 * @param array TypoScript configuration for the plugin
	 * @param tslib_cObj the parent cObj content, needed for the flexforms
	 */
	public function __construct(array $configuration, tslib_cObj $cObj) {
		parent::__construct($configuration, $cObj);
		$this->setRequiredFormFields();
	}

	/**
	 * Stores the currently attached files.
	 *
	 * Attached files are stored in a member variable and added to the form data
	 * afterwards, as the FORMidable renderlet is not usable for this.
	 */
	private function storeAttachedFiles() {
		if (!$this->isTestMode()) {
			$this->attachedFiles = t3lib_div::trimExplode(
				',',
				$this->getFormCreator()->oDataHandler
					->__aStoredData['attached_files'],
				TRUE
			);
		} else {
			$this->attachedFiles = array();
		}
	}

	/**
	 * Declares the additional data handler for m:n relations.
	 */
	private function declareDataHandler() {
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ameos_formidable']
			['declaredobjects']['datahandlers']['DBMM'] = array(
				'key' => 'dh_dbmm', 'base' => TRUE
			);
	}

	/**
	 * Creates the HTML output.
	 *
	 * @return string HTML of the create/edit form
	 */
	public function render() {
		$this->setFormConfiguration($this->conf['form.']['eventEditor.']);
		$this->declareDataHandler();

		$this->storeAttachedFiles();

		$template = tx_oelib_ObjectFactory::make('tx_oelib_Template');
		$template->processTemplate(parent::render());

		$template->hideSubpartsArray(
			$this->getHiddenSubparts(), self::SUBPART_PREFIX
		);

		$this->setRequiredFieldLabels($template);

		// The redirect to the FE editor with the current record loaded can
		// only work with the record's UID, but new records do not have a UID
		// before they are saved.
		if ($this->getObjectUid() == 0) {
			$template->hideSubparts('submit_and_stay');
		}

		return $this->getHtmlWithAttachedFilesList($template);
	}

	/**
	 * Returns the complete HTML for the FE editor.
	 *
	 * As FORMidable does not provide any formatting for the list of
	 * attachments and saves the list with the first letter snipped, we provide
	 * our own formatted list to ensure correctly displayed attachments, even if
	 * there was a validation error.
	 *
	 * @param tx_oelib_Template holds the raw HTML output, must be already
	 *                          processed by FORMidable
	 *
	 * @return string HTML for the FE editor with the formatted attachment
	 *                list if there are attached files, will not be empty
	 */
	private function getHtmlWithAttachedFilesList(tx_oelib_Template $template) {
		foreach (array(
			'label_delete', 'label_really_delete', 'label_save',
			'label_save_and_back',
		) as $label) {
			$template->setMarker($label, $this->translate($label));
		}

		$originalAttachmentList = $this->getFormCreator()->oDataHandler->oForm
			->aORenderlets['attached_files']->mForcedValue;

		if (!empty($this->attachedFiles)) {
			$attachmentList = '';
			$fileNumber = 1;
			foreach ($this->attachedFiles as $fileName) {
				$template->setMarker('file_name', $fileName);
				$template->setMarker(
					'single_attached_file_id', 'attached_file_' . $fileNumber
				);
				$fileNumber++;
				$attachmentList .= $template->getSubpart('SINGLE_ATTACHED_FILE');
			}
			$template->setSubpart('single_attached_file', $attachmentList);
		} else {
			$template->hideSubparts('attached_files');
		}

		$result = $template->getSubpart();

		// Removes FORMidable's original attachment list from the result.
		if ($originalAttachmentList != '') {
			$result = str_replace($originalAttachmentList . '<br />', '', $result);
		}

		return $result;
	}

	/**
	 * Provides data items for the list of available categories.
	 *
	 * @return array $items with additional items from the categories
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListCategories() {
		$categories = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Category')
			->findByPageUid($this->getPidsForAuxiliaryRecords(), 'title ASC');

		return self::makeListToFormidableList($categories);
	}

	/**
	 * Provides data items for the list of available event types.
	 *
	 * @return array $items with additional items from the event_types
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListEventTypes() {
		$eventTypes = tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_EventType')->findByPageUid(
				$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		return self::makeListToFormidableList($eventTypes);
	}

	/**
	 * Provides data items for the list of available lodgings.
	 *
	 * @return array $items with additional items from the lodgings table
	 *               as an array with the keys "caption" (for the title)
	 *               and "value" (for the UID)
	 */
	public function populateListLodgings() {
		$lodgings = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Lodging')
			->findByPageUid($this->getPidsForAuxiliaryRecords(), 'title ASC');

		return self::makeListToFormidableList($lodgings);
	}

	/**
	 * Provides data items for the list of available foods.
	 *
	 * @return array $items with additional items from the foods table
	 *               as an array with the keys "caption" (for the title)
	 *               and "value" (for the UID)
	 */
	public function populateListFoods() {
		$foods= tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Food')
			->findByPageUid($this->getPidsForAuxiliaryRecords(), 'title ASC');

		return self::makeListToFormidableList($foods);
	}

	/**
	 * Provides data items for the list of available payment methods.
	 *
	 * @return array $items with additional items from payment methods
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListPaymentMethods() {
		$paymentMethods = tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_PaymentMethod')->findByPageUid(
				$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		return self::makeListToFormidableList($paymentMethods);
	}

	/**
	 * Provides data items for the list of available organizers.
	 *
	 * @return array $items with additional items from the organizers
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListOrganizers() {
		$organizers = tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_Organizer')->findByPageUid(
				$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		return self::makeListToFormidableList($organizers);
	}

	/**
	 * Provides data items for the list of available places.
	 *
	 * @param array $items any pre-filled data (may be empty)
	 * @param array $unused unused
	 * @param tx_ameosformidable $formidable the FORMidable object
	 *
	 * @return array $items with additional items from the places table
	 *               as an array with the keys "caption" (for the title)
	 *               and "value" (for the UID)
	 */
	public function populateListPlaces(
		array $items, $unused = null, tx_ameosformidable $formidable = null
	) {
		$result = $items;

		$placeMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Place');
		$places = $placeMapper->findByPageUid(
			$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		if (is_object($formidable)) {
			$editButtonConfiguration =& $formidable->_navConf(
				$formidable->aORenderlets['editPlaceButton']->sXPath
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$showEditButton = $this->isFrontEndEditingOfRelatedRecordsAllowed(
			array('relatedRecordType' => 'Places')
		) && is_object($formidable);

		foreach ($places as $place) {
			$frontEndUserIsOwner = ($place->getOwner() === $frontEndUser);

			// Only shows places which have no owner or where the owner is the
			// currently logged in front-end user.
			if ($place->getOwner() && !$frontEndUserIsOwner) {
				continue;
			}

			if ($showEditButton && $frontEndUserIsOwner) {
				$editButtonConfiguration['name'] = 'editPlaceButton_' . $place->getUid();
				$editButtonConfiguration['onclick']['userobj']['php'] = '
					require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
					return tx_seminars_pi1_eventEditor::showEditPlaceModalBox($this, ' . $place->getUid() . ');
					';
				$editButton = $formidable->_makeRenderlet(
					$editButtonConfiguration,
					$formidable->aORenderlets['editPlaceButton']->sXPath
				);
				$editButton->includeScripts();
				$editButtonHTML = $editButton->_render();
				$result[] = array(
					'caption' => $place->getTitle(),
					'value' => $place->getUid(),
					'labelcustom' => 'id="tx_seminars_pi1_seminars_place_label_' . $place->getUid() . '"',
					'wrapitem' => '|</td><td>' . $editButtonHTML['__compiled'],
				);
			} else {
				$result[] = array(
					'caption' => $place->getTitle(),
					'value' => $place->getUid(),
					'wrapitem' => '|</td><td>&nbsp;'
				);
			}
		}

		return $result;
	}

	/**
	 * Provides data items for the list of available speakers.
	 *
	 * @param array any pre-filled data (may be empty)
	 * @param array $parameters the parameters sent to this function by FORMidable
	 * @param tx_ameosformidable $formidable the FORMidable object
	 *
	 * @return array $items with additional items from the speakers table
	 *               as an array with the keys "caption" (for the title)
	 *               and "value" (for the UID)
	 */
	public function populateListSpeakers(
		array $items, $parameters = array(), tx_ameosformidable $formidable = null
	) {
		$result = $items;

		$speakerMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker');
		$speakers = $speakerMapper->findByPageUid(
			$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		if (is_object($formidable)) {
			$editButtonConfiguration =& $formidable->_navConf(
				$formidable->aORenderlets['editSpeakerButton']->sXPath
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$showEditButton = $this->isFrontEndEditingOfRelatedRecordsAllowed(
			array('relatedRecordType' => 'Speakers')
		) && is_object($formidable);

		$type = $parameters['type'];

		foreach ($speakers as $speaker) {
			$frontEndUserIsOwner = ($speaker->getOwner() === $frontEndUser);

			// Only shows speakers which have no owner or where the owner is
			// the currently logged in front-end user.
			if ($speaker->getOwner() && !$frontEndUserIsOwner) {
				continue;
			}

			if ($showEditButton && $frontEndUserIsOwner) {
				$editButtonConfiguration['name'] = 'edit' . $type . 'Button_' . $speaker->getUid();
				$editButtonConfiguration['onclick']['userobj']['php'] = '
					require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
					return tx_seminars_pi1_eventEditor::showEditSpeakerModalBox($this, ' . $speaker->getUid() . ');
					';
				$editButton = $formidable->_makeRenderlet(
					$editButtonConfiguration,
					$formidable->aORenderlets['editSpeakerButton']->sXPath
				);
				$editButton->includeScripts();
				$editButtonHTML = $editButton->_render();
				$result[] = array(
					'caption' => $speaker->getName(),
					'value' => $speaker->getUid(),
					'labelcustom' => 'id="tx_seminars_pi1_seminars_' .
						strtolower($type) . '_label_' . $speaker->getUid() . '"',
					'wrapitem' => '|</td><td>' . $editButtonHTML['__compiled'],
				);
			} else {
				$result[] = array(
					'caption' => $speaker->getName(),
					'value' => $speaker->getUid(),
					'wrapitem' => '|</td><td>&nbsp;'
				);
			}
		}

		return $result;
	}

	/**
	 * Provides data items for the list of available checkboxes.
	 *
	 * @param array $items any pre-filled data (may be empty)
	 * @param array $unused unused
	 * @param tx_ameosformidable $formidable the FORMidable object
	 *
	 * @return array $items with additional items from the checkboxes
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListCheckboxes(
		array $items, $unused = null, tx_ameosformidable $formidable = null
	) {
		$result = $items;

		$checkboxMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Checkbox');
		$checkboxes = $checkboxMapper->findByPageUid(
			$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		if (is_object($formidable)) {
			$editButtonConfiguration =& $formidable->_navConf(
				$formidable->aORenderlets['editCheckboxButton']->sXPath
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$showEditButton = $this->isFrontEndEditingOfRelatedRecordsAllowed(
			array('relatedRecordType' => 'Checkboxes')
		) && is_object($formidable);

		foreach ($checkboxes as $checkbox) {
			$frontEndUserIsOwner = ($checkbox->getOwner() === $frontEndUser);

			// Only shows checkboxes which have no owner or where the owner is
			// the currently logged in front-end user.
			if ($checkbox->getOwner() && !$frontEndUserIsOwner) {
				continue;
			}

			if ($showEditButton && $frontEndUserIsOwner) {
				$editButtonConfiguration['name'] = 'editCheckboxButton_' . $checkbox->getUid();
				$editButtonConfiguration['onclick']['userobj']['php'] = '
					require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
					return tx_seminars_pi1_eventEditor::showEditCheckboxModalBox($this, ' . $checkbox->getUid() . ');
					';
				$editButton = $formidable->_makeRenderlet(
					$editButtonConfiguration,
					$formidable->aORenderlets['editCheckboxButton']->sXPath
				);
				$editButton->includeScripts();
				$editButtonHTML = $editButton->_render();
				$result[] = array(
					'caption' => $checkbox->getTitle(),
					'value' => $checkbox->getUid(),
					'labelcustom' => 'id="tx_seminars_pi1_seminars_checkbox_label_' . $checkbox->getUid() . '"',
					'wrapitem' => '|</td><td>' . $editButtonHTML['__compiled'],
				);
			} else {
				$result[] = array(
					'caption' => $checkbox->getTitle(),
					'value' => $checkbox->getUid(),
					'wrapitem' => '|</td><td>&nbsp;',
				);
			}
		}

		return $result;
	}

	/**
	 * Provides data items for the list of available target groups.
	 *
	 * @param array any pre-filled data (may be empty)
	 * @param array $unused unused
	 * @param tx_ameosformidable $formidable the FORMidable object
	 *
	 * @return array $items with additional items from the target groups
	 *               table as an array with the keys "caption" (for the
	 *               title) and "value" (for the UID)
	 */
	public function populateListTargetGroups(
		array $items, $unused = null, tx_ameosformidable $formidable = null
	) {
		$result = $items;

		$targetGroupMapper = tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_TargetGroup'
		);
		$targetGroups = $targetGroupMapper->findByPageUid(
			$this->getPidsForAuxiliaryRecords(), 'title ASC'
		);

		if (is_object($formidable)) {
			$editButtonConfiguration =& $formidable->_navConf(
				$formidable->aORenderlets['editTargetGroupButton']->sXPath
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$showEditButton = $this->isFrontEndEditingOfRelatedRecordsAllowed(
			array('relatedRecordType' => 'TargetGroups')
		) && is_object($formidable);

		foreach ($targetGroups as $targetGroup) {
			$frontEndUserIsOwner = ($targetGroup->getOwner() === $frontEndUser);

			// Only shows target groups which have no owner or where the owner
			// is the currently logged in front-end user.
			if ($targetGroup->getOwner() && !$frontEndUserIsOwner) {
				continue;
			}

			if ($showEditButton && $frontEndUserIsOwner) {
				$editButtonConfiguration['name'] = 'editTargetGroupButton_' .
					$targetGroup->getUid();
				$editButtonConfiguration['onclick']['userobj']['php'] = '
					require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
					return tx_seminars_pi1_eventEditor::showEditTargetGroupModalBox($this, ' . $targetGroup->getUid() . ');
					';
				$editButton = $formidable->_makeRenderlet(
					$editButtonConfiguration,
					$formidable->aORenderlets['editTargetGroupButton']->sXPath
				);
				$editButton->includeScripts();
				$editButtonHTML = $editButton->_render();
				$result[] = array(
					'caption' => $targetGroup->getTitle(),
					'value' => $targetGroup->getUid(),
					'labelcustom' => 'id="tx_seminars_pi1_seminars_target_group_label_' . $targetGroup->getUid() . '"',
					'wrapitem' => '|</td><td>' . $editButtonHTML['__compiled'],
				);
			} else {
				$result[] = array(
					'caption' => $targetGroup->getTitle(),
					'value' => $targetGroup->getUid(),
					'wrapitem' => '|</td><td>&nbsp;',
				);
			}
		}

		return $result;
	}

	/**
	 * Gets the URL of the page that should be displayed when an event has been
	 * successfully created.
	 * An URL of the FE editor's page is returned if "submit_and_stay" was
	 * clicked.
	 *
	 * @return string complete URL of the FE page with a message or, if
	 *                "submit_and_stay" was clicked, of the current page
	 */
	public function getEventSuccessfullySavedUrl() {
		$additionalParameters = '';

		if ($this->getFormValue('proceed_file_upload')) {
			$additionalParameters = t3lib_div::implodeArrayForUrl(
				$this->prefixId,
				array('seminar' => $this->getObjectUid())
			);
			$pageId = $GLOBALS['TSFE']->id;
		} else {
			$pageId = $this->getConfValueInteger(
				'eventSuccessfullySavedPID', 's_fe_editing'
			);
		}

		return t3lib_div::locationHeaderUrl(
			$this->cObj->typoLink_URL(array(
				'parameter' => $pageId,
				'additionalParams' => $additionalParameters,
			))
		);
	}

	/**
	 * Checks whether the currently logged-in FE user (if any) belongs to the
	 * FE group that is allowed to enter and edit event records in the FE.
	 * This group can be set using plugin.tx_seminars.eventEditorFeGroupID.
	 *
	 * It also is checked whether that event record exists and the logged-in
	 * FE user is the owner or is editing a new record.
	 *
	 * @return string locallang key of an error message, will be an empty
	 *                string if access was granted
	 */
	private function checkAccess() {
		if (!tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()) {
			return 'message_notLoggedIn';
		}

		if (($this->getObjectUid() > 0)
			&& !tx_seminars_objectfromdb::recordExists(
				$this->getObjectUid(), SEMINARS_TABLE_SEMINARS, TRUE
			)
		) {
			return 'message_wrongSeminarNumber';
		}

		if ($this->getObjectUid() > 0) {
			$seminar = tx_oelib_ObjectFactory::make(
				'tx_seminars_seminar', $this->getObjectUid(), FALSE, TRUE
			);
			$isUserVip = $seminar->isUserVip(
				$this->getFeUserUid(),
				$this->getConfValueInteger('defaultEventVipsFeGroupID')
			);
			$isUserOwner = $seminar->isOwnerFeUser();
			$seminar->__destruct();
			unset($seminar);
			$mayManagersEditTheirEvents = $this->getConfValueBoolean(
				'mayManagersEditTheirEvents', 's_listView'
			);

			$hasAccess = $isUserOwner
				|| ($mayManagersEditTheirEvents && $isUserVip);
		} else {
			$eventEditorGroupUid = $this->getConfValueInteger(
				'eventEditorFeGroupID', 's_fe_editing'
			);
			$hasAccess = ($eventEditorGroupUid != 0)
				&& tx_oelib_FrontEndLoginManager::getInstance()
					->getLoggedInUser()->hasGroupMembership($eventEditorGroupUid);
		}

		return ($hasAccess ? '' : 'message_noAccessToEventEditor');
	}

	/**
	 * Checks whether the currently logged-in FE user (if any) belongs to the
	 * FE group that is allowed to enter and edit event records in the FE.
	 * This group can be set using plugin.tx_seminars.eventEditorFeGroupID.
	 * If the FE user does not have the necessary permissions, a localized error
	 * message will be returned.
	 *
	 * @return string an empty string if a user is logged in and allowed
	 *                to enter and edit events, a localized error message
	 *                otherwise
	 */
	public function hasAccessMessage() {
		$result = '';
		$errorMessage = $this->checkAccess();

		if ($errorMessage != '') {
			$this->setMarker('error_text', $this->translate($errorMessage));
			$result = $this->getSubpart('ERROR_VIEW');
		}

		return $result;
	}

	/**
	 * Changes all potential decimal separators (commas and dots) in price
	 * fields to dots.
	 *
	 * @param array all entered form data with the field names as keys,
	 *              will be modified, must not be empty
	 */
	private function unifyDecimalSeparators(array &$formData) {
		$priceFields = array(
			'price_regular', 'price_regular_early', 'price_regular_board',
			'price_special', 'price_special_early', 'price_special_board',
		);

		foreach ($priceFields as $key) {
			if (isset($formData[$key])) {
				$formData[$key]
					= str_replace(',', '.', $formData[$key]);
			}
		}
	}

	/**
	 * Processes the deletion of attached files and sets the form value for
	 * "attached_files" to the locally stored value for this field.
	 *
	 * This is done because when FORMidable processes the upload renderlet,
	 * the first character of the string might get lost. In addition, with
	 * FORMidable, it is possible to store the name of an invalid file in the
	 * list of attachments.
	 *
	 * @param array form data, will be modified, must not be empty
	 */
	private function processAttachments(array &$formData) {
		$filesToDelete = t3lib_div::trimExplode(
			',', $formData['delete_attached_files'], TRUE
		);

		foreach ($filesToDelete as $fileName) {
			// saves other files in the upload folder from being deleted
			if (in_array($fileName, $this->attachedFiles)) {
				$this->purgeUploadedFile($fileName);
			}
		}

		$formData['attached_files'] = implode(',', $this->attachedFiles);
	}

	/**
	 * Removes all form data elements that are no fields in the seminars table.
	 *
	 * @param array form data, will be modified, must not be empty
	 */
	private function purgeNonSeminarsFields(array &$formData) {
		$fieldsToUnset = array(
			'' => array('proceed_file_upload', 'delete_attached_files'),
			'newPlace_' => array(
				'title', 'address', 'city', 'country', 'homepage', 'directions',
				'notes',
			),
			'editPlace_' => array(
				'title', 'address', 'city', 'country', 'homepage', 'directions',
				'notes', 'uid',
			),
			'newSpeaker_' => array(
				'title', 'gender', 'organization', 'homepage',
				'description', 'skills', 'notes', 'address', 'phone_work',
				'phone_home', 'phone_mobile', 'fax', 'email', 'cancelation_period',
			),
			'editSpeaker_' => array(
				'title', 'gender', 'organization', 'homepage',
				'description', 'skills', 'notes', 'address', 'phone_work',
				'phone_home', 'phone_mobile', 'fax', 'email', 'cancelation_period',
				'uid',
			),
			'newCheckbox_' => array('title'),
			'editCheckbox_' => array('title', 'uid'),
			'newTargetGroup_' => array(
				'title', 'uid', 'minimum_age', 'maximum_age',
			),
			'editTargetGroup_' => array(
				'title', 'uid', 'minimum_age', 'maximum_age',
			),
		);

		foreach ($fieldsToUnset as $prefix => $keys) {
			foreach ($keys as $key) {
				unset($formData[$prefix . $key]);
			}
		}
	}

	/**
	 * Adds some values to the form data before insertion into the database.
	 * Added values for new objects are: 'crdate', 'tstamp', 'pid' and
	 * 'owner_feuser'.
	 * For objects to update, just the 'tstamp' will be refreshed.
	 *
	 * @param array form data, will be modified, must not be empty
	 */
	private function addAdministrativeData(array &$formData) {
		$formData['tstamp'] = $GLOBALS['SIM_EXEC_TIME'];

		// Updating the timestamp is sufficent for existing records.
		if ($this->getObjectUid() > 0) {
			return;
		}

		$formData['crdate'] = $GLOBALS['SIM_EXEC_TIME'];
		$formData['owner_feuser'] = $this->getFeUserUid();
		$eventPid = tx_oelib_FrontEndLoginManager::getInstance()->getLoggedInUser(
			'tx_seminars_Mapper_FrontEndUser')->getEventRecordsPid();
		$formData['pid'] = ($eventPid > 0)
			? $eventPid
			: $this->getConfValueInteger('createEventsPID', 's_fe_editing');
	}

	/**
	 * Checks the publish settings of the user and hides the event record if
	 * necessary.
	 *
	 * @param array form data, will be modified if the seminar must be hidden
	 *              corresponding to the publish settings of the user, must not
	 *              be empty
	 */
	private function checkPublishSettings(array &$formData) {
		$publishSetting	= tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser')
				->getPublishSetting();
		$eventUid = $this->getObjectUid();
		$isNew = ($eventUid == 0);

		$hideEditedObject = !$isNew
			&& ($publishSetting
				== tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED
			);
		$hideNewObject = $isNew
			&& ($publishSetting
				> tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY
			);

		$eventIsHidden = !$isNew
			? tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Event')
				->find($eventUid)->isHidden()
			: FALSE;

		if (($hideEditedObject || $hideNewObject) && !$eventIsHidden) {
			$formData['hidden'] = 1;
			$formData['publication_hash'] = uniqid('', TRUE);
			$this->publicationHash = $formData['publication_hash'];
		} else {
			$this->publicationHash = '';
		}
	}

	/**
	 * Unifies decimal separators, processes the deletion of attachments and
	 * purges non-seminars-fields.
	 *
	 * @see unifyDecimalSeparators(), processAttachments(),
	 *      purgeNonSeminarsFields(), addAdministrativeData()
	 *
	 * @param array form data, must not be empty
	 *
	 * @return array modified form data, will not be empty
	 */
	public function modifyDataToInsert(array $formData) {
		$modifiedFormData = $formData;

		$this->processAttachments($modifiedFormData);
		$this->purgeNonSeminarsFields($modifiedFormData);
		$this->unifyDecimalSeparators($modifiedFormData);
		$this->addAdministrativeData($modifiedFormData);
		$this->checkPublishSettings($modifiedFormData);
		$this->addCategoriesOfUser($modifiedFormData);

		return $modifiedFormData;
	}

	/**
	 * Checks whether the provided file is of an allowed type and size. If it
	 * is, it is appended to the list of already attached files. If not, the
	 * file deleted becomes from the upload directory and the validation error
	 * is stored in $this->validationError.
	 *
	 * This check is done here because the FORMidable validators do not allow
	 * multiple error messages.
	 *
	 * @param array form data to check, must not be empty
	 *
	 * @return boolean TRUE if the provided file is valid, FALSE otherwise
	 */
	public function checkFile(array $valueToCheck) {
		$this->validationError = '';

		// If these values match, no files have been uploaded and we need no
		// further check.
		if ($valueToCheck['value'] == implode(',', $this->attachedFiles)) {
			return TRUE;
		}

		$fileToCheck = array_pop(
			t3lib_div::trimExplode(',', $valueToCheck['value'], TRUE)
		);

		$this->checkFileSize($fileToCheck);
		$this->checkFileType($fileToCheck);

		// If there is a validation error, the upload has to be done again.
		if (($this->validationError == '')
			&& ($this->isTestMode() || $this->getFormCreator()->oDataHandler->_allIsValid())
		) {
			array_push($this->attachedFiles, $fileToCheck);
		} else {
			$this->purgeUploadedFile($fileToCheck);
		}

		return ($this->validationError == '');
	}

	/**
	 * Checks whether an uploaded file is of a valid type.
	 *
	 * @param string file name, must match an uploaded file, must not be empty
	 */
	private function checkFileType($fileName) {
		$allowedExtensions = $this->getConfValueString(
			'allowedExtensionsForUpload', 's_fe_editing'
		);

		if (!preg_match(
			'/^.+\.(' . str_replace(',', '|', $allowedExtensions) . ')$/i',
			$fileName
		)) {
			$this->validationError
				= $this->translate('message_invalid_type') .
					' ' . str_replace(',', ', ', $allowedExtensions) . '.';
		}
	}

	/**
	 * Checks whether an uploaded file is not too large.
	 *
	 * @param string file name, must match an uploaded file, must not be empty
	 */
	private function checkFileSize($fileName) {
		$maximumFileSize = $GLOBALS['TYPO3_CONF_VARS']['BE']['maxFileSize'];
		$fileInformation = t3lib_div::makeInstance('t3lib_basicFileFunctions')
			->getTotalFileInfo(PATH_site . 'uploads/tx_seminars/' . $fileName);

		if ($fileInformation['size'] > ($maximumFileSize * 1024)) {
			$this->validationError
				= $this->translate('message_file_too_large') .
					' ' . $maximumFileSize . 'kB.';
		}
	}

	/**
	 * Deletes a file in the seminars upload directory and removes it from the
	 * list of currently attached files.
	 *
	 * @param string file name, must match an uploaded file, must not be empty
	 *
	 * @return string comma-separated list with the still attached files,
	 *                will be empty if the last attachment was removed
	 */
	private function purgeUploadedFile($fileName) {
		@unlink(PATH_site . 'uploads/tx_seminars/' . $fileName);
		$keyToPurge = array_search($fileName, $this->attachedFiles);
		if ($keyToPurge !== FALSE) {
			unset($this->attachedFiles[$keyToPurge]);
		}
	}

	/**
	 * Returns an error message if the provided file was invalid.
	 *
	 * @return string localized validation error message, will be empty if
	 *                $this->validationError was empty
	 */
	public function getFileUploadErrorMessage() {
		return $this->validationError;
	}

	/**
	 * Retrieves the keys of the subparts which should be hidden in the event
	 * editor.
	 *
	 * @return array the keys of the subparts which should be hidden in the
	 *               event editor without the prefix FE_EDITOR_, will be empty
	 *               if all subparts should be shown.
	 */
	private function getHiddenSubparts() {
		$visibilityTree = tx_oelib_ObjectFactory::make(
			'tx_oelib_Visibility_Tree', $this->createTemplateStructure()
		);

		$visibilityTree->makeNodesVisible($this->getFieldsToShow());
		$subpartsToHide = $visibilityTree->getKeysOfHiddenSubparts();
		$visibilityTree->__destruct();

		return $subpartsToHide;
	}

	/**
	 * Creates the template subpart structure.
	 *
	 * @return array the template's subpart structure for use with
	 *               tx_oelib_Visibility_Tree
	 */
	private function createTemplateStructure() {
		return array(
			'subtitle' => FALSE,
			'title_right' => array(
				'accreditation_number' => FALSE,
				'credit_points' => FALSE,
			),
			'basic_information' => array(
				'categories' => FALSE,
				'event_type' => FALSE,
				'cancelled' => FALSE,
			),
			'text_blocks' => array(
				'teaser' => FALSE,
				'description' => FALSE,
				'additional_information' => FALSE,
			),
			'registration_information' => array(
				'dates' => array(
					'events_dates' => array(
						'begin_date' => FALSE,
						'end_date' => FALSE,
					),
					'registration_dates' => array(
						'begin_date_registration' => FALSE,
						'deadline_early_bird' => FALSE,
						'deadline_registration' => FALSE,
					),
				),
				'attendance_information' => array(
					'registration_and_queue' => array(
						'needs_registration' => FALSE,
						'allows_multiple_registrations' => FALSE,
						'queue_size' => FALSE,
					),
					'attendees_number' => array(
						'attendees_min' => FALSE,
						'attendees_max' => FALSE,
						'offline_attendees' => FALSE,
					),
				),
				'target_groups' => FALSE,
				'prices' => array(
					'regular_prices' => array(
						'price_regular' => FALSE,
						'price_regular_early' => FALSE,
						'price_regular_board' => FALSE,
						'payment_methods' => FALSE,
					),
					'special_prices' => array(
						'price_special' => FALSE,
						'price_special_early' => FALSE,
						'price_special_board' => FALSE,
					),
				),
			),
			'place_information' => array(
				'place_and_room' => array(
					'place' => FALSE,
					'room' => FALSE,
				),
				'lodging_and_food' => array(
					'lodgings' => FALSE,
					'foods' => FALSE,
				),
			),
			'speakers' => FALSE,
			'leaders' => FALSE,
			'partner_tutor' => array(
				'partners' => FALSE,
				'tutors' => FALSE,
			),
			'checkbox_options' => array(
				'checkboxes' => FALSE,
				'uses_terms_2' => FALSE,
			),
			'attached_file_box' => FALSE,
			'notes' => FALSE,
		);
	}

	/**
	 * Returns the keys of the fields which should be shown in the FE editor.
	 *
	 * @return array the keys of the fields which should be shown, will be empty
	 *               if all fields should be hidden
	 */
	private function getFieldsToShow() {
		$fieldsToShow = t3lib_div::trimExplode(
			',',
			$this->getConfValueString(
				'displayFrontEndEditorFields', 's_fe_editing'),
			TRUE
		);
		$this->removeCategoryIfNecessary($fieldsToShow);

		return $fieldsToShow;
	}

	/**
	 * Returns whether front-end editing of the given related record type is
	 * allowed.
	 *
	 * @param array $parameters the contents of the "params" child of the
	 *                          userobj node as key/value pairs
	 *
	 * @return boolean TRUE if front-end editing of the given related record
	 *                 type is allowed, FALSE otherwise
	 */
	public function isFrontEndEditingOfRelatedRecordsAllowed(array $parameters) {
		$relatedRecordType = $parameters['relatedRecordType'];

		$frontEndUser = tx_oelib_FrontEndLoginManager::
			getInstance()->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$isFrontEndEditingAllowed = $this->getConfValueBoolean(
			'allowFrontEndEditingOf' . $relatedRecordType, 's_fe_editing'
		);

		$axiliaryPidFromSetup = $this->getConfValueBoolean(
			'createAuxiliaryRecordsPID'
		);
		$isAnAuxiliaryPidSet = ($frontEndUser->getAuxiliaryRecordsPid() > 0) ||
			($axiliaryPidFromSetup > 0);

		return $isFrontEndEditingAllowed && $isAnAuxiliaryPidSet;
	}

	/**
	 * Reads the list of required form fields from the configuration and stores
	 * it in $this->requiredFormFields.
	 */
	private function setRequiredFormFields() {
		$this->requiredFormFields = t3lib_div::trimExplode(
			',',
			$this->getConfValueString(
				'requiredFrontEndEditorFields', 's_fe_editing'
			)
		);

		$this->removeCategoryIfNecessary($this->requiredFormFields);
	}

	/**
	 * Adds a class 'required' to the label of a field if it is required.
	 *
	 * @param tx_oelib_template $template the template in which the required
	 *        markers should be set.
	 */
	private function setRequiredFieldLabels(tx_oelib_template $template) {
		$formFieldsToCheck = $this->getFieldsToShow();

		foreach ($formFieldsToCheck as $formField) {
			$template->setMarker(
				$formField . '_required',
				(in_array($formField, $this->requiredFormFields))
					? ' class="required"'
					: ''
			);
		}
	}

	/**
	 * Checks whether a given field is required.
	 *
	 * @param array $field
	 *        the field to check, the array must contain an element with the key
	 *        'elementName' and a nonempty value for that key
	 *
	 * @return boolean TRUE if the field is required, FALSE otherwise
	 */
	private function isFieldRequired(array $field) {
		if ($field['elementName'] == '') {
			throw new InvalidArgumentException('The given field name was empty.', 1333293167);
		}

		return in_array($field['elementName'], $this->requiredFormFields);
	}

	/**
	 * Checks whether a given field needs to be filled in, but hasn't been
	 * filled in yet.
	 *
	 * @param array $formData
	 *        associative array containing the current value, with the key
	 *        'value' and the name, with the key 'elementName', of the form
	 *        field to check, must not be empty
	 *
	 * @return boolean TRUE if this field is not empty or not required, FALSE
	 *                 otherwise
	 */
	public function validateString(array $formData) {
		if (!$this->isFieldRequired($formData)) {
			return TRUE;
		}

		return (trim($formData['value']) != '');
	}

	/**
	 * Checks whether a given field needs to be filled in with a non-zero value,
	 * but hasn't been filled in correctly yet.
	 *
	 * @param array $formData
	 *        associative array containing the current value, with the key
	 *        'value' and the name, with the key 'elementName', of the form
	 *        field to check, must not be empty
	 *
	 * @return boolean TRUE if this field is not zero or not required, FALSE
	 *                 otherwise
	 */
	public function validateInteger(array $formData) {
		if (!$this->isFieldRequired($formData)) {
			return TRUE;
		}

		return (intval($formData['value']) != 0);
	}

	/**
	 * Checks whether a given field needs to be filled in with a non-empty array,
	 * but hasn't been filled in correctly yet.
	 *
	 * @param array $formData
	 *        associative array containing the current value, with the key
	 *        'value' and the name, with the key 'elementName', of the form
	 *        field to check, must not be empty
	 *
	 * @return boolean TRUE if this field is not zero or not required, FALSE
	 *                 otherwise
	 */
	public function validateCheckboxes(array $formData) {
		if (!$this->isFieldRequired($formData)) {
			return TRUE;
		}

		return is_array($formData['value']) && !empty($formData['value']);
	}

	/**
	 * Checks whether a given field needs to be filled in with a valid date,
	 * but hasn't been filled in correctly yet.
	 *
	 * @param array $formData
	 *        associative array containing the current value, with the key
	 *        'value' and the name, with the key 'elementName', of the form
	 *        field to check, must not be empty
	 *
	 * @return boolean TRUE if this field contains a valid date or if this field
	 *                 is not required, FALSE otherwise
	 */
	public function validateDate(array $formData) {
		if (!$this->isFieldRequired($formData)) {
			return TRUE;
		}

		return (preg_match('/^[\d:\-\/ ]+$/', $formData['value']) == 1);
	}

	/**
	 * Checks whether a given field needs to be filled in with a valid price,
	 * but hasn't been filled in correctly yet.
	 *
	 * @param array $formData
	 *        associative array containing the current value, with the key
	 *        'value' and the name, with the key 'elementName', of the form
	 *        field to check, must not be empty
	 *
	 * @return boolean TRUE if this field contains a valid price or if this
	 *                 field is not required, FALSE otherwise
	 */
	public function validatePrice(array $formData) {
		if (!$this->isFieldRequired($formData)) {
			return TRUE;
		}

		return (preg_match('/^\d+((,|.)\d{1,2})?$/', $formData['value']) == 1);
	}

	/**
	 * Sends the publishing e-mail to the reviewer if necessary.
	 */
	public function sendEMailToReviewer() {
		if ($this->publicationHash == '') {
			return;
		}
		tx_oelib_MapperRegistry::purgeInstance();
		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');
		$reviewer = $frontEndUser->getReviewerFromGroup();

		if (!$reviewer) {
			return;
		}

		$event = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Event')
			->findByPublicationHash($this->publicationHash);

		if ($event && $event->isHidden()) {
			$eMail = tx_oelib_ObjectFactory::make('tx_oelib_Mail');
			$eMail->addRecipient($reviewer);
			$eMail->setSender($frontEndUser);
			$eMail->setSubject($this->translate('publish_event_subject'));
			$eMail->setMessage($this->createEMailContent($event));

			tx_oelib_mailerFactory::getInstance()->getMailer()->send($eMail);

			$eMail->__destruct();
		}
	}

	/**
	 * Builds the content for the publishing e-mail to the reviewer.
	 *
	 * @param tx_seminars_Model_Event $event
	 *        the event to send the publication e-mail for
	 *
	 * @return string the e-mail body for the publishing e-mail, will not be
	 *                empty
	 */
	private function createEMailContent(tx_seminars_Model_Event $event) {
		$this->getTemplateCode(TRUE);
		$this->setLabels();

		$markerPrefix = 'publish_event';

		if ($event->hasBeginDate()) {
			$beginDate = strftime(
				$this->getConfValueString('dateFormatYMD'),
				$event->getBeginDateAsUnixTimeStamp()
			);
		} else {
			$beginDate = '';
		}

		$this->setMarker('title', $event->getTitle(), $markerPrefix);
		$this->setOrDeleteMarkerIfNotEmpty(
			'date', $beginDate, $markerPrefix, 'wrapper_publish_event'
		);
		$this->setMarker(
			'description', $event->getDescription(), $markerPrefix
		);

		$this->setMarker('link', $this->createReviewUrl(), $markerPrefix);

		return $this->getSubpart('MAIL_PUBLISH_EVENT');
	}

	/**
	 * Builds the URL for the reviewer e-mail.
	 *
	 * @return string the URL for the plain text e-mail, will not be empty
	 */
	private function createReviewUrl() {
		$url = $this->cObj->typoLink_URL(array(
			'parameter' => $GLOBALS['TSFE']->id . ',' .
				$this->getConfValueInteger('typeNumForPublish'),
			'additionalParams' => t3lib_div::implodeArrayForUrl(
				'tx_seminars_publication',
				array(
					'hash' => $this->publicationHash,
				),
				'',
				FALSE,
				TRUE
			),
			'type' => $this->getConfValueInteger('typeNumForPublish'),
		));

		return t3lib_div::locationHeaderUrl(preg_replace(
			array('/\[/', '/\]/'),
			array('%5B', '%5D'),
			$url
		));
	}

	/**
	 * Creates a new place record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 *
	 * @return array calls to be executed on the client
	 */
	public static function createNewPlace(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();
		$validationErrors = self::validatePlace(
			$formidable, array(
				'title' => $formData['newPlace_title'],
				'city' => $formData['newPlace_city'],
			)
		);
		if (!empty($validationErrors)) {
			return array(
				$formidable->majixExecJs(
					'alert("' . implode('\n', $validationErrors) . '");'
				),
			);
		};

		$place = tx_oelib_ObjectFactory::make('tx_seminars_Model_Place');
		$place->setData(self::createBasicAuxiliaryData());
		self::setPlaceData($place, 'newPlace_', $formData);
		$place->markAsDirty();
		tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Place')->save($place);

		$editButtonConfiguration =& $formidable->_navConf(
			$formidable->aORenderlets['editPlaceButton']->sXPath
		);
		$editButtonConfiguration['name'] = 'editPlaceButton_' . $place->getUid();
		$editButtonConfiguration['onclick']['userobj']['php'] = '
			require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
			return tx_seminars_pi1_eventEditor::showEditPlaceModalBox($this, ' . $place->getUid() . ');
			';
		$editButton = $formidable->_makeRenderlet(
			$editButtonConfiguration,
			$formidable->aORenderlets['editPlaceButton']->sXPath
		);
		$editButton->includeScripts();
		$editButtonHTML = $editButton->_render();

		return array(
			$formidable->aORenderlets['newPlaceModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'appendPlaceInEditor(' . $place->getUid() . ', "' .
					addcslashes($place->getTitle(), '"\\') . '", {
						"name": "' . addcslashes($editButtonHTML['name'], '"\\') . '",
						"id": "' . addcslashes($editButtonHTML['id'], '"\\') . '",
						"value": "' . addcslashes($editButtonHTML['value'], '"\\') . '"
					});'
			),
		);
	}

	/**
	 * Updates an existing place record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 *
	 * @return array calls to be executed on the client
	 */
	public static function updatePlace(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$placeMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Place');

		try {
			$place = $placeMapper->find(intval($formData['editPlace_uid']));
		} catch (Exception $exception) {
			return $formidable->majixExecJs(
				'alert("The place with the given UID does not exist.");'
			);
		}

		if ($place->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this place.");'
			);
		}

		$validationErrors = self::validatePlace(
			$formidable,
			array(
				'title' => $formData['editPlace_title'],
				'city' => $formData['editPlace_city'],
			)
		);
		if (!empty($validationErrors)) {
			return $formidable->majixExecJs(
				'alert("' . implode('\n', $validationErrors) . '");'
			);
		};

		self::setPlaceData($place, 'editPlace_', $formData);
		$placeMapper->save($place);

		$htmlId = 'tx_seminars_pi1_seminars_place_label_' . $place->getUid();

		return array(
			$formidable->aORenderlets['editPlaceModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'updateAuxiliaryRecordInEditor("' . $htmlId . '", "' .
					addcslashes($place->getTitle(), '"\\') . '")'
			),
		);
	}

	/**
	 * Validates the entered data for a place.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 * @param array $formData
	 *        the entered form data, the key must be stripped of the
	 *        "newPlace_"/"editPlace_" prefix
	 *
	 * @return array the error messages, will be empty if there are no
	 *         validation errors
	 */
	private static function validatePlace(
		tx_ameosformidable $formidable, array $formData
	) {
		$validationErrors = array();
		if (trim($formData['title']) == '') {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_emptyTitle'
			);
		}
		if (trim($formData['city']) == '') {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_emptyCity'
			);
		}

		return $validationErrors;
	}

	/**
	 * Sets the data of a place model based on the data given in $formData.
	 *
	 * @param tx_seminars_Model_Place $place the place model to set the data
	 * @param string $prefix the prefix of the form fields in $formData
	 * @param array $formData the form data to use for setting the place data
	 */
	private static function setPlaceData(
		tx_seminars_Model_Place $place, $prefix, array $formData
	) {
		$countryUid = intval($formData[$prefix . 'country']);
		if ($countryUid > 0) {
			try {
				$country = tx_oelib_MapperRegistry::get('tx_oelib_Mapper_Country')
					->find($countryUid);
			} catch (Exception $exception) {
				$country = null;
			}
		} else {
			$country = null;
		}

		$place->setTitle(trim(strip_tags($formData[$prefix . 'title'])));
		$place->setAddress(trim(strip_tags($formData[$prefix . 'address'])));
		$place->setCity(trim(strip_tags($formData[$prefix . 'city'])));
		$place->setCountry($country);
		$place->setHomepage(trim(strip_tags($formData[$prefix . 'homepage'])));
		$place->setDirections(trim($formData[$prefix . 'directions']));
		$place->setNotes(trim(strip_tags($formData[$prefix . 'notes'])));
	}

	/**
	 * Shows a modalbox containing a form for editing an existing place record.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 * @param integer $placeUid the UID of the place to edit, must be > 0
	 *
	 * @return array calls to be executed on the client
	 */
	public static function showEditPlaceModalBox(
		tx_ameosformidable $formidable, $placeUid
	) {
		if ($placeUid <= 0) {
			return $formidable->majixExecJs('alert("$placeUid must be >= 0.");');
		}

		$placeMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Place');

		try {
			$place = $placeMapper->find(intval($placeUid));
		} catch (tx_oelib_Exception_NotFound $exception) {
			return $formidable->majixExecJs(
				'alert("A place with the given UID does not exist.");'
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		if ($place->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this place.");'
			);
		}

		try {
			$country = $place->getCountry();
			if ($country) {
				$countryUid = $country->getUid();
			} else {
				$countryUid = 0;
			}
		} catch (tx_oelib_Exception_NotFound $exception) {
			$countryUid = 0;
		}

		$fields = array(
			'uid' => $place->getUid(),
			'title' => $place->getTitle(),
			'address' => $place->getAddress(),
			'city' => $place->getCity(),
			'country' => $countryUid,
			'homepage' => $place->getHomepage(),
			'directions' => $place->getDirections(),
			'notes' => $place->getNotes(),
		);

		foreach ($fields as $key => $value) {
			$formidable->aORenderlets['editPlace_' . $key]->setValue($value);
		}

		$formidable->oRenderer->_setDisplayLabels(TRUE);
		$result = $formidable->aORenderlets['editPlaceModalBox']->majixShowBox();
		$formidable->oRenderer->_setDisplayLabels(FALSE);

		return $result;
	}

	/**
	 * Creates the basic data for a FE-entered auxiliary record (owner, PID).
	 *
	 * @return array the basic data as an associative array, will not be empty
	 */
	private static function createBasicAuxiliaryData() {
		$owner = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');
		$ownerPageUid = $owner->getAuxiliaryRecordsPid();

		$pageUid = ($ownerPageUid > 0)
			? $ownerPageUid
			: tx_oelib_ConfigurationRegistry::get('plugin.tx_seminars_pi1')
				->getAsInteger('createAuxiliaryRecordsPID');

		return array(
			'owner' => $owner,
			'pid' => $pageUid,
		);
	}

	/**
	 * Creates a new speaker record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 *
	 * @return array calls to be executed on the client
	 */
	public static function createNewSpeaker(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();
		$validationErrors = self::validateSpeaker(
			$formidable, array('title' => $formData['newSpeaker_title'])
		);
		if (!empty($validationErrors)) {
			return array(
				$formidable->majixExecJs(
					'alert("' . implode('\n', $validationErrors) . '");'
				),
			);
		};

		$speaker = tx_oelib_ObjectFactory::make('tx_seminars_Model_Speaker');
		$speaker->setData(array_merge(
			self::createBasicAuxiliaryData(),
			array('skills' => new tx_oelib_List())
		));
		self::setSpeakerData($speaker, 'newSpeaker_', $formData);
		$speaker->markAsDirty();
		tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker')
			->save($speaker);

		$editButtonConfiguration =& $formidable->_navConf(
			$formidable->aORenderlets['editSpeakerButton']->sXPath
		);
		$editButtonConfiguration['name'] = 'editSpeakerButton_' . $speaker->getUid();
		$editButtonConfiguration['onclick']['userobj']['php'] = '
			require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
			return tx_seminars_pi1_eventEditor::showEditSpeakerModalBox($this, ' . $speaker->getUid() . ');
			';
		$editButton = $formidable->_makeRenderlet(
			$editButtonConfiguration,
			$formidable->aORenderlets['editSpeakerButton']->sXPath
		);
		$editButton->includeScripts();
		$editButtonHTML = $editButton->_render();

		return array(
			$formidable->aORenderlets['newSpeakerModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'appendSpeakerInEditor(' . $speaker->getUid() . ', "' .
					addcslashes($speaker->getName(), '"\\') . '", {
						"name": "' . addcslashes($editButtonHTML['name'], '"\\') . '",
						"id": "' . addcslashes($editButtonHTML['id'], '"\\') . '",
						"value": "' . addcslashes($editButtonHTML['value'], '"\\') . '"
					});'
			),
		);
	}

	/**
	 * Updates an existing speaker record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 *
	 * @return array calls to be executed on the client
	 */
	public static function updateSpeaker(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$speakerMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker');

		try {
			$speaker = $speakerMapper->find(intval($formData['editSpeaker_uid']));
		} catch (Exception $exception) {
			return $formidable->majixExecJs(
				'alert("The speaker with the given UID does not exist.");'
			);
		}

		if ($speaker->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this speaker.");'
			);
		}

		$validationErrors = self::validateSpeaker(
			$formidable, array('title' => $formData['editSpeaker_title'])
		);
		if (!empty($validationErrors)) {
			return array(
				$formidable->majixExecJs(
					'alert("' . implode('\n', $validationErrors) . '");'
				),
			);
		};

		self::setSpeakerData($speaker, 'editSpeaker_', $formData);
		$speakerMapper->save($speaker);

		$speakerTypes = array(
			"speaker",
			"leader",
			"partner",
			"tutor",
		);

		$uid = $speaker->getUid();
		$name = $speaker->getName();

		$javaScript = '';
		foreach ($speakerTypes as $speakerType) {
			$javaScript .= 'updateAuxiliaryRecordInEditor("' .
				'tx_seminars_pi1_seminars_' .  $speakerType. '_label_' . $uid . '", ' .
				'"' . addcslashes($name, '"\\') . '"' .
				');';
		}

		return array(
			$formidable->aORenderlets['editSpeakerModalBox']->majixCloseBox(),
			$formidable->majixExecJs($javaScript),
		);
	}

	/**
	 * Validates the entered data for a speaker.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 * @param array $formData
	 *        the entered form data, the key must be stripped of the
	 *        "newSpeaker_"/"editSpeaker_" prefix
	 *
	 * @return array the error messages, will be empty if there are no
	 *         validation errors
	 */
	private static function validateSpeaker(
		tx_ameosformidable $formidable, array $formData
	) {
		$validationErrors = array();
		if (trim($formData['title']) == '') {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_emptyName'
			);
		}

		return $validationErrors;
	}

	/**
	 * Sets the data of a speaker model based on the data given in $formData.
	 *
	 * @param tx_seminars_Model_Speaker $speaker
	 *        the speaker model to set the data for
	 * @param string $prefix the prefix of the form fields in $formData
	 * @param array $formData the form data to use for setting the speaker data
	 */
	private static function setSpeakerData(tx_seminars_Model_Speaker $speaker, $prefix, array $formData) {
		$skillMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Skill');
		$skills = tx_oelib_ObjectFactory::make('tx_oelib_List');

		if (is_array($formData[$prefix . 'skills'])) {
			foreach ($formData[$prefix . 'skills'] as $rawUid) {
				$safeUid = intval($rawUid);
				if ($safeUid > 0) {
					$skills->add($skillMapper->find($safeUid));
				}
			}

		}

		$speaker->setSkills($skills);

		$speaker->setName(trim(strip_tags($formData[$prefix . 'title'])));
		$speaker->setGender(intval($formData[$prefix . 'gender']));
		$speaker->setOrganization($formData[$prefix . 'organization']);
		$speaker->setHomepage(trim(strip_tags($formData[$prefix . 'homepage'])));
		$speaker->setDescription(trim($formData[$prefix . 'description']));
		$speaker->setNotes(trim(strip_tags($formData[$prefix . 'notes'])));
		$speaker->setAddress(trim(strip_tags($formData[$prefix . 'address'])));
		$speaker->setPhoneWork(trim(strip_tags($formData[$prefix . 'phone_work'])));
		$speaker->setPhoneHome(trim(strip_tags($formData[$prefix . 'phone_home'])));
		$speaker->setPhoneMobile(trim(strip_tags($formData[$prefix . 'phone_mobile'])));
		$speaker->setFax(trim(strip_tags($formData[$prefix . 'fax'])));
		$speaker->setEMailAddress(trim(strip_tags($formData[$prefix . 'email'])));
		$speaker->setCancelationPeriod(intval($formData[$prefix . 'cancelation_period']));
	}

	/**
	 * Shows a modalbox containing a form for editing an existing speaker record.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 * @param integer $speakerUid the UID of the speaker to edit, must be > 0
	 *
	 * @return array calls to be executed on the client
	 */
	public static function showEditSpeakerModalBox(
		tx_ameosformidable $formidable, $speakerUid
	) {
		if ($speakerUid <= 0) {
			return $formidable->majixExecJs('alert("$speakerUid must be >= 0.");');
		}

		$speakerMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Speaker');

		try {
			$speaker = $speakerMapper->find(intval($speakerUid));
		} catch (tx_oelib_Exception_NotFound $exception) {
			return $formidable->majixExecJs(
				'alert("A speaker with the given UID does not exist.");'
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		if ($speaker->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this speaker.");'
			);
		}

		$fields = array(
			'uid' => $speaker->getUid(),
			'title' => $speaker->getName(),
			'gender' => $speaker->getGender(),
			'organization' => $speaker->getOrganization(),
			'homepage' => $speaker->getHomepage(),
			'description' => $speaker->getDescription(),
			'notes' => $speaker->getNotes(),
			'address' => $speaker->getAddress(),
			'phone_work' => $speaker->getPhoneWork(),
			'phone_home' => $speaker->getPhoneHome(),
			'phone_mobile' => $speaker->getPhoneMobile(),
			'fax' => $speaker->getFax(),
			'email' => $speaker->getEMailAddress(),
			'cancelation_period' => $speaker->getCancelationPeriod(),
		);

		foreach ($fields as $key => $value) {
			$formidable->aORenderlets['editSpeaker_' . $key]->setValue($value);
		}

		$result = array();

		$formidable->oRenderer->_setDisplayLabels(TRUE);
		$result[] = $formidable->aORenderlets['editSpeakerModalBox']->majixShowBox();
		$formidable->oRenderer->_setDisplayLabels(FALSE);

		$result[] = $formidable->aORenderlets['editSpeaker_skills']->majixCheckNone();

		$skills = $speaker->getSkills();
		foreach ($skills as $skill) {
			$result[] = $formidable->aORenderlets['editSpeaker_skills']
				->majixCheckItem($skill->getUid());
		}

		return $result;
	}

	/**
	 * Creates a new checkbox record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 *
	 * @return array calls to be executed on the client
	 */
	public static function createNewCheckbox(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();
		$validationErrors = self::validateCheckbox(
			$formidable, array('title' => $formData['newCheckbox_title'])
		);
		if (!empty($validationErrors)) {
			return array(
				$formidable->majixExecJs(
					'alert("' . implode('\n', $validationErrors) . '");'
				),
			);
		};

		$checkbox = tx_oelib_ObjectFactory::make('tx_seminars_Model_Checkbox');
		$checkbox->setData(self::createBasicAuxiliaryData());
		self::setCheckboxData($checkbox, 'newCheckbox_', $formData);
		$checkbox->markAsDirty();
		tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Checkbox')
			->save($checkbox);

		$editButtonConfiguration =& $formidable->_navConf(
			$formidable->aORenderlets['editCheckboxButton']->sXPath
		);
		$editButtonConfiguration['name'] = 'editCheckboxButton_' . $checkbox->getUid();
		$editButtonConfiguration['onclick']['userobj']['php'] = '
			require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
			return tx_seminars_pi1_eventEditor::showEditCheckboxModalBox($this, ' . $checkbox->getUid() . ');
			';
		$editButton = $formidable->_makeRenderlet(
			$editButtonConfiguration,
			$formidable->aORenderlets['editCheckboxButton']->sXPath
		);
		$editButton->includeScripts();
		$editButtonHTML = $editButton->_render();

		return array(
			$formidable->aORenderlets['newCheckboxModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'appendCheckboxInEditor(' . $checkbox->getUid() . ', "' .
					addcslashes($checkbox->getTitle(), '"\\') . '", {
						"name": "' . addcslashes($editButtonHTML['name'], '"\\') . '",
						"id": "' . addcslashes($editButtonHTML['id'], '"\\') . '",
						"value": "' . addcslashes($editButtonHTML['value'], '"\\') . '"
					});'
			),
		);
	}

	/**
	 * Updates an existing checkbox record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 *
	 * @return array calls to be executed on the client
	 */
	public static function updateCheckbox(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$checkboxMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Checkbox');

		try {
			$checkbox = $checkboxMapper->find(intval($formData['editCheckbox_uid']));
		} catch (Exception $exception) {
			return $formidable->majixExecJs(
				'alert("The checkbox with the given UID does not exist.");'
			);
		}

		if ($checkbox->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this checkbox.");'
			);
		}

		$validationErrors = self::validateCheckbox(
			$formidable,
			array('title' => $formData['editCheckbox_title'])
		);
		if (!empty($validationErrors)) {
			return $formidable->majixExecJs(
				'alert("' . implode('\n', $validationErrors) . '");'
			);
		};

		self::setCheckboxData($checkbox, 'editCheckbox_', $formData);
		$checkboxMapper->save($checkbox);

		$htmlId = 'tx_seminars_pi1_seminars_checkbox_label_' . $checkbox->getUid();

		return array(
			$formidable->aORenderlets['editCheckboxModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'updateAuxiliaryRecordInEditor("' . $htmlId . '", "' .
					addcslashes($checkbox->getTitle(), '"\\') . '")'
			),
		);
	}

	/**
	 * Validates the entered data for a checkbox.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 * @param array $formData
	 *        the entered form data, the key must be stripped of the
	 *        "newCheckbox_"/"editCheckbox_" prefix
	 *
	 * @return array the error messages, will be empty if there are no
	 *         validation errors
	 */
	private static function validateCheckbox(
		tx_ameosformidable $formidable, array $formData
	) {
		$validationErrors = array();
		if (trim($formData['title']) == '') {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_emptyTitle'
			);
		}

		return $validationErrors;
	}

	/**
	 * Sets the data of a checkbox model based on the data given in $formData.
	 *
	 * @param tx_seminars_Model_Checkbox $checkbox the checkbox model to set the data
	 * @param string $prefix the prefix of the form fields in $formData
	 * @param array $formData the form data to use for setting the checkbox data
	 */
	private static function setCheckboxData(
		tx_seminars_Model_Checkbox $checkbox, $prefix, array $formData
	) {
		$checkbox->setTitle($formData[$prefix . 'title']);
	}

	/**
	 * Shows a modalbox containing a form for editing an existing checkbox record.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 * @param integer $checkboxUid the UID of the checkbox to edit, must be > 0
	 *
	 * @return array calls to be executed on the client
	 */
	public static function showEditCheckboxModalBox(
		tx_ameosformidable $formidable, $checkboxUid
	) {
		if ($checkboxUid <= 0) {
			return $formidable->majixExecJs('alert("$checkboxUid must be >= 0.");');
		}

		$checkboxMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Checkbox');

		try {
			$checkbox = $checkboxMapper->find(intval($checkboxUid));
		} catch (tx_oelib_Exception_NotFound $exception) {
			return $formidable->majixExecJs(
				'alert("A checkbox with the given UID does not exist.");'
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		if ($checkbox->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this checkbox.");'
			);
		}

		$fields = array(
			'uid' => $checkbox->getUid(),
			'title' => $checkbox->getTitle(),
		);

		foreach ($fields as $key => $value) {
			$formidable->aORenderlets['editCheckbox_' . $key]->setValue($value);
		}

		$formidable->oRenderer->_setDisplayLabels(TRUE);
		$result = $formidable->aORenderlets['editCheckboxModalBox']->majixShowBox();
		$formidable->oRenderer->_setDisplayLabels(FALSE);

		return $result;
	}

	/**
	 * Creates a new target group record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 *
	 * @return array calls to be executed on the client
	 */
	public static function createNewTargetGroup(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();
		$validationErrors = self::validateTargetGroup(
			$formidable,
			array(
				'title' => $formData['newTargetGroup_title'],
				'minimum_age' => $formData['newTargetGroup_minimum_age'],
				'maximum_age' => $formData['newTargetGroup_maximum_age'],
			)
		);
		if (!empty($validationErrors)) {
			return array(
				$formidable->majixExecJs(
					'alert("' . implode('\n', $validationErrors) . '");'
				),
			);
		};

		$targetGroup
			= tx_oelib_ObjectFactory::make('tx_seminars_Model_TargetGroup');
		$targetGroup->setData(self::createBasicAuxiliaryData());
		self::setTargetGroupData($targetGroup, 'newTargetGroup_', $formData);
		$targetGroup->markAsDirty();
		tx_oelib_MapperRegistry::get('tx_seminars_Mapper_TargetGroup')
			->save($targetGroup);

		$editButtonConfiguration =& $formidable->_navConf(
			$formidable->aORenderlets['editTargetGroupButton']->sXPath
		);
		$editButtonConfiguration['name'] = 'editTargetGroupButton_' .
			$targetGroup->getUid();
		$editButtonConfiguration['onclick']['userobj']['php'] = '
			require_once(t3lib_extMgm::extPath(\'oelib\') . \'class.tx_oelib_Autoloader.php\');
			return tx_seminars_pi1_eventEditor::showEditTargetGroupModalBox($this, ' . $targetGroup->getUid() . ');
			';
		$editButton = $formidable->_makeRenderlet(
			$editButtonConfiguration,
			$formidable->aORenderlets['editTargetGroupButton']->sXPath
		);
		$editButton->includeScripts();
		$editButtonHTML = $editButton->_render();

		return array(
			$formidable->aORenderlets['newTargetGroupModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'appendTargetGroupInEditor(' . $targetGroup->getUid() . ', "' .
					addcslashes($targetGroup->getTitle(), '"\\') . '", {
						"name": "' . addcslashes($editButtonHTML['name'], '"\\') . '",
						"id": "' . addcslashes($editButtonHTML['id'], '"\\') . '",
						"value": "' . addcslashes($editButtonHTML['value'], '"\\') . '"
					});'
			),
		);
	}

	/**
	 * Updates an existing target group record.
	 *
	 * This function is intended to be called via an AJAX FORMidable event.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 *
	 * @return array calls to be executed on the client
	 */
	public static function updateTargetGroup(tx_ameosformidable $formidable) {
		$formData = $formidable->oMajixEvent->getParams();

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		$targetGroupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_TargetGroup');

		try {
			$targetGroup = $targetGroupMapper->find(intval($formData['editTargetGroup_uid']));
		} catch (Exception $exception) {
			return $formidable->majixExecJs(
				'alert("The target group with the given UID does not exist.");'
			);
		}

		if ($targetGroup->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this target group.");'
			);
		}

		$validationErrors = self::validateTargetGroup(
			$formidable,
			array(
				'title' => $formData['editTargetGroup_title'],
				'minimum_age' => $formData['editTargetGroup_minimum_age'],
				'maximum_age' => $formData['editTargetGroup_maximum_age'],
			)
		);
		if (!empty($validationErrors)) {
			return $formidable->majixExecJs(
				'alert("' . implode('\n', $validationErrors) . '");'
			);
		};

		self::setTargetGroupData($targetGroup, 'editTargetGroup_', $formData);
		$targetGroupMapper->save($targetGroup);

		$htmlId = 'tx_seminars_pi1_seminars_target_group_label_' . $targetGroup->getUid();

		return array(
			$formidable->aORenderlets['editTargetGroupModalBox']->majixCloseBox(),
			$formidable->majixExecJs(
				'updateAuxiliaryRecordInEditor("' . $htmlId . '", "' .
					addcslashes($targetGroup->getTitle(), '"\\') . '")'
			),
		);
	}

	/**
	 * Validates the entered data for a target group.
	 *
	 * @param tx_ameosformidable $formidable
	 *        the FORMidable object for the AJAX call
	 * @param array $formData
	 *        the entered form data, the key must be stripped of the
	 *        "newTargetGroup_"/"editTargetGroup_" prefix
	 *
	 * @return array the error messages, will be empty if there are no
	 *         validation errors
	 */
	private static function validateTargetGroup(
		tx_ameosformidable $formidable, array $formData
	) {
		$validationErrors = array();
		if (trim($formData['title']) == '') {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_emptyTitle'
			);
		}
		if (preg_match('/^(\d*)$/', trim($formData['minimum_age']))
			&& preg_match('/^(\d*)$/', trim($formData['maximum_age']))
		) {
			$minimumAge = $formData['minimum_age'];
			$maximumAge = $formData['maximum_age'];

			if (($minimumAge > 0) && ($maximumAge > 0)) {
				if ($minimumAge > $maximumAge) {
					$validationErrors[] = $formidable->getLLLabel(
						'LLL:EXT:seminars/pi1/locallang.xml:' .
							'message_targetGroupMaximumAgeSmallerThanMinimumAge'
					);
				}
			}
		} else {
			$validationErrors[] = $formidable->getLLLabel(
				'LLL:EXT:seminars/pi1/locallang.xml:message_noTargetGroupAgeNumber'
			);
		}

		return $validationErrors;
	}

	/**
	 * Sets the data of a target group model based on the data given in
	 * $formData.
	 *
	 * @param tx_seminars_Model_TargetGroup $targetGroup
	 *        the target group model to set the data
	 * @param string $prefix the prefix of the form fields in $formData
	 * @param array $formData
	 *        the form data to use for setting the target group data
	 */
	private static function setTargetGroupData(
		tx_seminars_Model_TargetGroup $targetGroup, $prefix, array $formData
	) {
		$targetGroup->setTitle($formData[$prefix . 'title']);
		$targetGroup->setMinimumAge(intval($formData[$prefix . 'minimum_age']));
		$targetGroup->setMaximumAge(intval($formData[$prefix . 'maximum_age']));
	}

	/**
	 * Shows a modalbox containing a form for editing an existing target group
	 * record.
	 *
	 * @param tx_ameos_formidable $formidable the FORMidable object
	 * @param integer $targetGroupUid
	 *        the UID of the target group to edit, must be > 0
	 *
	 * @return array calls to be executed on the client
	 */
	public static function showEditTargetGroupModalBox(
		tx_ameosformidable $formidable, $targetGroupUid
	) {
		if ($targetGroupUid <= 0) {
			return $formidable->majixExecJs('alert("$targetGroupUid must be >= 0.");');
		}

		$targetGroupMapper = tx_oelib_MapperRegistry::get(
			'tx_seminars_Mapper_TargetGroup'
		);

		try {
			$targetGroup = $targetGroupMapper->find(intval($targetGroupUid));
		} catch (tx_oelib_Exception_NotFound $exception) {
			return $formidable->majixExecJs(
				'alert("A target group with the given UID does not exist.");'
			);
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		if ($targetGroup->getOwner() !== $frontEndUser) {
			return $formidable->majixExecJs(
				'alert("You are not allowed to edit this target group.");'
			);
		}

		$minimumAge = ($targetGroup->getMinimumAge() > 0)
			? $targetGroup->getMinimumAge() : '';
		$maximumAge = ($targetGroup->getMaximumAge() > 0)
			? $targetGroup->getMaximumAge() : '';

		$fields = array(
			'uid' => $targetGroup->getUid(),
			'title' => $targetGroup->getTitle(),
			'minimum_age' => $minimumAge,
			'maximum_age' => $maximumAge,
		);

		foreach ($fields as $key => $value) {
			$formidable->aORenderlets['editTargetGroup_' . $key]->setValue($value);
		}

		$formidable->oRenderer->_setDisplayLabels(TRUE);
		$result = $formidable->aORenderlets['editTargetGroupModalBox']->majixShowBox();
		$formidable->oRenderer->_setDisplayLabels(FALSE);

		return $result;
	}

	/**
	 * Provides data items for the list of countries.
	 *
	 * @return array items as an array with the keys "caption" (for the title)
	 *         and "value" (for the UID)
	 */
	public static function populateListCountries() {
		$result = array();

		$countries = tx_oelib_MapperRegistry::get('tx_oelib_Mapper_Country')
			->findAll('cn_short_local');
		foreach ($countries as $country) {
			$result[] = array(
				'caption' => $country->getLocalShortName(),
				'value' => $country->getUid(),
			);
		}

		return $result;
	}

	/**
	 * Provides data items for the list of skills.
	 *
	 * @return array items as an array with the keys "caption" (for the title)
	 *         and "value" (for the UID)
	 */
	public static function populateListSkills() {
		$skills = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Skill')
			->findAll('title ASC');

		return self::makeListToFormidableList($skills);
	}

	/**
	 * Returns an array of caption value pairs for formidable checkboxes.
	 *
	 * @param tx_oelib_List $models
	 *        List of models to show in the checkboxes, may be empty
	 *
	 * @return array items as an array with the keys "caption" (for the title)
	 *         and "value" (for the UID), will be empty if an empty model list
	 *         was provided
	 */
	public static function makeListToFormidableList(tx_oelib_List $models) {
		if ($models->isEmpty()) {
			return array();
		}

		$result = array();

		foreach ($models as $model) {
			$result[] = array(
				'caption' => $model->getTitle(),
				'value' => $model->getUid(),
			);
		}

		return $result;
	}

	/**
	 * Returns the UID of the preselected organizer.
	 *
	 * @return integer the UID of the preselected organizer; if more than one
	 *                 organizer is available, zero will be returned
	 */
	public function getPreselectedOrganizer() {
		$availableOrganizers = $this->populateListOrganizers(array());
		if (count($availableOrganizers) != 1) {
			return 0;
		}

		$organizerData = array_pop($availableOrganizers);

		return $organizerData['value'];
	}

	/**
	 * Returns the allowed PIDs for the auxiliary records.
	 *
	 * @return string comma-sparated list of PIDs for the auxiliary records, may
	 *                be empty
	 */
	private function getPidsForAuxiliaryRecords() {
		$recordPids = array();
		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');
		$auxiliaryRecordsPid = $frontEndUser->getAuxiliaryRecordsPid();
		if ($auxiliaryRecordsPid == 0) {
			$auxiliaryRecordsPid
				= tx_oelib_ConfigurationRegistry::get('plugin.tx_seminars_pi1')
					->getAsInteger('createAuxiliaryRecordsPID');
		}

		if (tx_oelib_configurationProxy::getInstance('seminars')
			->getAsBoolean('useStoragePid')
		) {
			$recordPids[] = $this->getStoragePid();
		}
		if ($auxiliaryRecordsPid != 0) {
			$recordPids[] = $auxiliaryRecordsPid;
		}

		return implode(',', $recordPids);
	}

	/**
	 * Adds the default categories of the currently logged-in user to the
	 * event.
	 *
	 * Note: This affects only new records. Existing records (with a UID) will
	 * not be changed.
	 *
	 * @param array $formData
	 *        all entered form data with the field names as keys, will be
	 *        modified, must not be empty
	 */
	private function addCategoriesOfUser(array &$formData) {
		$eventUid = $this->getObjectUid();
		if ($eventUid > 0) {
			return;
		}
		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');
		if (!$frontEndUser->hasDefaultCategories()) {
			return;
		}

		$formData['categories'] =
			$frontEndUser->getDefaultCategoriesFromGroup()->getUids();
	}

	/**
	 * Removes the category field if the user has default categories set.
	 *
	 * @param array $formFields
	 *        the fields which should be checked for category, will be modified,
	 *        may be empty
	 */
	private function removeCategoryIfNecessary(array &$formFields) {
		if (!in_array('categories', $formFields)) {
			return;
		}

		$frontEndUser = tx_oelib_FrontEndLoginManager::getInstance()
			->getLoggedInUser('tx_seminars_Mapper_FrontEndUser');

		if ($frontEndUser->hasDefaultCategories()) {
			$categoryKey = array_search('categories', $formFields);
			unset($formFields[$categoryKey]);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_eventEditor.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_eventEditor.php']);
}
?>