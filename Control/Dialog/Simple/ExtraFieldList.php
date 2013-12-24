<?php

/**
 * Contact
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/contact
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\Contact\Control\Dialog\Simple;

use Silex\Application;
use phpManufaktur\Contact\Data\Contact\ExtraType;

class ExtraFieldList extends Dialog {

    protected $ExtraTypeData = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app=null, $options=null)
    {
        parent::__construct($app);
        if (!is_null($app)) {
            $this->initialize($app, $options);
        }
    }

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\Contact\Control\Alert::initialize()
     */
    protected function initialize(Application $app, $options=null)
    {
        parent::initialize($app);

        $this->setOptions(array(
            'template' => array(
                'namespace' => isset($options['template']['namespace']) ? $options['template']['namespace'] : '@phpManufaktur/Contact/Template',
                'alert' => isset($options['template']['alert']) ? $options['template']['alert'] : 'bootstrap/pattern/alert.twig',
                'list' => isset($options['template']['list']) ? $options['template']['list'] : 'bootstrap/pattern/simple/list.extra.twig'
            ),
            'route' => array(
                'edit' => isset($options['route']['edit']) ? $options['route']['edit'] : '/admin/contact/simple/extra/edit/id/{type_id}',
                'create' => isset($options['route']['create']) ? $options['route']['create'] : '/admin/contact/simple/extra/edit'
            )
        ));
        $this->ExtraTypeData = new ExtraType($this->app);
    }

    /**
     * Default controller for the tag list
     *
     * @param Application $app
     */
    public function controller(Application $app)
    {
        $this->app = $app;
        $this->initialize();
        return $this->exec();
    }

    /**
     * Return the tag list
     *
     * @return string tag list
     */
    public function exec($extra=null)
    {
        $fields = $this->ExtraTypeData->selectAll();

        return $this->app['twig']->render($this->app['utils']->getTemplateFile(self::$options['template']['namespace'], self::$options['template']['list']),
            array(
                'message' => $this->getMessage(),
                'alert' => $this->getAlert(),
                'route' => self::$options['route'],
                'fields' => $fields,
                'extra' => $extra
            ));
    }
}
