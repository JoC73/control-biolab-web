<?php

namespace Tests\Feature;

use App\Services\CashStore;
use App\Services\OrderStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BiolabCriticalFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/orders'));
        File::deleteDirectory(storage_path('app/cash'));
        File::deleteDirectory(storage_path('app/lab-results'));
        File::deleteDirectory(storage_path('app/audit'));
        File::deleteDirectory(storage_path('app/catalog'));
    }

    public function test_backend_permission_blocks_unauthorized_order_creation(): void
    {
        $this->actingAsBiolab('laboratorio')
            ->post('/ordenes', $this->orderPayload())
            ->assertForbidden();
    }

    public function test_initial_payment_cannot_exceed_net_total(): void
    {
        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload(['paid_amount' => 120]))
            ->assertSessionHasErrors('paid_amount');

        $this->assertCount(0, app(OrderStore::class)->all());
        $this->assertCount(0, app(CashStore::class)->all());
    }

    public function test_payment_must_match_pending_balance_and_updates_cash_once(): void
    {
        $this->createOrderAsReception(['price' => 75, 'paid_amount' => 60]);
        $order = app(OrderStore::class)->all()->first();

        $this->actingAsBiolab('caja')
            ->post("/ordenes/{$order['id']}/pago", ['amount' => 20, 'method' => 'efectivo'])
            ->assertSessionHasErrors('amount');

        $this->assertEqualsWithDelta(60.0, app(OrderStore::class)->find($order['id'])['paid_amount'], 0.001);
        $this->assertCount(1, app(CashStore::class)->all());

        $this->actingAsBiolab('caja')
            ->post("/ordenes/{$order['id']}/pago", ['amount' => 15, 'method' => 'efectivo'])
            ->assertRedirect();

        $paid = app(OrderStore::class)->find($order['id']);
        $this->assertEqualsWithDelta(75.0, $paid['paid_amount'], 0.001);
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertCount(2, app(CashStore::class)->all());
    }

    public function test_delivery_requires_paid_order_and_ready_results(): void
    {
        $this->createOrderAsReception(['price' => 75, 'paid_amount' => 0]);
        $order = app(OrderStore::class)->all()->first();

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertSessionHasErrors('delivery');

        app(OrderStore::class)->addPayment($order['id'], 75, 'efectivo');

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertSessionHasErrors('delivery');

        $this->actingAsBiolab('laboratorio')
            ->post("/ordenes/{$order['id']}/resultados", [
                'tests' => [['name' => 'Hemoglobina', 'unit' => 'g/dL', 'reference' => '12.0-17.0']],
                'results' => ['13.5'],
                'status' => 'ready',
            ])
            ->assertRedirect();

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertRedirect();

        $delivered = app(OrderStore::class)->find($order['id']);
        $this->assertSame('delivered', $delivered['status']);
        $this->assertNotNull($delivered['delivered_at']);

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertSessionHasErrors('delivery');
    }

    public function test_cancelled_paid_order_creates_only_one_reversal(): void
    {
        $this->createOrderAsReception(['price' => 75, 'paid_amount' => 75]);
        $order = app(OrderStore::class)->all()->first();

        $this->actingAsBiolab('caja')
            ->post("/ordenes/{$order['id']}/anular", ['reason' => 'Paciente no continua'])
            ->assertRedirect();

        $this->actingAsBiolab('caja')
            ->post("/ordenes/{$order['id']}/anular", ['reason' => 'Segundo intento'])
            ->assertSessionHasErrors('reason');

        $reversals = app(CashStore::class)->all()
            ->where('order_id', $order['id'])
            ->where('source', 'order_cancel_reversal');

        $this->assertCount(1, $reversals);
    }

    public function test_whatsapp_and_pdf_are_blocked_until_paid_and_ready(): void
    {
        $this->createOrderAsReception(['price' => 75, 'paid_amount' => 0, 'phone' => '55555555']);
        $order = app(OrderStore::class)->all()->first();

        $this->actingAsBiolab('recepcion')
            ->get("/ordenes/{$order['id']}")
            ->assertDontSee('https://wa.me', false);

        $this->actingAsBiolab('recepcion')
            ->get("/ordenes/{$order['id']}/pdf")
            ->assertForbidden();
    }

    private function createOrderAsReception(array $overrides = []): void
    {
        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload($overrides))
            ->assertRedirect();
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'Paciente QA Ficticio',
            'age' => '30',
            'phone' => '55555555',
            'category_slug' => 'hematologia',
            'date' => '2026-07-28',
            'referrer' => 'CENTRO DE SALUD',
            'price' => 75,
            'discount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'efectivo',
            'payment_timing' => 'before',
        ], $overrides);
    }

    private function actingAsBiolab(string $role): self
    {
        return $this->withSession([
            'biolab_user' => [
                'name' => ucfirst($role),
                'email' => $role.'@biolab.local',
                'role' => $role,
            ],
        ]);
    }
}
