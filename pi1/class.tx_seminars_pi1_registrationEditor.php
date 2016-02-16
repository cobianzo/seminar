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

require_once(t3lib_extMgm::extPath('static_info_tables') . 'pi1/class.tx_staticinfotables_pi1.php');

/**
 * Class 'tx_seminars_pi1_registrationEditor' for the 'seminars' extension.
 *
 * This class is a controller which allows to create registrations on the FE.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_pi1_registrationEditor extends tx_seminars_pi1_frontEndEditor {
	/**
	 * same as class name
	 *
	 * @var string
	 */
	public $prefixId = 'tx_seminars_registration_editor';

	/**
	 * path to this script relative to the extension dir
	 *
	 * @var string
	 */
	public $scriptRelPath = 'pi1/class.tx_seminars_pi1_registrationEditor.php';

	/**
	 * the names of the form fields to show (with the keys being the same as
	 * the values for performance reasons)
	 *
	 * @var array
	 */
	private $formFieldsToShow = array();

	/**
	 * the number of the current page of the form (starting with 0 for the first
	 * page)
	 *
	 * @var integer
	 */
	public $currentPageNumber = 0;

	/**
	 * fields that are part of the billing address, with the value controlling
	 * if the field will be displayed with a label on the second page of the
	 * registration form
	 *
	 * @var array
	 */
	private $fieldsInBillingAddress = array(
		'company' => FALSE,
		'gender' => FALSE,
		'name' => FALSE,
		'address' => FALSE,
		'zip' => FALSE,
		'city' => FALSE,
		'country' => FALSE,
		'telephone' => TRUE,
		'email' => TRUE
	);

	/**
	 * @var tx_staticinfotables_pi1
	 */
	private $staticInfo = null;

	/**
	 * @var tx_seminars_seminar seminar object
	 */
	private $seminar = null;

	/**
	 * @var tx_seminars_registration
	 */
	protected $registration = NULL;

	/**
	 * The constructor.
	 *
	 * This class may only be instantiated after is has already been made sure
	 * that the logged-in user is allowed to register for the corresponding
	 * event (or edit a registration).
	 *
	 * Please note that it is necessary to call setAction() and setSeminar()
	 * directly after instantiation.
	 *
	 * @param array TypoScript configuration for the plugin
	 * @param tslib_cObj the parent cObj content, needed for the flexforms
	 */
	public function __construct(array $configuration, tslib_cObj $cObj) {
		parent::__construct($configuration, $cObj);

		$formFieldsToShow = t3lib_div::trimExplode(',',
			$this->getConfValueString(
				'showRegistrationFields', 's_template_special'
			), TRUE
		);
		foreach ($formFieldsToShow as $currentFormField) {
			$this->formFieldsToShow[$currentFormField] = $currentFormField;
		}
	}

	/**
	 * Frees as much memory that has been used by this object as possible.
	 */
	public function __destruct() {
		unset($this->staticInfo, $this->seminar);
		parent::__destruct();
	}

	/**
	 * Sets the action.
	 *
	 * @param string action for which to create the form, must be either
	 *               "register" or "unregister", must not be empty
	 */
	public function setAction($action) {
		$this->setFormConfiguration($action);
	}

	/**
	 * Sets the seminar for which to create the form.
	 *
	 * @param tx_seminars_seminar $event the event for which to create the form
	 */
	public function setSeminar(tx_seminars_seminar $event) {
		$this->seminar = $event;
	}

	/**
	 * Returns the configured seminar object.
	 *
	 * @return tx_seminars_seminar the seminar instance
	 */
	private function getSeminar() {
		if (!is_object($this->seminar)) {
			throw new BadMethodCallException('Please set a proper seminar object via $this->setSeminar().', 1333293187);
		}

		return $this->seminar;
	}

	/**
	 * Sets the registration for which to create the unregistration form.
	 */
	public function setRegistration(tx_seminars_registration $registration) {
		$this->registration = $registration;
	}

	/**
	 * Returns the current registration object.
	 *
	 * @return tx_seminars_registration the registration, will be NULL if none has been set
	 */
	private function getRegistration() {
		return $this->registration;
	}

	/**
	 * Sets the form configuration to use.
	 *
	 * @param string action to perform, may be either "register" or "unregister",
	 *               must not be empty
	 */
	public function setFormConfiguration($action = 'register') {
		switch ($action) {
			case 'unregister':
				$formConfiguration = $this->conf['form.']['unregistration.'];
				break;
			case 'register':
				// The fall-through is intended.
			default:
				// The current page number will be 1 if a 3-click registration
				// is configured and the first page was submitted successfully.
				// It will be 2 for a 3-click registration and the second page
				// submitted successfully. It will also be 2 for a 2-click
				// registration and the first page submitted successfully.
				// Note that to display the second page, this function is called
				// two times in a row if the current page number is higher than
				// zero. It is only the second page, that can process the
				// registration.
				if (($this->currentPageNumber == 1)
					|| ($this->currentPageNumber == 2)
				) {
					$formConfiguration = $this->conf['form.']['registration.']['step2.'];
				} else {
					$formConfiguration = $this->conf['form.']['registration.']['step1.'];
				}
				break;
		}

		parent::setFormConfiguration($formConfiguration);
	}

	/**
	 * Creates the HTML output.
	 *
	 * @return string HTML of the create/edit form
	 */
	public function render() {
		$rawForm = parent::render();
		// For the confirmation page, we need to reload the whole thing. Yet,
		// the previous rendering still is necessary for processing the data.
		if ($this->currentPageNumber > 0) {
			$this->discardRenderedForm();
			$this->setFormConfiguration();
			// This will produce a new form to which no data can be provided.
			$rawForm = $this->makeFormCreator()->render();
		}

		// Remove empty label tags that have been created due to a bug in
		// FORMidable.
		$rawForm = preg_replace('/<label[^>]*><\/label>/', '', $rawForm);
		$this->processTemplate($rawForm);
		$this->setLabels();
		$this->hideUnusedFormFields();

		$this->setMarker('feuser_data', $this->getAllFeUserData());
		$this->setMarker('billing_address', $this->getBillingAddress());
		$this->setMarker('registration_data', $this->getRegistrationData());

		return $this->getSubpart('', 2);
	}

	/**
	 * Discards the rendered FORMIdable form from the page, including any header
	 * data.
	 */
	private function discardRenderedForm() {
		// A mayday would be returned without unsetting the form ID.
		unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ameos_formidable']
			['context']['forms']['tx_seminars_pi1_registration_editor']);
		if (!is_array($GLOBALS['TSFE']->additionalHeaderData)) {
			return;
		}

		foreach ($GLOBALS['TSFE']->additionalHeaderData as $key => $content) {
			if (strpos($content, 'FORMIDABLE:') !== FALSE) {
				unset($GLOBALS['TSFE']->additionalHeaderData[$key]);
			}
		}
	}

	/**
	 * Selects the confirmation page (the second step of the registration form)
	 * for display. This affects $this->render().
	 *
	 * @param array the entered form data with the field names as array keys
	 *              (including the submit button)
	 */
	public function setPage(array $parameters) {
		$this->currentPageNumber = $parameters['next_page'];
	}

	/**
	 * Checks whether we are on the last page of the registration form and we
	 * can proceed to saving the registration.
	 *
	 * @return boolean TRUE if we can proceed to saving the registration, FALSE
	 *                 otherwise
	 */
	public function isLastPage() {
		return ($this->currentPageNumber == 2);
	}

	/**
	 * Processes the entered/edited registration and stores it in the DB.
	 *
	 * In addition, the entered payment data is stored in the FE user session.
	 *
	 * @param array the entered form data with the field names as array keys
	 *              (including the submit button ...)
	 */
	public function processRegistration(array $parameters) {
		$this->saveDataToSession($parameters);

		if ($this->getRegistrationManager()->canCreateRegistration(
			$this->getSeminar(), $parameters
		)) {
			$this->getRegistrationManager()->createRegistration(
				$this->getSeminar(), $parameters, $this
			);
		}
	}

	/**
	 * Checks whether there are at least the number of seats provided in
	 * $formData['value'] available.
	 *
	 * @param array associative array with the element "value" in which
	 *              the number of seats to check for is stored
	 *
	 * @return boolean TRUE if there are at least $formData['value'] seats
	 *                 available, FALSE otherwise
	 */
	public function canRegisterSeats(array $formData) {
		return $this->getRegistrationManager()->canRegisterSeats(
			$this->getSeminar(), intval($formData['value'])
		);
	}

	/**
	 * Checks whether a checkbox is checked OR the "finish registration" button
	 * hasn't just been clicked.
	 *
	 * @param array associative array with the element "value" in which
	 *              the current value of the checkbox (0 or 1) is stored
	 *
	 * @return boolean TRUE if the checkbox is checked or we are not on the
	 *                 confirmation page, FALSE otherwise
	 */
	public function isTermsChecked(array $formData) {
		return ((boolean) $formData['value'])
			|| ($this->currentPageNumber != 2);
	}

	/**
	 * Checks whether the "travelling terms" checkbox (ie. the second "terms"
	 * checkbox) is enabled in the event record *and* via TS setup.
	 *
	 * @return boolean TRUE if the "travelling terms" checkbox is enabled in the
	 *                 event record *and* via TS setup, FALSE otherwise
	 */
	public function isTerms2Enabled() {
		return $this->hasRegistrationFormField(array('elementname' => 'terms_2'))
			&& $this->getSeminar()->hasTerms2();
	}

	/**
	 * Checks whether the "terms_2" checkbox is checked (if it is enabled in the
	 * configuration). If the checkbox is disabled in the configuration, this
	 * function always returns TRUE. It also always returns TRUE if the
	 * "finish registration" button hasn't just been clicked.
	 *
	 * @param array associative array with the element "value" in which
	 *              the current value of the checkbox (0 or 1) is stored
	 *
	 * @return boolean TRUE if the checkbox is checked or disabled in the
	 *                 configuration or if the "finish registration" button
	 *                 hasn't just been clicked, FALSE if it is not checked
	 *                 AND enabled in the configuration
	 */
	public function isTerms2CheckedAndEnabled(array $formData) {
		return ((boolean) $formData['value']) || !$this->isTerms2Enabled()
			|| ($this->currentPageNumber != 2);
	}

	/**
	 * Checks whether a method of payment is selected OR this event has no
	 * payment methods set at all OR the corresponding registration field is
	 * not visible in the registration form (in which case it is neither
	 * necessary nor possible to select any payment method) OR this event has
	 * no price at all.
	 *
	 * @param array associative array with the element "value" in which
	 *              the currently selected value (a positive integer or
	 *              null if no radiobutton is selected) is stored
	 *
	 * @return boolean TRUE if a method of payment is selected OR no method
	 *                 could have been selected at all OR this event has no
	 *                 price, FALSE if none is selected, but should have
	 *                 been selected
	 */
	public function isMethodOfPaymentSelected(array $formData) {
		return $this->isRadiobuttonSelected($formData['value'])
			|| !$this->getSeminar()->hasPaymentMethods()
			|| !$this->getSeminar()->hasAnyPrice()
			|| !$this->showMethodsOfPayment();
	}

	/**
	 * Checks whether a radiobutton in a radiobutton group is selected.
	 *
	 * @param mixed the currently selected value (a positive integer) or
	 *                  null if no button is selected
	 *
	 * @return boolean TRUE if a radiobutton is selected, FALSE if none is
	 *                 selected
	 */
	private function isRadiobuttonSelected($radiogroupValue) {
		return (boolean) $radiogroupValue;
	}

	/**
	 * Checks whether a form field should be displayed (and evaluated) at all.
	 * This is specified via TS setup (or flexforms) using the
	 * "showRegistrationFields" variable.
	 *
	 * @param array the contents of the "params" child of the userobj
	 *              node as key/value pairs (used for retrieving the
	 *              current form field name)
	 *
	 * @return boolean TRUE if the current form field should be displayed,
	 *                 FALSE otherwise
	 */
	public function hasRegistrationFormField(array $parameters) {
		return isset($this->formFieldsToShow[$parameters['elementname']]);
	}

	/**
	 * Checks whether a form field should be displayed (and evaluated) at all.
	 * This is specified via TS setup (or flexforms) using the
	 * "showRegistrationFields" variable.
	 *
	 * In addition, this function takes into account whether the form field
	 * actually has any meaningful content.
	 * Example: The payment methods field will be disabled if the current event
	 * does not have any payment methods.
	 *
	 * After some refactoring, this function will replace the function
	 * hasRegistrationFormField.
	 *
	 * @param string the key of the field to test, must not be empty
	 *
	 * @return boolean TRUE if the current form field should be displayed,
	 *                 FALSE otherwise
	 */
	public function isFormFieldEnabled($key) {
		// Some containers cannot be enabled or disabled via TS setup, but
		// are containers and depend on their content being displayed.
		switch ($key) {
			case 'payment':
				$result = $this->isFormFieldEnabled('price')
					|| $this->isFormFieldEnabled('method_of_payment')
					|| $this->isFormFieldEnabled('banking_data');
				break;
			case 'banking_data':
				$result = $this->isFormFieldEnabled('account_number')
					|| $this->isFormFieldEnabled('account_owner')
					|| $this->isFormFieldEnabled('bank_code')
					|| $this->isFormFieldEnabled('bank_name');
				break;
			case 'billing_address':
				// This fields actually can also be disabled via TS setup.
				$result = isset($this->formFieldsToShow[$key])
					&& (
						$this->isFormFieldEnabled('company')
						|| $this->isFormFieldEnabled('gender')
						|| $this->isFormFieldEnabled('name')
						|| $this->isFormFieldEnabled('address')
						|| $this->isFormFieldEnabled('zip')
						|| $this->isFormFieldEnabled('city')
						|| $this->isFormFieldEnabled('country')
						|| $this->isFormFieldEnabled('telephone')
						|| $this->isFormFieldEnabled('email')
					);
				break;
			case 'more_seats':
				$result = $this->isFormFieldEnabled('seats')
					|| $this->isFormFieldEnabled('attendees_names')
					|| $this->isFormFieldEnabled('kids');
				break;
			case 'lodging_and_food':
				$result = $this->isFormFieldEnabled('lodgings')
					|| $this->isFormFieldEnabled('accommodation')
					|| $this->isFormFieldEnabled('foods')
					|| $this->isFormFieldEnabled('food');
				break;
			case 'additional_information':
				$result = $this->isFormFieldEnabled('checkboxes')
					|| $this->isFormFieldEnabled('interests')
					|| $this->isFormFieldEnabled('expectations')
					|| $this->isFormFieldEnabled('background_knowledge')
					|| $this->isFormFieldEnabled('known_from')
					|| $this->isFormFieldEnabled('notes');
				break;
			case 'entered_data':
				$result = $this->isFormFieldEnabled('feuser_data')
					|| $this->isFormFieldEnabled('billing_address')
					|| $this->isFormFieldEnabled('registration_data');
				break;
			case 'all_terms':
				$result = $this->isFormFieldEnabled('terms')
					|| $this->isFormFieldEnabled('terms_2');
				break;
			case 'traveling_terms':
				// "traveling_terms" is an alias for "terms_2" which we use to
				// avoid the problem that subpart names need to be prefix-free.
				$result = $this->isFormFieldEnabled('terms_2');
				break;
			case 'billing_data':
				// "billing_data" is an alias for "billing_address" which we use
				// to prevent two subparts from having the same name.
				$result = $this->isFormFieldEnabled('billing_address');
				break;
			default:
				$result = isset($this->formFieldsToShow[$key]);
				break;
		}

		// Some fields depend on the availability of their data.
		switch ($key) {
			case 'method_of_payment':
				$result = $result && $this->showMethodsOfPayment();
				break;
			case 'account_number':
				// The fallthrough is intended.
			case 'bank_code':
				// The fallthrough is intended.
			case 'bank_name':
				// The fallthrough is intended.
			case 'account_owner':
				$result = $result && $this->getSeminar()->hasAnyPrice();
				break;
			case 'lodgings':
				$result = $result && $this->hasLodgings();
				break;
			case 'foods':
				$result = $result && $this->hasFoods();
				break;
			case 'checkboxes':
				$result = $result && $this->hasCheckboxes();
				break;
			case 'terms_2':
				$result = $result && $this->isTerms2Enabled();
				break;
			default:
				break;
		}

		return $result;
	}

	/**
	 * Checks whether a form field should be displayed (and evaluated) at all.
	 * This is specified via TS setup (or flexforms) using the
	 * "showRegistrationFields" variable.
	 *
	 * This function also checks if the current event has a price set at all,
	 * and returns only TRUE if the event has a price (ie. is not completely for
	 * free) and the current form field should be displayed.
	 *
	 * @param array the contents of the "params" child of the userobj
	 *              node as key/value pairs (used for retrieving the
	 *              current form field name)
	 *
	 * @return boolean TRUE if the current form field should be displayed
	 *                 AND the current event is not completely for free,
	 *                 FALSE otherwise
	 */
	public function hasBankDataFormField(array $parameters) {
		return $this->hasRegistrationFormField($parameters)
			&& $this->getSeminar()->hasAnyPrice();
	}

	/**
	 * Gets the URL of the page that should be displayed after a user has
	 * signed up for an event, but only if the form has been submitted from
	 * stage 2 (the confirmation page).
	 *
	 * If the current FE user account is a one-time account and
	 * checkLogOutOneTimeAccountsAfterRegistration is enabled in the TS setup,
	 * the FE user will be automatically logged out.
	 *
	 * @return string complete URL of the FE page with a message (or null
	 *                if the confirmation page has not been submitted yet)
	 */
	public function getThankYouAfterRegistrationUrl() {
		$sendParameters = FALSE;
		$pageId = $this->getConfValueInteger(
			'thankYouAfterRegistrationPID', 's_registration'
		);

		if ($this->getConfValueBoolean('logOutOneTimeAccountsAfterRegistration')
				&& tx_oelib_Session::getInstance(tx_oelib_Session::TYPE_USER)
					->getAsBoolean('onetimeaccount')
		) {
			$GLOBALS['TSFE']->fe_user->logoff();
			$GLOBALS['TSFE']->loginUser = 0;
		}

		if ($this->getConfValueBoolean(
			'sendParametersToThankYouAfterRegistrationPageUrl',
			's_registration'
		)) {
			$sendParameters = TRUE;
		}

		return $this->createUrlForRedirection($pageId, $sendParameters);
	}

	/**
	 * Gets the URL of the page that should be displayed after a user has
	 * unregistered from an event.
	 *
	 * @return string complete URL of the FE page with a message (or null
	 *                if the confirmation page has not been submitted yet)
	 */
	public function getPageToShowAfterUnregistrationUrl() {
		$sendParameters = FALSE;
		$pageId = $this->getConfValueInteger(
			'pageToShowAfterUnregistrationPID', 's_registration'
		);

		if (
			$this->getConfValueBoolean(
				'sendParametersToPageToShowAfterUnregistrationUrl',
				's_registration'
			)
		) {
			$sendParameters = TRUE;
		}

		return $this->createUrlForRedirection($pageId, $sendParameters);
	}

	/**
	 * Creates a URL for redirection. This is a utility function for
	 * getThankYouAfterRegistrationUrl() and getPageToShowAfterUnregistration().
	 *
	 * @param string the page UID
	 * @param boolean TRUE if GET parameters should be added to the URL,
	 *                otherwise FALSE
	 *
	 * @return string complete URL of the FE page with a message
	 */
	private function createUrlForRedirection($pageId, $sendParameters = TRUE) {
		// On freshly updated sites, the configuration value might not be set
		// yet. To avoid breaking the site, we use the event list in this case.
		if (!$pageId) {
			$pageId = $this->getConfValueInteger('listPID', 'sDEF');
		}

		$linkConfiguration = array('parameter' => $pageId);

		if ($sendParameters) {
			$linkConfiguration['additionalParams']
				= t3lib_div::implodeArrayForUrl(
					'tx_seminars_pi1',
					array('showUid' => $this->getSeminar()->getUid()),
					'',
					FALSE,
					TRUE
				);
		}

		// XXX We need to do this workaround of manually encoding brackets in
		// the URL due to a bug in the TYPO3 core:
		// http://bugs.typo3.org/view.php?id=3808
		$result = preg_replace(
			array('/\[/', '/\]/'),
			array('%5B', '%5D'),
			$this->cObj->typoLink_URL($linkConfiguration)
		);

		return t3lib_div::locationHeaderUrl($result);
	}

	/**
	 * Provides data items for the list of available payment methods.
	 *
	 * @param array array that contains any pre-filled data (may be
	 *              empty, but not null, unused)
	 *
	 * @return array items from the payment methods table as an array
	 *               with the keys "caption" (for the title) and "value"
	 *               (for the uid)
	 */
	public function populateListPaymentMethods(array $items) {
		if (!$this->getSeminar()->hasPaymentMethods()) {
			return array();
		}

		$rows = tx_oelib_db::selectMultiple(
			'uid, title',
			'tx_seminars_payment_methods, tx_seminars_seminars_payment_methods_mm',
			'tx_seminars_payment_methods.uid = ' .
				'tx_seminars_seminars_payment_methods_mm.uid_foreign ' .
				'AND tx_seminars_seminars_payment_methods_mm.uid_local=' .
				$this->getSeminar()->getTopicUid() .
				tx_oelib_db::enableFields('tx_seminars_payment_methods')
		);

		$result = array();
		foreach ($rows as $row) {
			$result[] = array(
				'caption' => $row['title'],
				'value' => $row['uid'],
			);
		}

		return $result;
	}

	/**
	 * Checks whether the methods of payment should be displayed at all,
	 * ie. whether they are enable in the setup and the current event actually
	 * has any payment methods assigned and has at least one price.
	 *
	 * @return boolean TRUE if the payment methods should be displayed,
	 *                 FALSE otherwise
	 */
	public function showMethodsOfPayment() {
		return $this->getSeminar()->hasPaymentMethods()
			&& $this->getSeminar()->hasAnyPrice()
			&& $this->hasRegistrationFormField(
				array('elementname' => 'method_of_payment')
			);
	}

	/**
	 * Gets the currently logged-in FE user's data nicely formatted as HTML so
	 * that it can be directly included on the confirmation page.
	 *
	 * The telephone number and the e-mail address will have labels in front of
	 * them.
	 *
	 * @return string the currently logged-in FE user's data
	 */
	public function getAllFeUserData() {
		$userData = $GLOBALS['TSFE']->fe_user->user;

		foreach (array(
			'name' => FALSE,
			'company' => FALSE,
			'address' => FALSE,
			'zip' => FALSE,
			'city' => FALSE,
			'country' => FALSE,
			'telephone' => TRUE,
			'email' => TRUE
		) as $currentKey => $hasLabel) {
			$value = htmlspecialchars($userData[$currentKey]);
			// Only show a label if we have any data following it.
			if ($hasLabel && !empty($value)) {
				$value = $this->translate('label_' . $currentKey) .
					' ' . $value;
			}
			$this->setMarker('user_' . $currentKey, $value);
		}

		$rawOutput = $this->getSubpart('REGISTRATION_CONFIRMATION_FEUSER');

		// drops empty lines
		return preg_replace('/[\n\r]\s*<br \/>/', '', $rawOutput);
	}

	/**
	 * Gets the already entered registration data nicely formatted as HTML so
	 * that it can be directly included on the confirmation page.
	 *
	 * @return string the entered registration data, nicely formatted as HTML
	 */
	public function getRegistrationData() {
		$result = '';

		foreach (array(
			'price',
			'seats',
			'total_price',
			'method_of_payment',
			'account_number',
			'bank_code',
			'bank_name',
			'account_owner',
			'attendees_names',
			'lodgings',
			'accommodation',
			'foods',
			'food',
			'checkboxes',
			'interests',
			'expectations',
			'background_knowledge',
			'known_from',
			'notes'
		) as $currentKey) {
			if ($this->isFormFieldEnabled($currentKey)) {
				$result .= $this->getFormDataItemForConfirmation(
					$currentKey
				);
			}
		}

		return $result;
	}

	/**
	 * Formats one data item from the form as HTML, including a heading.
	 * If the entered data is empty, an empty string will be returned (so the
	 * heading will only be included for non-empty data).
	 *
	 * @param string $key
	 *        the key of the field for which the data should be displayed
	 *
	 * @return string the data from the corresponding form field formatted in
	 *                HTML with a heading (or an empty string if the form data
	 *                is empty)
	 */
	private function getFormDataItemForConfirmation($key) {
		$result = '';

		// The "total_price" field doesn't exist as an actual renderlet and
		// so cannot be read.
		$currentFormData = ($key != 'total_price')
			? $this->getFormValue($key) : '';

		switch ($key) {
			case 'price':
				$currentFormData = $this->getSelectedPrice();
				break;
			case 'total_price':
				$currentFormData = $this->getTotalPriceWithUnit();
				break;
			case 'method_of_payment':
				$currentFormData = $this->getSelectedPaymentMethod();
				break;
			case 'lodgings':
				$this->ensureArray($currentFormData);
				$currentFormData = $this->getCaptionsForSelectedOptions(
					$this->getSeminar()->getLodgings(),
					$currentFormData
				);
				break;
			case 'foods':
				$this->ensureArray($currentFormData);
				$currentFormData = $this->getCaptionsForSelectedOptions(
					$this->getSeminar()->getFoods(),
					$currentFormData
				);
				break;
			case 'checkboxes':
				$this->ensureArray($currentFormData);
				$currentFormData = $this->getCaptionsForSelectedOptions(
					$this->getSeminar()->getCheckboxes(),
					$currentFormData
				);
				break;
			case 'attendees_names':
				if ($this->isFormFieldEnabled('registered_themselves')
					&& ($this->getFormValue('registered_themselves') == '1')
				) {
					$name = tx_oelib_FrontEndLoginManager::getInstance()
						->getLoggedInUser('tx_seminars_Mapper_FrontEndUser')
						->getName();
					$currentFormData = $name . CR . $currentFormData;
				}
				break;
			default:
				break;
		}

		if (!is_string($currentFormData)) {
			$currentFormData = (string) $currentFormData;
		}

		if ($currentFormData != '') {
			$this->setMarker(
				'registration_data_heading', $this->translate('label_' . $key)
			);
			$fieldContent = str_replace(
				CR, '<br />', htmlspecialchars($currentFormData)
			);
			$this->setMarker('registration_data_body', $fieldContent);
			$result = $this->getSubpart('REGISTRATION_CONFIRMATION_DATA');
		}

		return $result;
	}

	/**
	 * Ensures that the parameter is an array. If it is no array yet, it will
	 * be changed to an empty array.
	 *
	 * @param mixed variable that should be ensured to be an array
	 *              (passed by reference)
	 */
	private function ensureArray(&$data) {
		if (!is_array($data)) {
			$data = array();
		}
	}

	/**
	 * Retrieves the selected price, completely with caption (for example:
	 * "Standard price") and currency.
	 *
	 * If no price has been selected, the first available price will be used.
	 *
	 * @return string the selected price with caption and unit
	 */
	private function getSelectedPrice() {
		$availablePrices = $this->getSeminar()->getAvailablePrices();

		return $availablePrices[$this->getKeyOfSelectedPrice()]['caption'];
	}

	/**
	 * Retrieves the key of the selected price.
	 *
	 * If no price has been selected, the first available price will be used.
	 *
	 * @return string the key of the selected price, will always be a valid key
	 */
	private function getKeyOfSelectedPrice() {
		$availablePrices = $this->getSeminar()->getAvailablePrices();
		$selectedPrice = $this->getFormValue('price');

		// If no (available) price is selected, use the first price by default.
		if (!$this->getSeminar()->isPriceAvailable($selectedPrice)) {
			$selectedPrice = key($availablePrices);
		}

		return $selectedPrice;
	}

	/**
	 * Takes the selected price and the selected number of seats and calculates
	 * the total price. The total price will be returned with the currency
	 * unit appended.
	 *
	 * @return string the total price calculated from the form data including
	 *                the currency unit, eg. "240.00 EUR"
	 */
	private function getTotalPriceWithUnit() {
		$result = '';

		$seats = intval($this->getFormValue('seats'));

		// Only show the total price if the seats selector is displayed
		// (otherwise the total price will be same as the price anyway).
		if ($seats > 0) {
			// Build the total price for this registration and add it to the form
			// data to show it on the confirmation page.
			// This value will not be saved to the database from here. It will be
			// calculated again when creating the registration object.
			// It will not be added if no total price can be calculated (e.g.
			// total price = 0.00)
			$availablePrices = $this->getSeminar()->getAvailablePrices();
			$selectedPrice = $this->getKeyOfSelectedPrice();

			if ($availablePrices[$selectedPrice]['amount'] != '0.00') {
				$result = $this->getSeminar()->formatPrice(
					$seats * $availablePrices[$selectedPrice]['amount']
				);
			}
		}

		return $result;
	}

	/**
	 * Gets the caption of the selected payment method. If no valid payment
	 * method has been selected, this function returns an empty string.
	 *
	 * @return string the caption of the selected payment method or an empty
	 *                string if no valid payment method has been selected
	 */
	private function getSelectedPaymentMethod() {
		$result = '';
		$availablePaymentMethods = $this->populateListPaymentMethods(array());

		foreach ($availablePaymentMethods as $paymentMethod) {
			if ($paymentMethod['value'] ==
				$this->getFormValue('method_of_payment')
			) {
				$result = $paymentMethod['caption'];
				break;
			}
		}

		// We use strip_tags to remove any trailing <br /> tags.
		return strip_tags($result);
	}

	/**
	 * Takes the selected options for a list of options and displays it
	 * nicely using their captions, separated by a carriage return (ASCII 13).
	 *
	 * @param array all available options for this form element as a nested
	 *              array, the outer array having the UIDs of the options as
	 *              keys, the inner array having the keys "caption" (for the
	 *              visible captions) and "value" (the UID again), may be empty,
	 *              must not be null
	 * @param array the selected options with the array values being the UIDs of
	 *              the corresponding options, may be empty or even null
	 *
	 * @return string the captions of the selected options, separated by CR
	 */
	private function getCaptionsForSelectedOptions(
		array $availableOptions, array $selectedOptions
	) {
		$result = '';

		if (!empty($selectedOptions)) {
			$captions = array();

			foreach ($selectedOptions as $currentSelection) {
				if (isset($availableOptions[$currentSelection])) {
					$captions[] = $availableOptions[$currentSelection]['caption'];
				}
				$result = implode(CR, $captions);
			}
		}

		return $result;
	}

	/**
	 * Gets the already entered billing address nicely formatted as HTML so
	 * that it can be directly included on the confirmation page.
	 *
	 * @return string the already entered registration data, nicely formatted as
	 *                HTML
	 */
	public function getBillingAddress() {
		$result = '';

		foreach ($this->fieldsInBillingAddress as $currentKey => $hasLabel) {
			$currentFormData = $this->getFormValue($currentKey);
			if ($currentFormData != '') {
				// If the gender field is hidden, it would have an empty value,
				// so we wouldn't be here. So let's convert the "gender" index
				// into a readable string.
				if ($currentKey == 'gender') {
					$currentFormData = $this->translate(
						'label_gender.I.' . intval($currentFormData)
					);
				}
				$processedFormData = str_replace(
					CR, '<br />', htmlspecialchars($currentFormData)
				);
				if ($hasLabel) {
					$processedFormData
						= $this->translate('label_' . $currentKey) .
							' ' . $processedFormData;
				}

				$result .= $processedFormData.'<br />';
			}
		}

		$this->setMarker('registration_billing_address', $result);

		return $this->getSubpart('REGISTRATION_CONFIRMATION_BILLING');
	}

	/**
	 * Checks whether the current field is non-empty if the payment method
	 * "bank transfer" is selected. If a different payment method is selected
	 * (or none is defined as "bank transfer"), the check is always positive and
	 * returns TRUE.
	 *
	 * @param array associative array with the element "value" in which
	 *              the value of the current field is provided
	 *
	 * @return boolean TRUE if the field is non-empty or "bank transfer" is not
	 *                 selected
	 */
	public function hasBankData(array $formData) {
		$result = TRUE;

		if (empty($formData['value'])) {
			$bankTransferUid = $this->getConfValueInteger('bankTransferUID');

			$paymentMethod = intval($this->getFormValue('method_of_payment'));

			if (($bankTransferUid > 0) && ($paymentMethod == $bankTransferUid)) {
				$result = FALSE;
			}
		}

		return $result;
	}

	/**
	 * Returns a data item of the currently logged-in FE user or, if that data
	 * has additionally been stored in the FE user session (as billing address),
	 * the data from the session.
	 *
	 * This function may only be called when a FE user is logged in.
	 *
	 * The caller needs to take care of htmlspecialcharing the data.
	 *
	 * @param mixed (unused)
	 * @param array contents of the "params" XML child of the userobj node
	 *              (needs to contain an element with the key "key")
	 *
	 * @return string the contents of the element
	 */
	public function getFeUserData($unused, array $params) {
		$result = $this->retrieveDataFromSession(null, $params);

		if (empty($result)) {
			$key = $params['key'];
			$feUserData = $GLOBALS['TSFE']->fe_user->user;
			$result = $feUserData[$key];

			// If the country is empty, try the static info country instead.
			if (empty($result) && ($key == 'country')) {
				$static_info_country = $feUserData['static_info_country'];
				if (!empty($static_info_country)) {
					$this->initStaticInfo();
					$result = $this->staticInfo->getStaticInfoName(
						'COUNTRIES',
						$static_info_country,
						'',
						'',
						TRUE
					);
				} else {
					$result = $this->getDefaultCountry();
				}
			}
		}

		return $result;
	}

	/**
	 * Provides a localized list of country names from static_tables.
	 *
	 * @return array a list of localized country names from static_tables as an
	 *               array with the keys "caption" (for the title) and "value"
	 *               (in this case, the same as the caption)
	 */
	public function populateListCountries() {
		$this->initStaticInfo();
		$allCountries = $this->staticInfo->initCountries(
			'ALL', $this->staticInfo->getCurrentLanguage(), TRUE
		);

		$result = array();
		// Puts an empty item at the top so we won't have Afghanistan (the first entry) pre-selected for empty values.
		$result[] = array('caption' => '', 'value' => '');

		foreach ($allCountries as $currentCountryName) {
			$result[] = array(
				'caption' => $currentCountryName,
				'value' => $currentCountryName,
			);
		}

		return $result;
	}

	/**
	 * Returns the default country as localized string.
	 *
	 * @return string the default country's localized name, will be empty if
	 *                there is no default country
	 */
	private function getDefaultCountry() {
		$this->initStaticInfo();
		$defaultCountryCode = tx_oelib_ConfigurationRegistry::
			get('plugin.tx_staticinfotables_pi1')->getAsString('countryCode');
		if ($defaultCountryCode == '') {
			return '';
		}

		return tx_staticinfotables_div::getTitleFromIsoCode(
			'static_countries', $defaultCountryCode,
			$this->staticInfo->getCurrentLanguage(), TRUE
		);
	}

	/**
	 * Provides data items for the list of option checkboxes for this event.
	 *
	 * @return array items from the checkboxes table as an array with the keys
	 *               "caption" (for the title) and "value" (for the uid)
	 */
	public function populateCheckboxes() {
		$result = array();

		if ($this->getSeminar()->hasCheckboxes()) {
			$result = $this->getSeminar()->getCheckboxes();
		}

		return $result;
	}

	/**
	 * Checks whether our current event has any option checkboxes AND the
	 * checkboxes should be displayed at all.
	 *
	 * @return boolean TRUE if we have a non-empty list of checkboxes AND this
	 *                 list should be displayed, FALSE otherwise
	 */
	public function hasCheckboxes() {
		return $this->getSeminar()->hasCheckboxes()
			&& $this->hasRegistrationFormField(
				array('elementname' => 'checkboxes')
			);
	}

	/**
	 * Provides data items for the list of lodging options for this event.
	 *
	 * @return array items from the lodgings table as an array with the keys
	 *               "caption" (for the title) and "value" (for the uid)
	 */
	public function populateLodgings() {
		$result = array();

		if ($this->getSeminar()->hasLodgings()) {
			$result = $this->getSeminar()->getLodgings();
		}

		return $result;
	}

	/**
	 * Checks whether at least one lodging option is selected (if there is at
	 * least one lodging option for this event and the lodging options should
	 * be displayed).
	 *
	 * @param array the value of the current field in an associative
	 *              array witch the element "value"
	 *
	 * @return boolean TRUE if at least one item is selected or no lodging
	 *                 options can be selected
	 */
	public function isLodgingSelected(array $formData) {
		return !empty($formData['value']) || !$this->hasLodgings();
	}

	/**
	 * Checks whether our current event has any lodging options and the
	 * lodging options should be displayed at all.
	 *
	 * @return boolean TRUE if we have a non-empty list of lodging options and
	 *                 this list should be displayed, FALSE otherwise
	 */
	public function hasLodgings() {
		return $this->getSeminar()->hasLodgings()
			&& $this->hasRegistrationFormField(
				array('elementname' => 'lodgings')
			);
	}

	/**
	 * Provides data items for the list of food options for this event.
	 *
	 * @return array items from the foods table as an array with the keys
	 *               "caption" (for the title) and "value" (for the uid)
	 */
	public function populateFoods() {
		$result = array();

		if ($this->getSeminar()->hasFoods()) {
			$result = $this->getSeminar()->getFoods();
		}

		return $result;
	}

	/**
	 * Checks whether our current event has any food options and the food
	 * options should be displayed at all.
	 *
	 * @return boolean TRUE if we have a non-empty list of food options and this
	 *                 list should be displayed, FALSE otherwise
	 */
	public function hasFoods() {
		return $this->getSeminar()->hasFoods()
			&& $this->hasRegistrationFormField(array('elementname' => 'foods'));
	}

	/**
	 * Checks whether at least one food option is selected (if there is at
	 * least one food option for this event and the food options should
	 * be displayed).
	 *
	 * @param array associative array with the element "value" in which
	 *              the value of the current field is provided
	 *
	 * @return boolean TRUE if at least one item is selected or no food
	 *                 options can be selected
	 */
	public function isFoodSelected(array $formData) {
		return !empty($formData['value']) || !$this->hasFoods();
	}

	/**
	 * Provides data items for the prices for this event.
	 *
	 * @return array available prices as an array with the keys "caption" (for
	 *               the title) and "value" (for the uid)
	 */
	public function populatePrices() {
		return $this->getSeminar()->getAvailablePrices();
	}

	/**
	 * Checks whether a valid price is selected or the "price" registration
	 * field is not visible in the registration form (in which case it is not
	 * possible to select a price).
	 *
	 * @param array associative array with the element "value" in which
	 *              the currently selected value (a positive integer) or
	 *              null if no radiobutton is selected is provided
	 *
	 * @return boolean TRUE if a valid price is selected or the price field
	 *                 is hidden, FALSE if none is selected, but could have
	 *                 been selected
	 */
	public function isValidPriceSelected(array $formData) {
		return $this->getSeminar()->isPriceAvailable($formData['value'])
			|| !$this->hasRegistrationFormField(
				array('elementname' => 'price')
			);
	}

	/**
	 * Returns the UID of the preselected payment method.
	 *
	 * This will be:
	 * a) the same payment method as previously selected (within the current
	 * session) if that method is available for the current event
	 * b) if only one payment method is available, that payment method
	 * c) 0 in all other cases
	 *
	 * @return integer the UID of the preselected payment method or 0 if should
	 *                 will be preselected
	 */
	public function getPreselectedPaymentMethod() {
		$availablePaymentMethods = $this->populateListPaymentMethods(array());
		if (count($availablePaymentMethods) == 1) {
			return $availablePaymentMethods[0]['value'];
		}

		$result = 0;
		$paymentMethodFromSession = $this->retrieveSavedMethodOfPayment();

		foreach ($availablePaymentMethods as $paymentMethod) {
			if ($paymentMethod['value'] == $paymentMethodFromSession) {
				$result = $paymentMethod['value'];
				break;
			}
		}

		return $result;
	}

	/**
	 * Saves the following data to the FE user session:
	 * - payment method
	 * - account number
	 * - bank code
	 * - bank name
	 * - account_owner
	 * - gender
	 * - name
	 * - address
	 * - zip
	 * - city
	 * - country
	 * - telephone
	 * - email
	 *
	 * @param array the form data (may be empty)
	 */
	private function saveDataToSession(array $parameters) {
		if (!empty($parameters)) {
			$parametersToSave = array(
				'method_of_payment',
				'account_number',
				'bank_code',
				'bank_name',
				'account_owner',
				'company',
				'gender',
				'name',
				'address',
				'zip',
				'city',
				'country',
				'telephone',
				'email',
				'registered_themselves',
			);

			foreach ($parametersToSave as $currentKey) {
				if (isset($parameters[$currentKey])) {
					tx_oelib_Session::getInstance(tx_oelib_Session::TYPE_USER)
						->setAsString(
							$this->prefixId . '_' . $currentKey,
							$parameters[$currentKey]
						);
				}
			}
		}
	}

	/**
	 * Retrieves the saved payment method from the FE user session.
	 *
	 * @return integer the UID of the payment method that has been saved in the
	 *                 FE user session or 0 if there is none
	 */
	private function retrieveSavedMethodOfPayment() {
		return intval(
			$this->retrieveDataFromSession(
				null,
				array('key' => 'method_of_payment')
			)
		);
	}

	/**
	 * Retrieves the data for a given key from the FE user session. Returns an
	 * empty string if no data for that key is stored.
	 *
	 * @param mixed (unused)
	 * @param array the contents of the "params" child of the userobj
	 *              node as key/value pairs (used for retrieving the
	 *              current form field name)
	 *
	 * @return string the data stored in the FE user session under the
	 *                given key, might be empty
	 */
	public function retrieveDataFromSession($unused, array $parameters) {
		return tx_oelib_Session::getInstance(tx_oelib_Session::TYPE_USER)
			->getAsString($this->prefixId . '_' . $parameters['key']);
	}

	/**
	 * Gets the prefill value for the account owner: If it is provided, the
	 * account owner from a previous registration in the same FE user session,
	 * or the FE user's name.
	 *
	 * @return string a name to prefill the account owner
	 */
	public function prefillAccountOwner() {
		$result = $this->retrieveDataFromSession(
			null,
			array('key' => 'account_owner')
		);

		if (empty($result)) {
			$result = $this->getFeUserData(
				null,
				array('key' => 'name')
			);
		}

		return $result;
	}

	/**
	 * Creates and initializes $this->staticInfo (if that hasn't been done yet).
	 */
	private function initStaticInfo() {
		if (!$this->staticInfo) {
			$this->staticInfo
				= t3lib_div::makeInstance('tx_staticinfotables_pi1');
			$this->staticInfo->init();
		}
	}

	/**
	 * Hides form fields that are either disabled via TS setup or that have
	 * nothing to select (e.g. if there are no payment methods) from the
	 * templating process.
	 */
	private function hideUnusedFormFields() {
		static $availableFormFields = array(
			'step_counter',
			'payment',
			'price',
			'method_of_payment',
			'banking_data',
			'account_number',
			'bank_code',
			'bank_name',
			'account_owner',
			'billing_address',
			'billing_data',
			'company',
			'gender',
			'name',
			'address',
			'zip',
			'city',
			'country',
			'telephone',
			'email',
			'additional_information',
			'interests',
			'expectations',
			'background_knowledge',
			'lodging_and_food',
			'accommodation',
			'food',
			'known_from',
			'more_seats',
			'seats',
			'registered_themselves',
			'attendees_names',
			'kids',
			'lodgings',
			'foods',
			'checkboxes',
			'notes',
			'entered_data',
			'feuser_data',
			'registration_data',
			'all_terms',
			'terms',
			'terms_2',
			'traveling_terms'
		);

		$formFieldsToHide = array();

		foreach ($availableFormFields as $key) {
			if (!$this->isFormFieldEnabled($key)) {
				$formFieldsToHide[$key] = $key;
			}
		}

		$numberOfClicks = $this->getConfValueInteger(
			'numberOfClicksForRegistration', 's_registration'
		);

		// If we first visit the registration form, the value of
		// $this->currentPageNumber is 0.
		// If we had an error in our form input and we were send back to the
		// registration form, $this->currentPageNumber is 2.
		if (($this->currentPageNumber == 0) || ($this->currentPageNumber == 2)) {
			switch ($numberOfClicks) {
				case 2:
					$formFieldsToHide['button_continue'] = 'button_continue';
					break;
				case 3:
					// The fall-through is intended.
				default:
					$formFieldsToHide['button_submit'] = 'button_submit';
					break;
			}
		}

		$this->hideSubparts(
			implode(',', $formFieldsToHide),
			'registration_wrapper'
		);
	}

	/**
	 * Provides a string "Registration form: step x of y" for the current page.
	 * The number of the first and last page can be configured via TS setup.
	 *
	 * @return string a localized string displaying the number of the current
	 *                and the last page
	 */
	public function getStepCounter() {
		$lastPageNumberForDisplay = $this->getConfValueInteger(
			'numberOfLastRegistrationPage'
		);

		$currentPageNumber = $this->getConfValueInteger(
			'numberOfFirstRegistrationPage'
		) + $this->currentPageNumber;

		// Decreases $lastPageNumberForDisplay by one if we only have 2 clicks
		// to registration.
		$numberOfClicks = $this->getConfValueInteger(
			'numberOfClicksForRegistration',
			's_registration'
		);

		if ($numberOfClicks == 2) {
			$lastPageNumberForDisplay--;
		}

		$currentPageNumberForDisplay
			= min($lastPageNumberForDisplay, $currentPageNumber);

		return sprintf(
			$this->translate('label_step_counter'),
			$currentPageNumberForDisplay, $lastPageNumberForDisplay
		);
	}

	/**
	 * Processes the registration that should be removed.
	 */
	public function processUnregistration() {
		if (
			$this->getFormCreator()->aORenderlets['button_cancel']
				->hasThrown('click')
		) {
			$redirectUrl = t3lib_div::locationHeaderUrl(
				$this->pi_getPageLink(
					$this->getConfValueInteger('myEventsPID')
				)
			);
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()
				->addHeader('Location:' . $redirectUrl);
			exit;
		}

		$this->getRegistrationManager()->removeRegistration(
			$this->getRegistration()->getUid(), $this
		);
	}

	/**
	 * Gets the number of entered persons in the form by counting the lines
	 * in the "additional attendees names" field and the state of the
	 * "register myself" checkbox.
	 *
	 * @return integer the number of entered persons, will be >= 0
	 */
	public function getNumberOfEnteredPersons() {
		if ($this->isFormFieldEnabled('registered_themselves')) {
			$formData = intval($this->getFormValue('registered_themselves'));
			$themselves = ($formData > 0) ? 1 : 0;
		} else {
			$themselves = 1;
		}
		$names = t3lib_div::trimExplode(
			LF, (string) $this->getFormValue('attendees_names'), TRUE
		);
		$namesCounter = count($names);

		return $themselves + $namesCounter;
	}

	/**
	 * Checks whether the number of selected seats matches the number of
	 * registered persons (from the "attendees names" text area and the
	 * "myself" checkbox).
	 *
	 * @return boolean TRUE if the number of seats matches the number of
	 *                 registered persons, FALSE otherwise
	 */
	public function numberOfSeatsMatchesRegisteredPersons() {
		if (intval($this->getFormValue('seats')) <= 0) {
			return FALSE;
		}
		if (!$this->isFormFieldEnabled('attendees_names')) {
			return TRUE;
		}

		return (intval($this->getFormValue('seats'))
			== $this->getNumberOfEnteredPersons());
	}

	/**
	 * Gets the error message to return if the number of registered persons
	 * does not match the number of seats.
	 *
	 * @return string the localized error message, will be empty if both numbers
	 *                match
	 */
	public function getMessageForSeatsNotMatchingRegisteredPersons() {
		$seats = intval($this->getFormValue('seats'));
		$persons = $this->getNumberOfEnteredPersons();

		if ($persons < $seats) {
			$result = $this->translate('message_lessAttendeesThanSeats');
		} elseif ($persons > $seats) {
			$result = $this->translate('message_moreAttendeesThanSeats');;
		} else {
			$result = '';
		}

		return $result;
	}

	/**
	 * Returns the Singleton registration manager instance.
	 *
	 * @return tx_seminars_registrationmanager the singleton instance
	 */
	private function getRegistrationManager() {
		return tx_seminars_registrationmanager::getInstance();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_registrationEditor.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/pi1/class.tx_seminars_pi1_registrationEditor.php']);
}
?>