<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_home_redirects_to_login_when_guest(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_login_and_open_home(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@biolab.local',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/');
        $this->assertNotNull(session('biolab_user'));

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Control de resultados');
    }
}
