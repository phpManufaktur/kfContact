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

class Contact extends ContactParent
{

    protected static $contact_id = -1;
    protected static $person_id = -1;

    protected static $name = '';
    protected static $login = '';
    protected static $status = 'ACTIVE';
    protected static $timestamp = '0000-00-00 00:00:00';
    protected static $type = 'PERSON';
    protected static $person = null;
    protected static $company = null;


    protected $ContactData = null;
    protected $ContactPerson = null;
    protected $ContactCompany = null;
    protected $ContactCommunication = null;
    protected $ContactAddress = null;
    protected $ContactNote = null;

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

        $this->ContactPerson = new ContactPerson($this->app);
        $this->ContactData = new ContactData($this->app);
        $this->ContactCommunication = new ContactCommunication($this->app);
        $this->ContactAddress = new ContactAddress($this->app);
        $this->ContactNote = new ContactNote($this->app);
        $this->ContactCompany = new ContactCompany($this->app);
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
                'contact_type' => self::$type,
                'contact_status' => 'ACTIVE',
                'contact_timestamp' => '0000-00-00 00:00:00',
            )
        );

        if (self::$type === 'PERSON') {
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
                $contact = $this->ContactData->selectContact(self::$contact_id);
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
    protected function validateContact(&$data, $contact_data=array(), $option=array())
    {
        // the contact_id must be always set
        if (!isset($data['contact_id']) || !is_numeric($data['contact_id'])) {
            $this->setMessage("Missing the %identifier%! The ID should be set to -1 if you insert a new record.",
                array('%identifier%' => 'contact_id'));
            return false;
        }
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

        $message = '';
        $check = true;

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
                            $message .= $this->getMessage();
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
                                $message .= $this->ContactPerson->getMessage();
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
                                $message .= $this->ContactCompany->getMessage();
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
                                $message .= $this->ContactCommunication->getMessage();
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
                                $message .= $this->ContactAddress->getMessage();
                                $check = false;
                            }
                            $contact_data[$block][$level] = $address_data;
                            $level++;
                        }
                    }
                    break;
                case 'note':
                    if (isset($contact_data[$block]) && is_array($contact_data[$block])) {
                        if (!$this->ContactNote->validate($contact_data, $validate_options)) {
                            $message .= $this->ContactNote->getMessage();
                            $check = false;
                        }
                    }
                    break;
                default:
                    // ContactBlock does not exists
                    throw new \Exception("The ContactBlock $block does not exists!");
            }
        }
        self::$message = $message;
        return $check;
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

        if (!$this->validate($data)) {
            return false;
        }
        try {
            // BEGIN TRANSACTION
            $this->app['db']->beginTransaction();

            // first step: insert a contact record
            $this->ContactData->insert($data['contact'], self::$contact_id);
            $contact_id = self::$contact_id;

            // check the communication
            if (isset($data['communication'])) {
                foreach ($data['communication'] as $communication) {
                    // accept only arrays
                    if (!is_array($communication)) continue;
                    // ignore empty values
                    if (empty($communication['communication_value'])) continue;
                    $communication_id = -1;
                    if ($this->ContactCommunication->insert($communication, self::$contact_id, $communication_id)) {
                        switch ($communication['communication_type']) {
                            case 'EMAIL':
                                if (self::$type === 'PERSON') {
                                    if (!isset($data['person'][0]['person_primary_email_id']) ||
                                        (isset($data['person'][0]['person_primary_email_id']) && ($data['person'][0]['person_primary_email_id'] < 1))) {
                                        $data['person'][0]['person_primary_email_id'] = $communication_id;
                                    }
                                }
                                else {
                                    throw ContactException::contactTypeNotSupported(self::$type);
                                }
                                break;
                            case 'PHONE':
                                if (self::$type === 'PERSON') {
                                    if (!isset($data['person'][0]['person_primary_phone_id']) ||
                                        (isset($data['person'][0]['person_primary_phone_id']) && ($data['person'][0]['person_primary_phone_id'] < 1))) {
                                        $data['person'][0]['person_primary_phone_id'] = $communication_id;
                                    }
                                }
                                else {
                                    throw ContactException::contactTypeNotSupported(self::$type);
                                }
                                break;
                        }
                    }
                    else {
                        // rollback and return to the dialog
                        $this->app['db']->rollback();
                        self::$message = $this->ContactCommunication->getMessage();
                        return false;
                    }
                }
            }

            if (isset($data['address'])) {
                foreach ($data['address'] as $address) {
                    // loop through the addresses
                    if (!is_array($address)) continue;
                    $address_id = -1;
                    if ($this->ContactAddress->insert($address, self::$contact_id, $address_id)) {
                        if (self::$type === 'PERSON') {
                            if (!isset($data['person'][0]['person_primary_address_id']) ||
                                (isset($data['person'][0]['person_primary_address_id']) && ($data['person'][0]['person_primary_address_id'] < 1))) {
                                // pick the first address as primary address
                                $data['person'][0]['person_primary_address_id'] = $address_id;
                            }
                        }
                        else {
                            throw ContactException::contactTypeNotSupported(self::$type);
                        }
                    }
                    else {
                        // rollback and return to the dialog
                        $this->app['db']->rollback();
                        self::$message = $this->ContactAddress->getMessage();
                        return false;
                    }
                }
            }

            if (isset($data['note'])) {
                foreach ($data['note'] as $note) {
                    if (!is_array($note)) continue;
                    $note_id = -1;
                    if ($this->ContactNote->insert($note, self::$contact_id, $note_id)) {
                        if (self::$type === 'PERSON') {
                            if (!isset($data['person'][0]['person_primary_note_id']) ||
                            (isset($data['person'][0]['person_primary_note_id']) && ($data['person'][0]['person_primary_note_id'] < 1))) {
                                // pick the first address as primary address
                                $data['person'][0]['person_primary_note_id'] = $note_id;
                                break;
                            }
                        }
                        else {
                            throw ContactException::contactTypeNotSupported(self::$type);
                        }
                    }
                    else {
                        // rollback and return to the dialog
                        $this->app['db']->rollback();
                        self::$message = $this->ContactNote->getMessage();
                        return false;
                    }
                }
            }

            if (isset($data['person'])) {
                foreach ($data['person'] as $person) {
                    if (!is_array($person)) continue;
                    if (!$this->ContactPerson->insert($person, self::$contact_id, self::$person_id)) {
                        // something went wrong, rollback and return with message
                        $this->app['db']->rollback();
                        self::$message = $this->ContactPerson->getMessage();
                        return false;
                    }
                }
            }

            // COMMIT TRANSACTION
            $this->app['db']->commit();
            return true;
        } catch (\Exception $e) {
            // ROLLBACK TRANSACTION
            $this->app['db']->rollback();
            throw new ContactException($e);
        }
    }

    public function update($data, $contact_id)
    {
        if (!$this->validate($data)) {
            return false;
        }
        // first get the existings record
        if (false === ($old = $this->ContactData->selectContact($contact_id))) {
            $this->setMessage("The contact with the ID %contact_id% does not exists!",
                array('%contact_id%' => $contact_id));
            return false;
        }

        try {
            // start transaction
            $this->app['db']->beginTransaction();

            $data_changed = false;

            if (isset($data['contact'])) {
                $changed = array();
                foreach ($data['contact'] as $key => $value) {
                    if ($key === 'contact_id') continue;
                    if ($old['contact'][$key] !== $value) {
                        $changed[$key] = $value;
                    }
                }
                if (!empty($changed)) {
                    $data_changed = true;
                    $this->ContactData->update($changed, $contact_id);
                }
            }

            if (isset($data['person'])) {
                foreach ($data['person'] as $new_person) {
                    if (!isset($new_person['person_id'])) {
                        throw new \Exception("The update check fail because the 'person_id' is missing in the 'person' block!");
                    }
                    if ($new_person['person_id'] < 1) {
                        // add as new record
                        if (!$this->ContactPerson->insert($new_person, $contact_id)) {
                            self::$message = $this->ContactPerson->getMessage();
                            // rollback
                            $this->app['db']->rollback();
                            return false;
                        }
                        $data_changed = true;
                        continue;
                    }
                    foreach ($old['person'] as $old_person) {
                        if ($old_person['person_id'] === $new_person['person_id']) {
                            $has_changed = false;
                            if (!$this->ContactPerson->update($new_person, $old_person, $new_person['person_id'], $has_changed)) {
                                self::$message = $this->ContactPerson->getMessage();
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

            if (isset($data['communication'])) {
                foreach ($data['communication'] as $new_communication) {
                    if (!is_array($new_communication)) continue;
                    if (!isset($new_communication['communication_id'])) {
                        throw new \Exception("Update check fail because the 'communication_id' is missing in the 'communication' block!");
                    }
                    if ($new_communication['communication_id'] === -1) {
                        if (isset($new_communication['communication_type']) && !empty($new_communication['communication_type']) &&
                            isset($new_communication['communication_value']) && ! empty($new_communication['communication_value'])) {
                            // add as new record
                            $communication_id = -1;
                            if (!$this->ContactCommunication->insert($new_communication, $contact_id, $communication_id)) {
                                self::$message = $this->ContactCommunication->getMessage();
                                // rollback
                                $this->app['db']->rollback();
                                return false;
                            }
                            $data_changed = true;
                        }
                        continue;
                    }
                    foreach ($old['communication'] as $old_communication) {
                        if ($old_communication['communication_id'] == $new_communication['communication_id']) {
                            $has_changed = false;
                            if (!$this->ContactCommunication->update($new_communication, $old_communication, $new_communication['communication_id'], $has_changed)) {
                                self::$message = $this->ContactCommunication->getMessage();
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

            if (isset($data['address'])) {
                foreach ($data['address'] as $new_address) {
                    if (!is_array($new_address)) continue;
                    if (!isset($new_address['address_id'])) {
                        throw new \Exception("Update check fail because the 'address_id' is missing in the 'address' block!");
                    }
                    if ($new_address['address_id'] < 1) {
                        // insert a new address
                        $address_id = -1;
                        $this->ContactAddress->insert($new_address, $contact_id, $address_id);
                        $data_changed = true;
                        continue;
                    }
                    foreach ($old['address'] as $old_address) {
                        if ($old_address['address_id'] == $new_address['address_id']) {
                            $has_changed = false;
                            if (!$this->ContactAddress->update($new_address, $old_address, $new_address['address_id'], $has_changed)) {
                                self::$message = $this->ContactAddress->getMessage();
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


            if (isset($data['note'])) {
                foreach ($data['note'] as $new_note) {
                    if (!is_array($new_note)) continue;
                    if (!isset($new_note['note_id'])) {
                        throw new \Exception("Update check fail because the 'note_id' is missing in the 'note' block!");
                    }
                    if ($new_note['note_id'] < 1) {
                        // insert a new note
                        $this->ContactNote->insert($new_note, $contact_id);
                        $data_changed = true;
                        continue;
                    }

                }
            }

            // commit transaction
            $this->app['db']->commit();

            if (!$data_changed) {
                $this->setMessage("The contact record was not changed!");
                return false;
            }
            return true;
        } catch (\Exception $e) {
            // rollback transaction
            $this->app['db']->rollback();
            throw new ContactException($e);
        }
    }
}

