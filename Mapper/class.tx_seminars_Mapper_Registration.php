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
 * Class tx_seminars_Mapper_Registration for the "seminars" extension.
 *
 * This class represents a mapper for registrations.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_Mapper_Registration extends tx_oelib_DataMapper {
	/**
	 * @var string the name of the database table for this mapper
	 */
	protected $tableName = 'tx_seminars_attendances';

	/**
	 * @var string the model class name for this mapper, must not be empty
	 */
	protected $modelClassName = 'tx_seminars_Model_Registration';

	/**
	 * @var array the (possible) relations of the created models in the format
	 *            DB column name => mapper name
	 */
	protected $relations = array(
		'seminar' => 'tx_seminars_Mapper_Event',
		'user' => 'tx_seminars_Mapper_FrontEndUser',
		'currency' => 'tx_oelib_Mapper_Currency',
		'method_of_payment' => 'tx_seminars_Mapper_PaymentMethod',
		'lodgings' => 'tx_seminars_Mapper_Lodging',
		'foods' => 'tx_seminars_Mapper_Food',
		'checkboxes' => 'tx_seminars_Mapper_Checkbox',
	);
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Mapper/class.tx_seminars_Mapper_Registration.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Mapper/class.tx_seminars_Mapper_Registration.php']);
}
?>