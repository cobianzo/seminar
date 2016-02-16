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
 * Class 'tx_seminars_Mapper_Event' for the 'seminars' extension.
 *
 * This class represents a mapper for events.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_Mapper_Event extends tx_oelib_DataMapper {
	/**
	 * @var string the name of the database table for this mapper
	 */
	protected $tableName = 'tx_seminars_seminars';

	/**
	 * @var string the model class name for this mapper, must not be empty
	 */
	protected $modelClassName = 'tx_seminars_Model_Event';

	/**
	 * @var array the (possible) relations of the created models in the format
	 *            DB column name => mapper name
	 */
	protected $relations = array(
		'topic' => 'tx_seminars_Mapper_Event',
		'categories' => 'tx_seminars_Mapper_Category',
		'event_type' => 'tx_seminars_Mapper_EventType',
		'timeslots' => 'tx_seminars_Mapper_TimeSlot',
		'place' => 'tx_seminars_Mapper_Place',
		'lodgings' => 'tx_seminars_Mapper_Lodging',
		'foods' => 'tx_seminars_Mapper_Food',
		'speakers' => 'tx_seminars_Mapper_Speaker',
		'partners' => 'tx_seminars_Mapper_Speaker',
		'tutors' => 'tx_seminars_Mapper_Speaker',
		'leaders' => 'tx_seminars_Mapper_Speaker',
		'payment_methods' => 'tx_seminars_Mapper_PaymentMethod',
		'organizers' => 'tx_seminars_Mapper_Organizer',
		'organizing_partners' => 'tx_seminars_Mapper_Organizer',
		'target_groups' => 'tx_seminars_Mapper_TargetGroup',
		'owner_feuser' => 'tx_oelib_Mapper_FrontEndUser',
		'vips' => 'tx_oelib_Mapper_FrontEndUser',
		'checkboxes' => 'tx_seminars_Mapper_Checkbox',
		'requirements' => 'tx_seminars_Mapper_Event',
		'dependencies' => 'tx_seminars_Mapper_Event',
	);

	/**
	 * Retrieves an event model with the publication hash provided.
	 *
	 * @param string $publicationHash
	 *        the publication hash to find the event for, must not be empty
	 *
	 * @return tx_seminars_Model_Event the event with the publication hash
	 *                                 provided, will be null if no event could
	 *                                 be found
	 */
	public function findByPublicationHash($publicationHash) {
		if ($publicationHash == '') {
			throw new InvalidArgumentException('The given publication hash was empty.', 1333292411);
		}

		try {
			$result = $this->findSingleByWhereClause(
				array('publication_hash' => $publicationHash)
			);
		} catch (tx_oelib_Exception_NotFound $exception) {
			$result = null;
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Mapper/class.tx_seminars_Mapper_Event.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Mapper/class.tx_seminars_Mapper_Event.php']);
}
?>