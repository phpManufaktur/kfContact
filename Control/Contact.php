<?php

/**
 * Contact
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/FacebookGallery
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Contact\Control;

use Silex\Application;
use phpManufaktur\Contact\Data\Contact\Contact as ContactData;
use Symfony\Component\Validator\Constraints as Assert;
use phpManufaktur\Contact\Data\Contact\Title;
use phpManufaktur\Contact\Data\Contact\Country;
use phpManufaktur\Contact\Data\Contact\Overview;

class Contact extends ContactParent
{

    protected static $contact_id = -1;

    protected $ContactData = null;
    protected $ContactPerson = null;
    protected $ContactCompany = null;
    protected $ContactCommunication = null;
    protected $ContactAddress = null;
    protected $ContactNote = null;
    protected $Overview = null;

    protected static $ContactBlocks = array(
        'contact' => array(
            'login' => array(
                'use_email_address' => true
            ),
            'name' => array(
                'use_login' => true
            )
        ),
        'person',
        'company',
        'communication' => array(
                'usage' => array(
                    'default' => 'PRIVATE'
                ),
                'value' => array(
                    'ignore_if_empty' => true
                )
            ),
        'address',
        'note'
    );

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->ContactAddress = new ContactAddress($this->app);
        $this->ContactCommunication = new ContactCommunication($this->app);
        $this->ContactCompany = new ContactCompany($this->app);
        $this->ContactData = new ContactData($this->app);
        $this->ContactNote = new ContactNote($this->app);
        $this->ContactPerson = new ContactPerson($this->app);
        $this->Overview = new Overview($this->app);
    }

    /**
     * Get the ContactBlocks used by the validate() functions
     *
     * @return array
     */
    public function getContactBlocks()
    {
        return self::$ContactBlocks;
    }

    /**
     * Replace the default ContentBlocks with the given $blocks
     *
     * @param array $blocks
     * @throws \Exception
     */
    public function setContactBlocks($blocks)
    {
        if (!is_array($blocks)) {
            throw new \Exception("ContactBlocks must be submitted as array!");
        }
        self::$ContactBlocks = array();
        foreach ($blocks as $block) {
            self::$ContactBlocks[] = strtolower($block);
        }
    }

    public function getTitleArrayForTwig()
    {
        $title = new Title($this->app);
        return $title->getArrayForTwig();
    }

    public function getCountryArrayForTwig()
    {
        $country = new Country($this->app);
        return $country->getArrayForTwig();
    }

    /**
     * Get the contact record for this contact_id
     */
    public function getDefaultRecord()
    {
        $data = array(
            'contact' => array(
                'contact_id' => -1,
                'contact_name' => '',
                'contact_login' => '',
                'contact_type' => 'PERSON',
                'contact_status' => 'ACTIVE',
                'contact_timestamp' => '0000-00-00 00:00:00',
            )
        );

        if ($data['contact']['contact_type'] === 'PERSON') {
            $ContactPerson = new ContactPerson($this->app);
            $data['person'] = array($ContactPerson->getDefaultRecord());
        }
        else {
            throw ContactException::contactTypeNotSupported(self::$contact_type);
        }

        // default communication entry
        $data['communication'] = array(
            $this->ContactCommunication->getDefaultRecord()
        );

        // default address entry
        $data['address'] = array(
            $this->ContactAddress->getDefaultRecord()
        );

        // default note entry
        $data['note'] = array(
            $this->ContactNote->getDefaultRecord()
        );

        return $data;
    }

    /**
     * Level down the regular multilevel contact array to only one level. The keys
     * of the resulting array contain all levels, i.e. $contact['address'][0]['address_id']
     * will become $contact['address_0_address_id']
     *
     * @param array $contact
     * @param boolean $use_communication_type use communication type instead of level (default)
     * @return array
     */
    public function levelDownContactArray($contact, $use_communication_type=true)
    {
        $flatten = array();
        foreach ($contact as $key => $value) {
            $level = 0;
            foreach ($value as $subkey => $subvalue) {
                if (is_array($subvalue)) {
                    // loop through the sublevel array
                    foreach ($subvalue as $subsubkey => $subsubvalue) {
                        if ($use_communication_type && ($key == 'communication')) {
                            $type = strtolower($contact[$key][$level]['communication_type']);
                            $flatten["{$key}_{$type}_{$subsubkey}"] = $subsubvalue;
                        }
                        else {
                            $flatten["{$key}_{$level}_{$subsubkey}"] = $subsubvalue;
                        }
                    }
                    $level++;
                }
                else {
                    // no further level
                    $flatten["{$subkey}"] = $subvalue;
                }
            }
        }
        return $flatten;
    }

    /**
     * Rebuild a one-level array created with levelDownContactArray() to a
     * regular multilevel contact array
     *
     * @param array $contact
     * @throws ContactException
     * @return array
     */
    public function levelUpContactArray($contact)
    {
        $multi = array();
        $communication_types = array();

        foreach ($contact as $contact_key => $contact_value) {
            $key = substr($contact_key, 0, strpos($contact_key, '_'));
            if ($key == 'contact') {
                // the contact block has no further levels
                $multi[$key][$contact_key] = $contact_value;
            }
            else {
                $level = substr($contact_key, strlen($key)+1);
                $tail = substr($level, strpos($level, '_')+1);
                $level = substr($level, 0, strpos($level, '_'));
                if (is_numeric($level)) {
                    $multi[$key][$level][$tail] = $contact_value;
                }
                elseif ($key == 'communication') {
                    if (!in_array($level, $communication_types)) {
                        $communication_types[] = $level;
                    }
                    $c_level = array_search($level, $communication_types);
                    $multi[$key][$c_level][$tail] = $contact_value;
                }
                else {
                    throw new ContactException("Unexpected structure of the submitted contact array!");
                }
            }
        }
        return $multi;
    }

    /**
     * General select function for contact records.
     * The identifier can be the contact_id or the login name.
     * Return a PERSON or a COMPANY record. If the identifier not exists return
     * a default contact array.
     *
     * @param mixed $identifier
     */
    public function select($identifier)
    {
        if (is_numeric($identifier)) {
            self::$contact_id = $identifier;
            if (self::$contact_id < 1) {
                return $this->getDefaultRecord();
            }
            else {
                if (false === ($contact = $this->ContactData->select(self::$contact_id))) {
                    self::$contact_id = -1;
                    $this->setMessage("The contact with the ID %contact_id% does not exists!", array('%contact_id%' => $identifier));
                    return $this->getDefaultRecord();
                }
                if (false === ($contact = $this->ContactData->selectContact(self::$contact_id))) {
                    $this->setMessage("Can't read the contact with the ID %contact_id% - it is possibly deleted.",
                        array('%contact_id%' => $identifier));
                    return $this->getDefaultRecord();
                }
                return $contact;
            }
        }
        else {
            echo "string";
        }
    }

    /**
     * Validate the CONTACT block and perform some actions.
     * Can set the 'contact_login' and the 'contact_name' if specified in the
     * $option array
     *
     * @param reference array $data
     * @param array $contact_data
     * @param array $option
     * @return boolean
     */
    protected function validateContact(&$data, $contact_data=null, $option=null)
    {
        // the contact_id must be always set
        if (!isset($data['contact_id']) || !is_numeric($data['contact_id'])) {
            $this->setMessage("Missing the %identifier%! The ID should be set to -1 if you insert a new record.",
                array('%identifier%' => 'contact_id'));
            return false;
        }

        // the contact type must be always set
        $contact_types = $this->ContactData->getContactTypes();
        if (!isset($data['contact_type']) || !in_array($data['contact_type'], $contact_types)) {
            $this->setMessage("The contact_type must be always set (%contact_types%).",
                array('%contact_types%' => implode(', ', $contact_types)));
            return false;
        }

        if (!isset($option['mode']['insert'])) {
            // check only if not insert a new record
            if (!isset($data['contact_login']) || empty($data['contact_login'])) {
                // missing the login
                if (isset($option['login']['use_email_address']) && $option['login']['use_email_address']) {
                    // try to use the email as login

                    if (isset($contact_data['communication'])) {
                        $use_email = false;
                        foreach ($contact_data['communication'] as $communication) {
                            if (isset($communication['communication_type']) && $communication['communication_type'] == 'EMAIL') {
                                if (isset($communication['communication_value'])) {
                                    $errors = $this->app['validator']->validateValue($communication['communication_value'], new Assert\Email());
                                    if (count($errors) > 0) {
                                        $this->setMessage("The contact login must be set!");
                                        return false;
                                    }
                                    else {
                                        $data['contact_login'] = strtolower($communication['communication_value']);
                                        $use_email = true;
                                        break;
                                    }
                                }

                            }
                        }
                        if (!$use_email) {
                            $this->setMessage("The contact login must be set!");
                            return false;
                        }
                    }
                    else {
                        $this->setMessage("The contact login must be set!");
                        return false;
                    }
                }
                else {
                    $this->setMessage("The contact login must be set!");
                    return false;
                }
            }

            if (!isset($data['contact_name']) || empty($data['contact_name'])) {
                if (isset($option['name']['use_login']) && $option['name']['use_login']) {
                    // use the LOGIN also for the NAME
                    $data['contact_name'] = $data['contact_login'];
                }
                else {
                    $this->setMessage("The contact name must be set!");
                    return false;
                }
            }
        }

        // if this is new record check it the login name is available
        if (($data['contact_id'] < 1) &&
            (false !== ($check = $this->ContactData->selectLogin($data['contact_login'])))) {
            $this->setMessage('The login <b>%login%</b> is already in use, please choose another one!',
                array('%login%' => $data['contact_login']));
            return false;
        }

        return true;
    }

    /**
     * Validate the given $data record for all contact types.
     * With $options you can define the ContentBlocks which will be validated.
     * You can also use SetContactBlocks() to set the ContentBlocks global for
     * all operations and not only for validate()
     *
     * @param array $data
     * @param array $options if empty the global ContactBlocks will be used
     * @return boolean
     */
    public function validate(&$contact_data, $options=array())
    {
        if (!is_array($options) || empty($options)) {
            $options = self::$ContactBlocks;
        }

        $check = true;
        $this->clearMessage();

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $block = strtolower($key);
                $validate_options = $value;
            }
            else {
                $block = strtolower($value);
                $validate_options = array();
            }
            switch ($block) {
                case 'contact':
                    // check the contact block
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        if (!$this->validateContact($contact_data[$block], $contact_data, $validate_options)) {
                            $check = false;
                        }
                    }
                    break;
                case 'person':
                    // check the person block
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        $level = 0;
                        foreach ($contact_data[$block] as $person_data) {
                            if (!$this->ContactPerson->validate($person_data, $contact_data, $validate_options)) {
                                $this->mergeMessage($this->ContactPerson->getMessage());
                                $check = false;
                            }
                            $contact_data[$block][$level] = $person_data;
                            $level++;
                        }
                    }
                    break;
                case 'company':
                    // check the company block
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        $level = 0;
                        foreach ($contact_data[$block] as $company_data) {
                            if (!$this->ContactCompany->validate($company_data, $contact_data, $validate_options)) {
                                $this->mergeMessage($this->ContactCompany->getMessage());
                                $check = false;
                            }
                            $contact_data[$block][$level] = $company_data;
                            $level++;
                        }
                    }
                    break;
                case 'communication':
                    // check the communication block
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        $level = 0;
                        foreach ($contact_data[$block] as $communication_data) {
                            if (!$this->ContactCommunication->validate($communication_data, $contact_data, $validate_options)) {
                                $this->mergeMessage($this->ContactCommunication->getMessage());
                                $check = false;
                            }
                            $contact_data[$block][$level] = $communication_data;
                            $level++;
                        }
                    }
                    break;
                case 'address':
                    // check the address block
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        $level = 0;
                        foreach ($contact_data[$block] as $address_data) {
                            if (!$this->ContactAddress->validate($address_data, $contact_data, $validate_options)) {
                                $this->mergeMessage($this->ContactAddress->getMessage());
                                $check = false;
                            }
                            $contact_data[$block][$level] = $address_data;
                            $level++;
                        }
                    }
                    break;
                case 'note':
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        $level = 0;
                        foreach ($contact_data[$block] as $note_data) {
                            if (!$this->ContactNote->validate($note_data, $contact_data, $validate_options)) {
                                $this->mergeMessage($this->ContactNote->getMessage());
                                $check = false;
                            }
                            $contact_data[$block][$level] = $note_data;
                            $level++;
                        }
                    }
                    break;
                default:
                    // ContactBlock does not exists
                    throw new \Exception("The ContactBlock $block does not exists!");
            }
        }

        // return the result of the check
        return $check;
    }

    /**
     * Insert the contact block of the new contact record into the database
     *
     * @param array $contact_data
     * @param array $complete_data
     * @param reference integer $contact_id
     * @return boolean
     */
    protected function insertContact($contact_data, $complete_data=null, &$contact_id=null)
    {
        $contact_data['contact_id'] = -1;

        $contact_blocks = $this->getContactBlocks();
        $option = isset($contact_blocks['content']) ? $contact_blocks['content'] : array();
        $option['mode'] = 'insert';

        // if no contact_login isset, try to set the email address as login
        if (!isset($contact_data['contact_login']) || empty($contact_data['contact_login'])) {
            // try to get an email address
            $check = false;
            if (isset($complete_data['communication'])) {
                foreach ($complete_data['communication'] as $communication) {
                    if (isset($communication['communication_type']) && ($communication['communication_type'] == 'EMAIL') &&
                        !empty($communication['communication_value'])) {
                        $contact_data['contact_login'] = $communication['communication_value'];
                        $check = true;
                        break;
                    }
                }
            }

            if (!$check) {
                $this->setMessage("The login_name or a email address must be always set, can't insert the record!");
                return false;
            }
        }

        if (!isset($contact_data['contact_name']) || empty($contact_data['contact_name'])) {
            // set the contact_login also as contact_name
            $contact_data['contact_name'] = $contact_data['contact_login'];
        }

        if (!$this->validateContact($contact_data, $complete_data, $option)) {
            // contact validation fail
            return false;
        }

        // insert the new record
        $this->ContactData->insert($contact_data, $contact_id);

        return true;
    }


    /**
     * Insert the given $data record into the contact database. Process all needed
     * steps, uses transaction and roll back if necessary.
     *
     * @param array $data
     * @param reference integer $contact_id
     * @throws ContactException
     * @return boolean
     */
    public function insert($data, &$contact_id=null)
    {

        try {
            // BEGIN TRANSACTION
            $this->app['db']->beginTransaction();

            $this->clearMessage();

            // get the contact blocks with the options
            $contact_blocks = $this->getContactBlocks();

            // first step: insert a contact record
            if (!isset($data['contact'])) {
                $this->setMessage("Missing the contact block! Can't insert the new record!");
                $this->app['db']->rollback();
                return false;
            }

            if (!$this->insertContact($data['contact'], $data, self::$contact_id)) {
                $this->app['db']->rollback();
                return false;
            }
            // set the contact ID
            $data['contact']['contact_id'] = self::$contact_id;
            $contact_id = self::$contact_id;

            // as next we need the person record
            if (isset($data['person'])) {
                foreach ($data['person'] as $person) {
                    if (!is_array($person)) continue;
                    if (!$this->ContactPerson->insert($person, self::$contact_id)) {
                        // something went wrong, rollback and return with message
                        $this->app['db']->rollback();
                        return false;
                    }
                }
            }

            // todo: COMPANY

            // check the communication
            if (isset($data['communication'])) {
                foreach ($data['communication'] as $communication) {
                    if (!is_array($communication)) continue;
                    if (!$this->ContactCommunication->insert($communication, self::$contact_id)) {
                        // rollback and return to the dialog
                        $this->app['db']->rollback();
                        return false;
                    }
                }
            }

            if (isset($data['address'])) {
                foreach ($data['address'] as $address) {
                    // loop through the addresses
                    if (!is_array($address)) continue;
                    if (!$this->ContactAddress->insert($address, self::$contact_id)) {
                        // rollback and return to the dialog
                        $this->app['db']->rollback();
                        return false;
                    }
                }
            }

            if (isset($data['note'])) {
                foreach ($data['note'] as $note) {
                    if (!is_array($note)) continue;
                    if (!$this->ContactNote->insert($note, self::$contact_id)) {
                        // something went wrong, rollback
                        $this->app['db']->rollback();
                        return false;
                    }
                }
            }

            // all complete - now we refresh the OVERVIEW
            $this->Overview->refresh($contact_id);

            // COMMIT TRANSACTION
            $this->app['db']->commit();

            if (!$this->isMessage()) {
                $this->setMessage("Inserted the new contact with the ID %contact_id%.", array('%contact_id%' => self::$contact_id));
            }

            return true;
        } catch (\Exception $e) {
            // ROLLBACK TRANSACTION
            $this->app['db']->rollback();
            throw new ContactException($e);
        }
    }

    /**
     * Update a contact block record with the given new and old data for the
     * specified contact ID
     *
     * @param array $new_data the data to update
     * @param array $old_data the existing data from database
     * @param integer $contact_id
     * @param reference boolean $has_changed will be set to true if data has changed
     * @return boolean
     */
    protected function updateContact($new_data, $old_data, $contact_id, &$has_changed=false)
    {
        $has_changed = false;
        $changed = array();

        foreach ($new_data as $key => $value) {
            if ($key == 'contact_key') continue;
            if ($old_data[$key] != $value) {
                $changed[$key] = $value;
            }
        }

        if (!empty($changed)) {
            foreach ($changed as $key => $value) {
                switch ($key) {
                    case 'contact_login':
                        if (is_null($value) || empty($value)) {
                            // contact_login must be always set!
                            $this->setMessage("The field %field% can not be empty!", array('%field%' => 'contact_login'));
                            return false;
                        }
                        // check if the login already exists
                        if ($this->ContactData->existsLogin($value, $contact_id)) {
                            $this->setMessage('The login <b>%login%</b> is already in use, please choose another one!',
                                array('%login%' => $value));
                            return false;
                        }
                        break;
                    case 'contact_name':
                        if (is_null($value) || empty($value)) {
                            // contact_name must be always set!
                            $this->setMessage("The field %field% can not be empty!", array('%field%' => 'contact_name'));
                            return false;
                        }
                        if ($this->ContactData->existsName($value, $contact_id)) {
                            // the contact_name already exists - tell it the user but update the record!
                            $this->setMessage("The contact name %name% already exists! The update has still executed, please check if you really want this duplicate name.",
                                array('%name%' => $value));
                            // don't return false!!!
                        }
                }
            }
            $this->ContactData->update($changed, $contact_id);
            $has_changed = true;
        }

        return true;
    }

    /**
     * Update the complete contact with all blocks
     *
     * @param array $data regular contact array
     * @param integer $contact_id
     * @param reference boolean $data_changed will be set to true if data has changed
     * @throws ContactException
     * @throws \Exception
     * @return boolean
     */
    public function update($data, $contact_id, &$data_changed=false)
    {
        // first get the existings record
        if (false === ($old = $this->ContactData->selectContact($contact_id))) {
            $this->setMessage("The contact with the ID %contact_id% does not exists!",
                array('%contact_id%' => $contact_id));
            return false;
        }

        try {
            // start transaction
            $this->app['db']->beginTransaction();

            $this->clearMessage();

            $data_changed = false;

            // contact block
            if (isset($data['contact'])) {
                $has_changed = false;
                if (!$this->updateContact($data['contact'], $old['contact'], $contact_id, $has_changed)) {
                    // rollback
                    $this->app['db']->rollback();
                    return false;
                }
                if ($has_changed) {
                    $data_changed = true;
                }
            }
            else {
                $this->setMessage("The contact block must be set always!");
                // rollback
                $this->app['db']->rollback();
                return false;
            }

            if ($old['contact']['contact_type'] == 'COMPANY') {

                // Contact TYPE: COMPANY
                throw new ContactException("The contact type COMPANY is not supported yet!");

            }
            else {
                // Contact TYPE: PERSON
                if (isset($data['person'])) {
                    foreach ($data['person'] as $new_person) {
                        $has_changed = false;
                        if (count($old['person']) < 1) {
                            // no handling of multiple persons
                            throw new ContactException("The handling of multiple persons within one account of type PERSON is not supported yet.");
                        }
                        foreach ($old['person'] as $old_person) {
                            if ($old_person['person_id'] == $new_person['person_id']) {
                                // update the person
                                if (!$this->ContactPerson->update($new_person, $old_person, $new_person['person_id'], $has_changed)) {
                                    // rollback
                                    $this->app['db']->rollback();
                                    return false;
                                }
                                if ($has_changed) {
                                    $data_changed = true;
                                }
                                break;
                            }
                        }
                    }
                }
            }

            if (isset($data['communication'])) {
                foreach ($data['communication'] as $new_communication) {
                    if (!is_array($new_communication)) continue;
                    if (!isset($new_communication['communication_id'])) {
                        throw new ContactException("Update check fail because the 'communication_id' is missing in the 'communication' block!");
                    }
                    if ($new_communication['communication_id'] < 1) {
                        // insert a new communication record
                        $communication_id = -1;
                        $has_inserted = false;
                        if (!$this->ContactCommunication->insert($new_communication, $contact_id, $communication_id, $has_inserted)) {
                            // rollback
                            $this->app['db']->rollback();
                            return false;
                        }
                        if ($has_inserted) {
                            $data_changed = true;
                        }
                        continue;
                    }
                    $processed = false;
                    foreach ($old['communication'] as $old_communication) {
                        if ($old_communication['communication_id'] == $new_communication['communication_id']) {
                            $has_changed = false;
                            if (!$this->ContactCommunication->update($new_communication, $old_communication, $new_communication['communication_id'], $has_changed)) {
                                // rollback
                                $this->app['db']->rollback();
                                return false;
                            }
                            if ($has_changed) {
                                $data_changed = true;
                            }
                            $processed = true;
                            break;
                        }
                    }
                    if (!$processed) {
                        // the communication entry was not processed!
                        $this->setMessage("The %entry% entry with the ID %id% was not processed, there exists no fitting record for comparison!",
                            array(
                                '%id%' => $new_communication['communication_id'],
                                '%entry%' => 'communication'
                            ));
                        $this->addError("The communication ID {$new_communication['communication_id']} was not updated because it was not found in the table!",
                            array(__METHOD__, __LINE__));
                    }
                }
            }

            if (isset($data['address'])) {
                foreach ($data['address'] as $new_address) {
                    if (!is_array($new_address)) continue;
                    if (!isset($new_address['address_id'])) {
                        throw new ContactException("Update check fail because the 'address_id' is missing in the 'address' block!");
                    }
                    if ($new_address['address_id'] < 1) {
                        // insert a new address
                        $address_id = -1;
                        $has_inserted = false;
                        $this->ContactAddress->insert($new_address, $contact_id, $address_id, $has_inserted);
                        if ($has_inserted) {
                            $data_changed = true;
                        }
                        continue;
                    }
                    $processed = false;
                    foreach ($old['address'] as $old_address) {
                        if ($old_address['address_id'] == $new_address['address_id']) {
                            $has_changed = false;
                            if (!$this->ContactAddress->update($new_address, $old_address, $new_address['address_id'], $has_changed)) {
                                // rollback
                                $this->app['db']->rollback();
                                return false;
                            }
                            if ($has_changed) {
                                $data_changed = true;
                            }
                            $processed = true;
                            break;
                        }
                    }
                    if (!$processed) {
                        // the address entry was not processed!
                        $this->setMessage("The %entry% entry with the ID %id% was not processed, there exists no fitting record for comparison!",
                            array(
                                '%id%' => $new_address['address_id'],
                                '%entry%' => 'address'
                            ));
                        $this->addError("The address ID {$new_address['address_id']} was not updated because it was not found in the table!",
                            array(__METHOD__, __LINE__));
                    }
                }
            }

            if (isset($data['note'])) {
                foreach ($data['note'] as $new_note) {
                    if (!is_array($new_note)) continue;
                    if (!isset($new_note['note_id'])) {
                        throw new \Exception("Update check fail because the 'note_id' is missing in the 'note' block!");
                    }
                    if ($new_note['note_id'] < 1) {
                        // insert a new note
                        $note_id = -1;
                        $has_inserted = false;
                        $this->ContactNote->insert($new_note, $contact_id, $note_id, $has_inserted);
                        if ($has_inserted) {
                            $data_changed = true;
                        }
                        continue;
                    }
                    $processed = false;
                    foreach ($old['note'] as $old_note) {
                        if ($old_note['note_id'] == $new_note['note_id']) {
                            $has_changed = false;
                            if (!$this->ContactNote->update($new_note, $old_note, $new_note['note_id'], $has_changed)) {
                                // rollback
                                $this->app['db']->rollback();
                                return false;
                            }
                            if ($has_changed) {
                                $data_changed = true;
                            }
                            $processed = true;
                            break;
                        }
                    }
                    if (!$processed) {
                        // the address entry was not processed!
                        $this->setMessage("The %entry% entry with the ID %id% was not processed, there exists no fitting record for comparison!",
                            array(
                                '%id%' => $new_note['note_id'],
                                '%entry%' => 'note'
                            ));
                        $this->addError("The note ID {$new_note['note_id']} was not updated because it was not found in the table!",
                        array(__METHOD__, __LINE__));
                    }
                }
            }

            if ($data_changed) {
                // all complete - now we refresh the OVERVIEW
                $this->Overview->refresh($contact_id);
            }

            // commit transaction
            $this->app['db']->commit();

            if ($data_changed) {
                if (!$this->isMessage()) {
                    $this->setMessage("The contact with the ID %contact_id% was successfull updated.",
                        array('%contact_id%' => self::$contact_id));
                }
            }
            else {
                if (!$this->isMessage()) {
                    $this->setMessage("The contact record was not changed!");
                }
            }

            return true;
        } catch (ContactException $e) {
            // rollback transaction
            $this->app['db']->rollback();
            throw new ContactException($e);
        }
    }
}

