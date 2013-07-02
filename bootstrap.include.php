<?php

/**
 * Contact
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/FacebookGallery
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

use phpManufaktur\Contact\Data\Setup\Setup;
use phpManufaktur\Contact\Control\Dialog\Simple\SimpleContact;
use phpManufaktur\Contact\Control\Dialog\Simple\SimpleList;

// scan the /Locale directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/Contact/Data/Locale');

$app->get('/admin/contact/setup', function() use($app) {
    $Setup = new Setup($app);
    $Setup->exec();
    return "Success!";
});

$app->match('/admin/contact/simple/contact', function() use($app) {
    $contact = new SimpleContact($app);
    return $contact->exec();
});

$app->match('/admin/contact/simple/contact/{contact_id}', function($contact_id) use($app) {
    $contact = new SimpleContact($app);
    $contact->setContactID($contact_id);
    return $contact->exec();
});

$app->match('/admin/contact/simple/list', function() use ($app) {
    $list = new SimpleList($app);
    return $list->exec();
});

$app->match('/admin/contact/simple/list/page/{page}', function($page) use ($app) {
    $list = new SimpleList($app);
    $list->setCurrentPage($page);
    return $list->exec();
});

