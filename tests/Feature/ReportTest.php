<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_open_the_sales_report_page(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->completed()->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($seller)
            ->get(route('seller.reports.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_seller_can_download_excel_and_pdf_reports(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();
        $order = Order::factory()->for($store)->completed()->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($seller)
            ->get(route('seller.reports.excel'))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($seller)
            ->get(route('seller.reports.pdf'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_seller_reports_only_include_their_own_store(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();

        $ownOrder = Order::factory()->for($store)->completed()->create();
        $otherOrder = Order::factory()->completed()->create();

        $this->actingAs($seller)
            ->get(route('seller.reports.index'))
            ->assertOk()
            ->assertSee($ownOrder->order_number)
            ->assertDontSee($otherOrder->order_number);
    }

    public function test_seller_report_filters_are_applied(): void
    {
        $seller = User::factory()->seller()->create();
        $store = Store::factory()->for($seller, 'owner')->create();

        $paidOrder = Order::factory()->for($store)->paid()->create();
        $pendingOrder = Order::factory()->for($store)->create();

        $this->actingAs($seller)
            ->get(route('seller.reports.index', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
                'status' => 'dibayar',
            ]))
            ->assertOk()
            ->assertSee($paidOrder->order_number)
            ->assertDontSee($pendingOrder->order_number);
    }

    public function test_invalid_report_date_range_is_rejected(): void
    {
        $seller = User::factory()->seller()->create();
        Store::factory()->for($seller, 'owner')->create();

        $this->actingAs($seller)
            ->get(route('seller.reports.index', [
                'from' => '2026-10-20',
                'to' => '2026-10-01',
            ]))
            ->assertSessionHasErrors('from');
    }

    public function test_admin_can_open_and_download_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['name' => 'Toko Laporan']);
        $order = Order::factory()->for($store)->completed()->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($admin)
            ->get(route('admin.reports.excel', ['store_id' => $store->id]))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($admin)
            ->get(route('admin.reports.pdf', ['store_id' => $store->id]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_buyer_cannot_access_report_pages(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)->get(route('seller.reports.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('seller.reports.excel'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.reports.pdf'))->assertForbidden();
    }
}
