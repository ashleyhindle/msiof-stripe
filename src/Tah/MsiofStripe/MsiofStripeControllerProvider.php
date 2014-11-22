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

					 $controllers->get('/unsubscribe', function (Application $app) {
								\Stripe::setApiKey($app['msiof.stripe']['keys']['secret']);
								$currentPlan = $app['user']->getCustomField('stripe_current_plan');
								if ($currentPlan != $app['msiof.stripe']['plans']['paid']) {
										 return 'You aren\'t subscribed anyway, so you can\'t unsubscribe';
								}

								if (empty($app['user']->getCustomField('stripe_customer_id'))) {
										  return 'You have no stripe customerid, so you can\'t unsubscribe';
								}

								try {
										  $customer = \Stripe_Customer::retrieve($app['user']->getCustomField('stripe_customer_id'));
										  $result = $customer->subscriptions->retrieve($app['user']->getCustomField('stripe_subscription_id_paid'))->cancel([
													 'at_period_end' => true
										  ]);
										  $app['user']->setCustomField('stripe_subscription_awaiting_cancellation', 1);
										  $app['user.manager']->update($app['user']);
								} catch (Exception $e) {
										  return 'Something went wrong, sorry';
								}

								$app['session']->getFlashBag()->set('alert-success', 'You are now unsubscribed, but why? :(');

								return $app->redirect($app->url('msiof-stripe-account'));
					 })->bind('msiof-stripe-unsubscribe');

					 $controllers->get('/account', function (Application $app) {
								if (!$app['user']) {
										  return $app->redirect('user.login');
								}

								$subscriptionId = $app['user']->getCustomField('stripe_subscription_id_paid');
								if (!empty($subscriptionId)) {
										  if ($app['user']->getCustomField('stripe_subscription_awaiting_cancellation')) {
													 return 'Your subscription is cancelled';
										  } else {
													 return '<a href="/stripe/unsubscribe">Unsubscribe</a>';
										  }
								}

								$serverCount = 26;
								$form = '
								<form action="/stripe/upgrade" method="POST">
								<script
								src="https://checkout.stripe.com/checkout.js" class="stripe-button"
								data-key="pk_test_2bpghGfYvZb4cS2rYIhpcC31"
								data-amount="0"
								data-name="Upgrade Plan"
								data-description="' . $serverCount . ' More Server Fires ($3/server/month)"
								data-image="/128x128.png"
								data-currency="USD"
								data-allowrememberme="false"
								data-email="' . $app['user']->getEmail() . '"
								data-panelLabel="Subscribe"
								data-label="Upgrade"
								>
								</script>
								</form>
								';

								return $form;
					 })->bind('msiof-stripe-account');

					 $controllers->post('/upgrade', function(Application $app, Request $request) {
								if (!$app['user']) {
										  return $app->redirect('user.login');
								}

								$subscriptionId = $app['user']->getCustomField('stripe_subscription_id_paid');
								if (!empty($subscriptionId)) {
										  $app['session']->getFlashBag()->set('alert', 'You are already subscribed, what ya playing at?');

										  return $app->redirect($app->url('dashboard'));
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

										  $app['user']->setCustomField('stripe_subscription_id_free', $subscription->id);
										  $app['user']->setCustomField('stripe_customer_id', $customer->id);
										  $app['user']->setCustomField('stripe_current_plan', $app['msiof.stripe']['plans']['free']);
										  $app['user.manager']->update($app['user']);
										  $customerId = $customer->id;
								}

								$serverCount = 26;
								try {
										  $customer = \Stripe_Customer::retrieve($customerId);
										  \Stripe_InvoiceItem::create([
													 "customer" => $customerId,
													 "amount" => $serverCount * $app['msiof.stripe']['pricePerServer']['USD'],
													 "currency" => "usd",
													 "description" => "{$serverCount} server fires"
										  ]);

										  $subscription = $customer->subscriptions->create([
													 "plan" => $app['msiof.stripe']['plans']['paid'],
													 "card" => $request->get('stripeToken')
										  ]);

										  $result = $subscription->save();
								} catch (Stripe_CardError $e) {
										  $body = $e->getJsonBody();
										  $err  = $body['error'];
										  $app['session']->getFlashBag()->set('alert', 'Something was wrong with your card [' . $err['message'] . '].  Please try again.');
								} catch (Stripe_Error $e) {
										  $app['session']->getFlashBag()->set('alert', 'Something went wrong.  Please get in touch at somethingwentwrong@myserverisonfire.com');

										  return $app->redirect($app->url('dashboard'));
								} catch (Exception $e) {
										  $app['session']->getFlashBag()->set('alert', 'Something went wrong.  Please get in touch at somethingwentwrong@myserverisonfire.com');

										  return $app->redirect($app->url('dashboard'));
								}

								$subscriptionId = $result->id;
								$app['user']->setCustomField('stripe_current_plan', $app['msiof.stripe']['plans']['paid']);
								$app['user']->setCustomField('stripe_subscription_id_paid', $subscriptionId);
								$app['user']->setCustomField('stripe_current_period_start', $result->current_period_start);
								$app['user']->setCustomField('stripe_current_period_end', $result->current_period_end);
								$app['user.manager']->update($app['user']);

								$app['session']->getFlashBag()->set('alert-success', 'You are now upgraded! Congrats!');

								return $app->redirect($app->url('msiof-stripe-account'));
					 })->bind('msiof-stripe-pay-post');

					 $controllers->post('/webhook', function(Application $app, Request $request) {
								$serverCount = 26;

								if (strpos($request->headers->get('Content-Type'), 'application/json') === 0) {
										  $data = json_decode($request->getContent(), true);
										  if ($data['data']['object']['lines']['data'][0]['plan']['id'] == $app['msiof.stripe']['plans']['free']) {
													 return "Ignoring because it's the free plan";
										  }

										  if ($data['type'] == 'invoice.created') {
													 $customerId = $data['data']['object']['customer'];
													 $invoiceId = $data['data']['object']['id'];
													 \Stripe_InvoiceItem::create([
																"customer" => $customerId,
																"amount" => $serverCount * $app['msiof.stripe']['pricePerServer']['USD'],
																"currency" => "usd",
																"description" => "{$serverCount} server fires"
													 ]);
										  } elseif ($data['type'] == 'invoice.payment_succeeded') {
													 //@TODO: Update db to set current_period_start and current_period_end?
										  } elseif ($data['type'] == 'invoice.payment_failed') {
													 //@TODO: Email to say payment failed
										  } elseif ($data['type'] == 'customer.subscription.deleted') {
													 //@TODO: They should only be able to cancel paid subscription, so set stripe_current_plan to 'free', and remove paid subscription id
													 //@TODO: Get user instance - findOneBy?
													 //@TODO: Remove stripe_subscription_awaiting_cancellation too
										  }
								}

								return "{$customerId} / {$invoiceId}";
					 });


					 return $controllers;
		  }
}
