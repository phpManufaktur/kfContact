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

class ContactParent
{
    protected $app = null;
    protected static $message = '';

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return the $message
     */
    public function getMessage()
    {
        return self::$message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message, $params=array())
    {
        self::$message .= $this->app['twig']->render($this->app['utils']->templateFile('@phpManufaktur/Contact/Template', 'message.twig'),
            array('message' => $this->app['translator']->trans($message, $params)));
    }

    /**
     * Check if a message is active
     *
     * @return boolean
     */
    public function isMessage()
    {
        return !empty(self::$message);
    }

    /**
     * Clear the existing message(s)
     */
    public function clearMessage()
    {
        self::$message = '';
    }

}