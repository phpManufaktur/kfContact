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
use phpManufaktur\Contact\Data\Contact\Address;
use phpManufaktur\Contact\Data\Contact\Country;
use phpManufaktur\Contact\Data\Contact\Contact as ContactData;
use phpManufaktur\Contact\Data\Contact\Person;

class ContactAddress extends ContactParent
{
    protected $Address = null;
    protected $Country = null;
    protected $Contact = null;
    protected $Person = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->Address = new Address($this->app);
        $this->Country = new Country($this->app);
        $this->Contact = new ContactData($this->app);
        $this->Person = new Person($this->app);
    }

    public function getDefaultRecord()
    {
        return $this->Address->getDefaultRecord();
    }

    /**
     * Validate the given ADDRESS
     *
     * @param reference array $address_data
     * @param array $contact_data
     * @param array $option
     * @return boolean
     */
    public function validate(&$address_data, $contact_data=array(), $option=array())
    {
        if (!isset($address_data['address_id']) || !is_numeric($address_data['address_id'])) {
            $this->setMessage("Missing the %identifier%! The ID should be set to -1 if you insert a new record.",
                array('%identifier%' => 'address_id'));
            return false;
        }

        // check if any value is NULL
        foreach ($address_data as $key => $value) {
            if (is_null($value)) {
                switch ($key) {
                    case 'contact_id':
                        $address_data[$key] = -1;
                        break;
                    case 'address_type':
                        $address_data[$key] = 'OTHER';
                        break;
                    case 'address_identifier':
                    case 'address_description':
                    case 'address_street':
                    case 'address_appendix_1':
                    case 'address_appendix_2':
                    case 'address_zip':
                    case 'address_city':
                    case 'address_area':
                    case 'address_state':
                    case 'address_country_code':
                        $address_data[$key] = '';
                        break;
                    case 'address_status':
                        $address_data[$key] = 'ACTIVE';
                        break;
                    default:
                        throw new ContactException("The key $key is not defined!");
                        break;
                }
            }
        }

        if ((isset($address_data['address_street']) && (!empty($address_data['address_street']))) ||
            (isset($address_data['address_city']) && !empty($address_data['address_city'])) ||
            (isset($address_data['address_zip']) && !empty($address_data['address_zip']))) {
            // passed the minimum requirements, go ahead with all other checks

            if (isset($address_data['address_country_code']) && !empty($address_data['address_country_code'])) {
                $address_data['address_country_code'] = strtoupper(trim($address_data['address_country_code']));
                if (!$this->Country->existsCountryCode($address_data['address_country_code'])) {
                    $this->setMessage('The country code %country_code% does not exists!',
                        array('%country_code%' => $address_data['address_country_code']));
                    return false;
                }
            }

            if (isset($address_data['address_country_code']) && ($address_data['address_country_code'] == 'DE') &&
                isset($address_data['address_zip']) && !empty($address_data['address_zip'])) {
                // check the german ZIP code
                if (!preg_match('/^(?!01000|99999)(0[1-9]\d{3}|[1-9]\d{4})$/', $address_data['address_zip'])) {
                    $this->setMessage('The zip %zip% is not valid!', array('%zip%' => $address_data['address_zip']));
                    return false;
                }
            }

        }
        /*
        elseif ($address_data['address_id'] > 0) {
            // existing address ID, assume the record should be deleted
            return true;
        }
        else {
            // minimum requriments fail
            $this->setMessage("At minimum you must specify a street, a city or a zip code for a valid address");
            return false;
        }
        */
        return true;
    }

    /**
     * Insert a ADDRESS record
     *
     * @param array $data
     * @param integer $contact_id
     * @param reference integer $address_id
     * @return boolean
     */
    public function insert($data, $contact_id, &$address_id=-1)
    {
        // enshure that the contact_id isset
        $data['contact_id'] = $contact_id;

        if (!$this->validate($data)) {
           return false;
        }
        if ((isset($data['address_street']) && (!empty($data['address_street']))) ||
            (isset($data['address_city']) && !empty($data['address_city'])) ||
            (isset($data['address_zip']) && !empty($data['address_zip']))) {

            // insert only, if street, city or zip isset
            $this->Address->insert($data, $address_id);
            $this->app['monolog']->addDebug("Insert address ID $address_id for contact ID $contact_id");

            // check if a primary address isset for the contact
            if ($this->Contact->getPrimaryAddressID($contact_id) < 1) {
                // set the primary address
                $this->Contact->setPrimaryAddressID($contact_id, $address_id);
                $this->app['monolog']->addDebug("Set address ID $address_id as primary address for the contact ID $contact_id");
            }
        }
        else {
            // nothing to do
            $this->app['monolog']->addDebug("Skipped ADDRESS insert because no street, zip or city isset.");
        }
        return true;
    }

    public function update($new_data, $old_data, $address_id, &$has_changed=false)
    {
        $has_changed = false;

        if ((!isset($new_data['address_street']) || empty($new_data['address_street'])) &&
            (!isset($new_data['address_zip']) || empty($new_data['address_zip'])) &&
            (!isset($new_data['address_city']) || empty($new_data['address_city']))) {
            // check if this address can be deleted

            if ($this->Address->isUsedAsPrimaryAddress($address_id, $old_data['contact_id'])) {
                $this->setMessage("Can't delete the Adress with the ID %address_id% because it is used as primary address.",
                    array('%address_id%' => $address_id));
                return false;
            }
            else {
                // delete the address
                $this->Address->delete($address_id);
                $this->setMessage("The Address with the ID %address_id% was successfull deleted.",
                    array('%address_id%' => $address_id));
                $has_changed = true;
                return true;
            }
        }

        // now we can validate the address
        if (!$this->validate($new_data)) {
            return false;
        }

        echo "check";
        return true;
    }

}