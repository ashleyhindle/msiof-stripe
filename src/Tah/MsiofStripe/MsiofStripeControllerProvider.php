<?php
namespace Tah\MsiofStripe;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\ServiceControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use SimpleUser\UserEvents;
use SimpleUser\UserEvent;

/**
 * Class: MsiofStripeServiceProvider
 *
 * @see Silex\ControllerProviderInterface
 */
class MsiofStripeControllerProvider implements ControllerProviderInterface
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
					 })->bind('msiof-stripe-index');

					 $controllers->get('/pay', function (Application $app) {
								if (!$app['user']) {
										  return $app->redirect('user.login');
								}

								$form = '
								<form action="" method="POST">
								<script
								src="https://checkout.stripe.com/checkout.js" class="stripe-button"
								data-key="pk_test_2bpghGfYvZb4cS2rYIhpcC31"
								data-amount="2000"
								data-name="Unlimited MSIOF"
								data-description="Unlimited (&pound; 20/month)"
								data-image="/128x128.png"
								data-currency="GBP"
								data-allowrememberme="false"
								data-email="ashley@smellynose.com"
								>
								</script>
								</form>
								';

								return $form;
					 })->bind('msiof-stripe-pay-form');

					 $controllers->post('/pay', function(Application $app, Request $request) {
								if (!$app['user']) {
										  return $app->redirect('user.login');
								}

								\Stripe::setApiKey('sk_test_26P4i0fynum9NZWXomS2wlTd');
								$customer = \Stripe_Customer::create([
										  'email' => $request->get('stripeEmail'),
										  'card'  => $request->get('stripeToken')
								]);

								$app['user']->setCustomField('stripe_customer_id', $customer->id);
								$app['user.manager']->update($app['user']);

								$charge = \Stripe_Charge::create([
										  'customer' => $customer->id,
										  'amount'   => 2000,
										  'currency' => 'gbp'
								]);

								var_dump($charge);

								return print_r($_POST, true);
					 })->bind('msiof-stripe-pay-post');

					 $controllers->post('/webhook', function(Application $app, Request $request) {
								return 'Thanks';
					 });


					 return $controllers;
		  }
}
