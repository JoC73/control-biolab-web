<?php

namespace Tests\Feature;

use App\Services\CashStore;
use App\Services\CatalogStore;
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

    public function test_laboratory_profile_does_not_show_or_access_billing_module(): void
    {
        $this->actingAsBiolab('laboratorio')
            ->get('/')
            ->assertOk()
            ->assertDontSee('Registrar cobro');

        $this->actingAsBiolab('laboratorio')
            ->get('/ordenes/nueva')
            ->assertForbidden();
    }

    public function test_non_laboratory_profiles_cannot_access_laboratory_queue(): void
    {
        $this->actingAsBiolab('recepcion')
            ->get('/laboratorio')
            ->assertForbidden();

        $this->actingAsBiolab('caja')
            ->get('/laboratorio')
            ->assertForbidden();
    }

    public function test_cashier_profile_can_update_prices_but_not_manage_templates_or_references(): void
    {
        $this->actingAsBiolab('caja')
            ->get('/catalogos')
            ->assertOk()
            ->assertSee('Precio base por examen')
            ->assertSee('Guardar')
            ->assertDontSee('Crear examen');

        $this->actingAsBiolab('caja')
            ->post('/catalogos/precios', ['slug' => 'hematologia', 'price' => 80])
            ->assertRedirect();

        $this->assertEqualsWithDelta(80.0, app(CatalogStore::class)->prices()['hematologia'], 0.001);

        $this->actingAsBiolab('caja')
            ->get('/catalogos/examenes/nuevo')
            ->assertForbidden();

        $this->actingAsBiolab('caja')
            ->post('/catalogos/referencias', ['name' => 'CLINICA QA'])
            ->assertForbidden();
    }

    public function test_cash_monthly_summary_excludes_voided_and_other_months(): void
    {
        $this->actingAsBiolab('caja');

        $cash = app(CashStore::class);
        $cash->create(['type' => 'income', 'date' => '2026-07-02', 'amount' => 100, 'method' => 'efectivo', 'description' => 'Ingreso julio']);
        $cash->create(['type' => 'expense', 'date' => '2026-07-03', 'amount' => 25, 'method' => 'efectivo', 'description' => 'Egreso julio']);
        $voided = $cash->create(['type' => 'income', 'date' => '2026-07-04', 'amount' => 50, 'method' => 'efectivo', 'description' => 'Ingreso anulado']);
        $cash->void($voided['id'], 'Prueba QA');
        $cash->create(['type' => 'income', 'date' => '2026-08-01', 'amount' => 500, 'method' => 'efectivo', 'description' => 'Ingreso agosto']);

        $summary = $cash->monthlyTotals('2026-07');

        $this->assertEqualsWithDelta(100.0, $summary['income'], 0.001);
        $this->assertEqualsWithDelta(25.0, $summary['expense'], 0.001);
        $this->assertEqualsWithDelta(75.0, $summary['balance'], 0.001);
        $this->assertSame(1, $summary['income_count']);
        $this->assertSame(1, $summary['expense_count']);
        $this->assertSame(1, $summary['voided_count']);

        $this->actingAsBiolab('caja')
            ->get('/caja?month=2026-07')
            ->assertOk()
            ->assertSee('Resumen mensual')
            ->assertSee('Q 100.00')
            ->assertSee('Q 25.00')
            ->assertSee('Q 75.00');
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

    public function test_multi_exam_order_uses_backend_prices_and_one_cash_movement(): void
    {
        $this->seedExamPrices();

        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload([
                'exam_slugs' => ['hematologia', 'orina', 'heces'],
                'exam_prices' => ['hematologia' => 75, 'orina' => 50, 'heces' => 40],
                'price' => 1,
                'paid_amount' => 100,
            ]))
            ->assertRedirect();

        $order = app(OrderStore::class)->all()->first();

        $this->assertCount(3, $order['exam_items']);
        $this->assertEqualsWithDelta(165.0, $order['price'], 0.001);
        $this->assertEqualsWithDelta(165.0, $order['total'], 0.001);
        $this->assertEqualsWithDelta(100.0, $order['paid_amount'], 0.001);
        $this->assertSame('partial', $order['payment_status']);
        $this->assertCount(1, app(CashStore::class)->all());
    }

    public function test_create_order_view_keeps_exam_prices_separate_from_subtotal_field(): void
    {
        $this->seedExamPrices();

        $this->actingAsBiolab('recepcion')
            ->get('/ordenes/nueva')
            ->assertOk()
            ->assertSee('data-subtotal', false)
            ->assertSee('data-exam-price="75"', false)
            ->assertDontSee('data-price', false);
    }

    public function test_create_order_view_uses_touch_friendly_referrer_picker(): void
    {
        $this->actingAsBiolab('recepcion')
            ->get('/ordenes/nueva')
            ->assertOk()
            ->assertSee('data-referrer-value', false)
            ->assertSee('data-referrer-selected', false)
            ->assertSee('data-referrer-search-toggle', false)
            ->assertSee('data-referrer-search', false)
            ->assertSee('data-referrer-picker', false)
            ->assertSee('data-referrer-option', false)
            ->assertDontSee('<datalist', false);
    }

    public function test_login_has_password_visibility_toggle(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-password-input', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_multi_exam_order_rejects_duplicates_and_manipulated_prices(): void
    {
        $this->seedExamPrices();

        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload([
                'exam_slugs' => ['hematologia', 'hematologia'],
                'exam_prices' => ['hematologia' => 75],
            ]))
            ->assertStatus(422);

        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload([
                'exam_slugs' => ['hematologia', 'orina'],
                'exam_prices' => ['hematologia' => 1, 'orina' => 50],
            ]))
            ->assertStatus(422);

        $this->assertCount(0, app(OrderStore::class)->all());
    }

    public function test_multi_exam_results_are_independent_and_delivery_requires_all_ready(): void
    {
        $this->seedExamPrices();

        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload([
                'exam_slugs' => ['hematologia', 'orina'],
                'exam_prices' => ['hematologia' => 75, 'orina' => 50],
                'paid_amount' => 125,
            ]))
            ->assertRedirect();

        $order = app(OrderStore::class)->all()->first();

        $this->actingAsBiolab('laboratorio')
            ->post("/ordenes/{$order['id']}/resultados", [
                'exam_index' => 0,
                'tests' => [['name' => 'Hemoglobina', 'unit' => 'g/dL', 'reference' => '12.0-17.0']],
                'results' => ['13.5'],
                'status' => 'ready',
            ])
            ->assertRedirect();

        $partial = app(OrderStore::class)->find($order['id']);
        $this->assertSame('ready', $partial['exam_items'][0]['status']);
        $this->assertSame('pending', $partial['exam_items'][1]['status']);
        $this->assertSame('pending_results', $partial['status']);

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertSessionHasErrors('delivery');

        $this->actingAsBiolab('laboratorio')
            ->post("/ordenes/{$order['id']}/resultados", [
                'exam_index' => 1,
                'tests' => [['name' => 'Color', 'unit' => '', 'reference' => 'Amarillo']],
                'results' => ['Amarillo'],
                'status' => 'ready',
            ])
            ->assertRedirect();

        $ready = app(OrderStore::class)->find($order['id']);
        $this->assertSame('ready', $ready['status']);

        $this->actingAsBiolab('recepcion')
            ->post("/ordenes/{$order['id']}/entregar")
            ->assertRedirect();

        $this->actingAsBiolab('recepcion')
            ->get("/ordenes/{$order['id']}/pdf/0")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAsBiolab('recepcion')
            ->get("/ordenes/{$order['id']}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame('delivered', app(OrderStore::class)->find($order['id'])['status']);
    }

    private function createOrderAsReception(array $overrides = []): void
    {
        $this->actingAsBiolab('recepcion')
            ->post('/ordenes', $this->orderPayload($overrides))
            ->assertRedirect();
    }

    private function seedExamPrices(): void
    {
        app(CatalogStore::class)->savePrice('hematologia', 75);
        app(CatalogStore::class)->savePrice('orina', 50);
        app(CatalogStore::class)->savePrice('heces', 40);
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
