<?php
namespace Tah\MsiofStripe;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\ServiceControllerResolver;

/**
 * Class: MsiofStripeServiceProvider
 *
 * @see Silex\ServiceProviderInterface
 * @see Silex\ControllerProviderInterface
 */
class MsiofStripeServiceProvider implements ServiceProviderInterface, ControllerProviderInterface
{
		  /**
		  * connect
		  *
		  * @param Application $app
		  *
		  * @return Silex\ControllerCollection
		  */
		  public function connect(Application $app)
		  {
					 // creates a new controller based on the default route
					 $controllers = $app['controllers_factory'];

					 $controllers->get('/', function (Application $app) {
								return 'It works';
					 });

					 return $controllers;
		  }
}
