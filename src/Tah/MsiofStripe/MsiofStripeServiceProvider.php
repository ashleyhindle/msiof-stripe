<?php
namespace Tah\MsiofStripe;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use SimpleUser\UserEvents;
use SimpleUser\UserEvent;

/**
 * Class: MsiofStripeServiceProvider
 *
 * @see Silex\ServiceProviderInterface
 */
class MsiofStripeServiceProvider implements ServiceProviderInterface
{
		  /**
		  * register
		  *
		  * @param Application $app
		  */
		  public function register(Application $app)
		  {
					 $app['msiof.stripe'] = [
								'keys' => []
					 ];
		  }

		  /**
		  * boot
		  *
		  * @param Application $app
		  */
		  public function boot(Application $app)
		  {
					 if (empty($app['msiof.stripe']['keys']['secret'])) {
								throw new \RuntimeException('Stripe key not set msiof.stripe[keys][secret].  Cannot continue.');
					 }

					 if (empty($app['msiof.stripe']['keys']['publishable'])) {
								throw new \RuntimeException('Stripe key not set msiof.stripe[keys][publishable].  Cannot continue.');
					 }
					 \Stripe::setApiKey($app['msiof.stripe']['keys']['secret']);

					 $app['dispatcher']->addListener(UserEvents::AFTER_INSERT, function(UserEvent $event) use ($app) {
								$user = $event->getUser();
								$customer = \Stripe_Customer::create([
										  'email' => $user->getEmail(),
										  'metadata' => [
													 'userid' => $user->getId()
										  ]
								]);

								$subscription = $customer->subscriptions->create([
										  "plan" => $app['msiof.stripe']['plans']['free']
								]);

								$user->setCustomField('stripe_customer_id', $customer->id);
								$user->setCustomField('stripe_subscription_id', $subscription->id);
								$user->setCustomField('stripe_current_plan', $app['msiof.stripe']['plans']['free']);
								$app['user.manager']->update($user);
					 });
		  }
}
