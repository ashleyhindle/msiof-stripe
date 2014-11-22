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
 * Class: MsiofStripeControllerProvider
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
								data-amount="0"
								data-name="Upgrade Plan"
								data-description="More Server Fires ($3/server/month)"
								data-image="/128x128.png"
								data-currency="USD"
								data-allowrememberme="false"
								data-email="' . $app['user']->getEmail() . '"
								data-panelLabel="Subscribe"
								data-label="Subscribe"
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

								$subscriptionId = $app['user']->getCustomField('stripe_subscription_id');
								if (!empty($subscriptionId)) {
										  $app['session']->getFlashBag()->set('alert', 'You are already subscribed, what ya playing at?');

										  return $app->redirect('/dashboard');
								}

								\Stripe::setApiKey($app['msiof.stripe']['keys']['secret']);
								$customerId = $app['user']->getCustomField('stripe_customer_id');
								if (empty($customerId)) {
										  /*
											* They don't have a customerid somehow, so we need to make them one
											*/
										  $customer = \Stripe_Customer::create([
													 'email' => $app['user']->getEmail(),
													 'metadata' => [
																'userid' => $app['user']->getId()
													 ]
										  ]);

										  $subscription = $customer->subscriptions->create([
													 "plan" => $app['msiof.stripe']['plans']['free']
										  ]);

										  $app['user']->setCustomField('stripe_subscription_id', $subscription->id);
										  $app['user']->setCustomField('stripe_customer_id', $customer->id);
										  $app['user']->setCustomField('stripe_current_plan', $app['msiof.stripe']['plans']['free']);
										  $app['user.manager']->update($app['user']);
										  $customerId = $customer->id;
								}

								try {
										  $customer = \Stripe_Customer::retrieve($customerId);
										  $subscription = $customer->subscriptions->retrieve($app['user']->getCustomField('stripe_subscription_id'));
										  $subscription->plan = $app['msiof.stripe']['plans']['paid'];
										  $subscription->card = $request->get('stripeToken');
										  $result = $subscription->save();
								} catch (Exception $e) {
										  $app['session']->getFlashBag()->set('alert', 'Something went wrong.  Please get in touch - <a href="mailto:somethingwentwrong@myserverisonfire.com">somethingwentwrong@myserverisonfire.com</a>');

										  return $app->redirect('/');
								}

								$subscriptionId = $result->id;
								$app['user']->setCustomField('stripe_current_plan', 'paid');
								$app['user']->setCustomField('stripe_subscription_id', $subscriptionId);
								$app['user.manager']->update($app['user']);

								return "Subscription id: {$result['id']}";
					 })->bind('msiof-stripe-pay-post');

					 $controllers->post('/webhook', function(Application $app, Request $request) {
								return 'Thanks';
					 });


					 return $controllers;
		  }
}
