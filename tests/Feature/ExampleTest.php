<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // El root lleva al ERP, que vive en Vue bajo /app. Antes apuntaba a
        // /admin, de cuando el panel de Filament era la puerta de entrada;
        // hoy /admin quedó reservado para Dropi.
        $response->assertRedirect('/app');
    }
}
