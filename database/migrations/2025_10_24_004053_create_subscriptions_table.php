<?php

use App\Enums\BillingCycle;
use App\Enums\PaymentProvider;
use App\Enums\PlanType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->enum('plan_type', PlanType::values());
            $table->enum('billing_cycle', BillingCycle::values());
            $table->enum('payment_provider', PaymentProvider::values());
            $table->enum('status', SubscriptionStatus::values());

            $table->string('provider_subscription_id')->nullable();

            $table->integer('max_users')->nullable();
            $table->integer('used_users')->default(0);
            $table->integer('max_contacts')->nullable();
            $table->integer('used_contacts')->default(0);
            $table->integer('max_whatsapp_numbers')->nullable();
            $table->integer('used_whatsapp_numbers')->default(0);
            $table->integer('extra_users_purchased')->default(0);

            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('grace_period_ends_at')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
            $table->index('provider_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
