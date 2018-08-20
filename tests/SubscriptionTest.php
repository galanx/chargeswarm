<?php

namespace Rennokki\Chargeswarm\Test;

use Carbon\Carbon;
use Chargebee_Invoice as ChargebeeInvoice;
use Chargebee_Download as ChargebeeDownload;
use Chargebee_InvalidRequestException as ChargebeeInvalidRequestException;

class SubscriptionTest extends TestCase
{
    public function testCreateCancelResumeCancelImmediately()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_hustle')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->create('tok_visa');

        $this->assertTrue($user->subscribed('cbdemo_hustle'));
        $this->assertFalse($user->subscribed('1'));
        $this->assertEquals($user->activeSubscriptions()->count(), 1);

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();
        $this->assertEquals($subscription->plan_id, 'cbdemo_hustle');

        $this->assertNull($subscription->trial_starts_at);
        $this->assertNull($subscription->trial_ends_at);

        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->valid());

        $subscription->cancel();
        $subscription->refresh();
        $this->assertFalse($subscription->cancelled());
        $this->assertNull($subscription->ends_at);

        try {
            $subscription->resume();
        } catch (ChargebeeInvalidRequestException $e) {
            $this->assertTrue(true);
        }

        $subscription->cancelImmediately();
        $subscription->refresh();
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onTrial());

        $subscription->reactivate();
        $subscription->refresh();
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());
    }

    public function testCreateCancelResumeCancelImmediatelyWithTrialPlan()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_grow')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->create('tok_visa');

        $this->assertTrue($user->subscribed('cbdemo_grow'));
        $this->assertFalse($user->subscribed('1'));
        $this->assertEquals($user->activeSubscriptions()->count(), 1);

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();
        $this->assertEquals($subscription->plan_id, 'cbdemo_grow');

        $this->assertTrue($subscription->onTrial());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->valid());

        $subscription->cancel();
        $subscription->refresh();
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue(Carbon::parse($subscription->ends_at)->equalTo(Carbon::parse($subscription->trial_ends_at)));

        $subscription->resume();
        $subscription->refresh();
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());

        $subscription->cancelImmediately();
        $subscription->refresh();
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onTrial());

        $subscription->reactivate();
        $subscription->refresh();
        $this->assertFalse($subscription->cancelled());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());
    }

    public function testSwapToTrial()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_hustle')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->create('tok_visa');

        $this->assertTrue($user->subscribed('cbdemo_hustle'));
        $this->assertFalse($user->subscribed('1'));
        $this->assertEquals($user->activeSubscriptions()->count(), 1);

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();
        $this->assertEquals($subscription->plan_id, 'cbdemo_hustle');

        $subscription->swap('cbdemo_grow');
        $subscription->refresh();

        $this->assertEquals($subscription->plan_id, 'cbdemo_grow');
    }

    public function testSwapToPaid()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_grow')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->create('tok_visa');

        $this->assertTrue($user->subscribed('cbdemo_grow'));
        $this->assertFalse($user->subscribed('1'));
        $this->assertEquals($user->activeSubscriptions()->count(), 1);

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();
        $this->assertEquals($subscription->plan_id, 'cbdemo_grow');

        $subscription->swap('cbdemo_hustle');
        $subscription->refresh();

        $this->assertEquals($subscription->plan_id, 'cbdemo_hustle');
    }

    public function testCoupon()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_hustle')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->withCoupon('cbdemo_earlybird')
             ->create('tok_visa');

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();

        $this->assertNotNull($subscription->id);
    }

    public function testAddon()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_hustle')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->withAddons('cbdemo_additionaluser')
             ->create('tok_visa');

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();

        $this->assertInstanceOf(Carbon::class, $subscription->next_billing_at);
        $this->assertNotNull($subscription->id);
    }

    public function testInvoice()
    {
        $user = factory(\Rennokki\Chargeswarm\Test\Models\User::class)->create();

        $user->subscription('cbdemo_hustle')
             ->withCustomerData('a@b.com', 'First Name', 'Last Name')
             ->withBilling('a@c.com', 'First', 'Last', 'Address', 'City', 'State', null, 'RO', 'Company')
             ->billingCycles(12)
             ->withAddons('cbdemo_additionaluser')
             ->create('tok_visa');

        $activeSubscriptions = $user->activeSubscriptions();
        $subscription = $activeSubscriptions->first();
        $invoices = $subscription->invoices();

        $this->assertTrue(is_array($invoices));
        $this->assertEquals(count($invoices), 1);

        $invoice = $invoices[0];

        $this->assertInstanceOf(ChargebeeInvoice::class, $user->invoice($invoice->id));
        $this->assertInstanceOf(ChargebeeDownload::class, $user->downloadInvoice($invoice->id));
    }
}
