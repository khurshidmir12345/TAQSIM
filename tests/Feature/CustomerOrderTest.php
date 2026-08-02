<?php

namespace Tests\Feature;

use App\Enums\CustomerOrderStatus;
use App\Enums\SellerSubStatus;
use App\Enums\ShopPermission;
use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\SellerSub;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private string $uzsId;

    private BreadCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Test Shop',
            'slug' => 'test-'.Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $this->owner->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Owner,
        ]);

        $this->category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oq non',
            'selling_price' => 5000,
            'currency_id' => $this->uzsId,
        ]);
    }

    public function test_can_create_order_with_inline_customer_and_advance_payment(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer' => [
                    'name' => 'Ali Valiyev',
                    'phone' => '+998901234567',
                ],
                'delivery_date' => '2026-07-15',
                'delivery_time' => '10:30',
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 200,
                    ],
                ],
                'advance_amount' => 500000,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.customer_order.status', CustomerOrderStatus::Active->value)
            ->assertJsonPath('data.customer_order.total_amount', '1000000.00')
            ->assertJsonPath('data.customer_order.paid_amount', '500000.00')
            ->assertJsonPath('data.customer_order.remaining_amount', '500000.00')
            ->assertJsonPath('data.customer_order.customer.name', 'Ali Valiyev')
            ->assertJsonCount(1, 'data.customer_order.items')
            ->assertJsonCount(1, 'data.customer_order.payments');

        $this->assertDatabaseHas('customers', [
            'shop_id' => $this->shop->id,
            'name' => 'Ali Valiyev',
        ]);
    }

    public function test_can_filter_orders_by_date_and_status(): void
    {
        $customer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mijoz',
            'created_by' => $this->owner->id,
        ]);

        CustomerOrder::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'status' => CustomerOrderStatus::Active,
            'delivery_date' => '2026-07-15',
            'total_amount' => 100000,
            'created_by' => $this->owner->id,
        ]);

        CustomerOrder::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'status' => CustomerOrderStatus::Delivered,
            'delivery_date' => '2026-07-16',
            'total_amount' => 200000,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders?date=2026-07-15&status=active");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_payment_exceeding_remaining_is_rejected(): void
    {
        $order = $this->createOrderWithTotal(100000, 50000);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}/payments", [
                'amount' => 60000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_payment_on_cancelled_order_is_rejected(): void
    {
        $order = $this->createOrderWithTotal(100000, 0, CustomerOrderStatus::Cancelled);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}/payments", [
                'amount' => 10000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_payment_on_delivered_order_is_rejected(): void
    {
        $order = $this->createOrderWithTotal(100000, 50000, CustomerOrderStatus::Delivered);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}/payments", [
                'amount' => 10000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_deliver_order_with_final_payment(): void
    {
        $order = $this->createOrderWithTotal(100000, 50000);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}/deliver", [
                'payment_amount' => 50000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.customer_order.status', 'delivered')
            ->assertJsonPath('data.customer_order.paid_amount', '100000.00')
            ->assertJsonPath('data.customer_order.remaining_amount', '0.00');

        $this->assertNotNull($response->json('data.customer_order.delivered_at'));
    }

    public function test_edit_rejected_when_total_below_paid_amount(): void
    {
        $order = $this->createOrderWithTotal(100000, 80000);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}", [
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 10,
                        'unit_price' => 5000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_cancel_active_order(): void
    {
        $order = $this->createOrderWithTotal(100000, 0);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.customer_order.status', 'cancelled');
    }

    public function test_seller_without_manage_orders_permission_is_denied_on_write(): void
    {
        $seller = $this->createSellerWithoutOrdersPermission();

        $response = $this->actingAs($seller)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer' => ['name' => 'Test'],
                'delivery_date' => '2026-07-15',
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_permission');
    }

    public function test_seller_without_manage_orders_permission_is_denied_on_read(): void
    {
        $seller = $this->createSellerWithoutOrdersPermission();

        $response = $this->actingAs($seller)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders");

        $response->assertStatus(403)
            ->assertJsonPath('code', 'forbidden_permission');
    }

    public function test_delete_order_with_payments_is_rejected(): void
    {
        $order = $this->createOrderWithTotal(100000, 50000);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('customer_orders', ['id' => $order->id]);
    }

    public function test_can_delete_payment_free_active_order(): void
    {
        $order = $this->createOrderWithTotal(100000, 0);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/customer-orders/{$order->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('customer_orders', ['id' => $order->id]);
    }

    public function test_cross_shop_access_is_denied(): void
    {
        $otherShop = Shop::create([
            'name' => 'Other',
            'slug' => 'other-'.Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $this->owner->shops()->attach($otherShop->id, [
            'user_type' => ShopUserType::Owner,
        ]);

        $order = $this->createOrderWithTotal(100000, 0);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$otherShop->id}/customer-orders/{$order->id}");

        $response->assertStatus(404);
    }

    public function test_cross_shop_bread_category_id_is_rejected_at_validation(): void
    {
        $otherShop = Shop::create([
            'name' => 'Other',
            'slug' => 'other-'.Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $otherCategory = BreadCategory::create([
            'shop_id' => $otherShop->id,
            'name' => 'Boshqa non',
            'selling_price' => 6000,
            'currency_id' => $this->uzsId,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer' => ['name' => 'Test'],
                'delivery_date' => '2026-07-15',
                'items' => [
                    [
                        'bread_category_id' => $otherCategory->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.bread_category_id']);
    }

    public function test_can_list_search_and_paginate_customers(): void
    {
        Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Ali Valiyev',
            'phone' => '+998901111111',
            'created_by' => $this->owner->id,
        ]);

        Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Bobur Karimov',
            'phone' => '+998902222222',
            'created_by' => $this->owner->id,
        ]);

        $list = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customers");

        $list->assertOk()
            ->assertJsonCount(2, 'data.customers');

        $search = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customers?search=Ali");

        $search->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.customers.0.name', 'Ali Valiyev');

        $paginated = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customers?paginate=1&per_page=1");

        $paginated->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_can_create_update_show_and_delete_customer(): void
    {
        $create = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customers", [
                'name' => 'Sardor',
                'phone' => '+998903333333',
                'note' => 'VIP',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.customer.name', 'Sardor');

        $customerId = $create->json('data.customer.id');

        $show = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customers/{$customerId}");

        $show->assertOk()
            ->assertJsonPath('data.customer.id', $customerId)
            ->assertJsonPath('data.customer.phone', '+998903333333');

        $update = $this->actingAs($this->owner)
            ->putJson("/api/v1/shops/{$this->shop->id}/customers/{$customerId}", [
                'name' => 'Sardor Aliyev',
            ]);

        $update->assertOk()
            ->assertJsonPath('data.customer.name', 'Sardor Aliyev');

        $delete = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/customers/{$customerId}");

        $delete->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    public function test_cannot_delete_customer_with_orders(): void
    {
        $customer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mijoz',
            'created_by' => $this->owner->id,
        ]);

        $this->createOrderWithTotal(100000, 0, CustomerOrderStatus::Active, $customer);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/customers/{$customer->id}");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_cross_shop_customer_access_returns_404(): void
    {
        $otherShop = Shop::create([
            'name' => 'Other',
            'slug' => 'other-'.Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $customer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mijoz',
            'created_by' => $this->owner->id,
        ]);

        $this->owner->shops()->attach($otherShop->id, [
            'user_type' => ShopUserType::Owner,
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$otherShop->id}/customers/{$customer->id}")
            ->assertStatus(404);
    }

    public function test_seller_with_manage_orders_permission_can_access_orders(): void
    {
        $seller = User::factory()->create();
        $this->attachSeller($seller, [ShopPermission::ManageOrders->value]);

        $response = $this->actingAs($seller)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_seller_with_manage_orders_permission_can_create_customer_and_order(): void
    {
        $seller = User::factory()->create();
        $this->attachSeller($seller, [ShopPermission::ManageOrders->value]);

        $customerResponse = $this->actingAs($seller)
            ->postJson("/api/v1/shops/{$this->shop->id}/customers", [
                'name' => 'Seller mijoz',
                'phone' => '+998904444444',
            ]);

        $customerResponse->assertStatus(201)
            ->assertJsonPath('data.customer.name', 'Seller mijoz');

        $customerId = $customerResponse->json('data.customer.id');

        $orderResponse = $this->actingAs($seller)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer_id' => $customerId,
                'delivery_date' => '2026-07-19',
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $orderResponse->assertStatus(201)
            ->assertJsonPath('data.customer_order.customer.id', $customerId)
            ->assertJsonPath('data.customer_order.total_amount', '25000.00');
    }

    public function test_advance_amount_greater_than_total_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer' => ['name' => 'Advance test'],
                'delivery_date' => '2026-07-15',
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 10,
                    ],
                ],
                'advance_amount' => 60000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('customer_orders', [
            'shop_id' => $this->shop->id,
            'total_amount' => 50000,
        ]);
    }

    public function test_can_filter_orders_by_customer_id_and_date_range(): void
    {
        $firstCustomer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Birinchisi',
            'created_by' => $this->owner->id,
        ]);

        $secondCustomer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Ikkinchisi',
            'created_by' => $this->owner->id,
        ]);

        CustomerOrder::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $firstCustomer->id,
            'status' => CustomerOrderStatus::Active,
            'delivery_date' => '2026-07-10',
            'total_amount' => 100000,
            'created_by' => $this->owner->id,
        ]);

        CustomerOrder::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $secondCustomer->id,
            'status' => CustomerOrderStatus::Active,
            'delivery_date' => '2026-07-20',
            'total_amount' => 200000,
            'created_by' => $this->owner->id,
        ]);

        $byCustomer = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders?customer_id={$firstCustomer->id}");

        $byCustomer->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.id', $firstCustomer->id);

        $byRange = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders?from=2026-07-15&to=2026-07-25");

        $byRange->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.id', $secondCustomer->id);
    }

    public function test_can_create_order_with_existing_customer(): void
    {
        $customer = Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mavjud mijoz',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shops/{$this->shop->id}/customer-orders", [
                'customer_id' => $customer->id,
                'delivery_date' => '2026-07-18',
                'items' => [
                    [
                        'bread_category_id' => $this->category->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_order.customer.id', $customer->id)
            ->assertJsonPath('data.customer_order.total_amount', '50000.00');
    }

    private function createSellerWithoutOrdersPermission(): User
    {
        $seller = User::factory()->create();
        $this->attachSeller($seller, [ShopPermission::ManageProduction->value]);

        return $seller;
    }

    /** @param array<int, string> $permissions */
    private function attachSeller(User $seller, array $permissions): void
    {
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
            'permissions' => $permissions,
        ]);

        SellerSub::create([
            'user_id' => $seller->id,
            'shop_id' => $this->shop->id,
            'owner_id' => $this->owner->id,
            'status' => SellerSubStatus::Active,
            'is_paid_seat' => false,
        ]);
    }

    private function createOrderWithTotal(
        float $total,
        float $paid,
        CustomerOrderStatus $status = CustomerOrderStatus::Active,
        ?Customer $customer = null,
    ): CustomerOrder {
        $customer ??= Customer::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mijoz',
            'created_by' => $this->owner->id,
        ]);

        $quantity = (int) ($total / 5000);

        $order = CustomerOrder::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'delivery_date' => '2026-07-15',
            'total_amount' => $total,
            'created_by' => $this->owner->id,
        ]);

        $order->items()->create([
            'bread_category_id' => $this->category->id,
            'quantity' => $quantity,
            'unit_price' => 5000,
            'subtotal' => $total,
        ]);

        if ($paid > 0) {
            $order->payments()->create([
                'shop_id' => $this->shop->id,
                'amount' => $paid,
                'paid_at' => now(),
                'created_by' => $this->owner->id,
            ]);
        }

        return $order;
    }
}
