<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_registration(): void
    {

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'Codigoasdasd.00',
            'password_confirmation' => 'Codigoasdasd.00',
        ];

        $response = $this->postJson('/api/register', $userData);

        // Verificar que la respuesta es correcta
        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully. Please check your email for verification.',
            ]);

        // Verificar que el usuario se creó en la base de datos
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_user_registration_with_invalid_data(): void
    {
        // Datos de prueba inválidos (falta el campo "last_name")
        $userData = [
            'first_name' => 'John',
            'email' => 'john.doe@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        // Hacer una solicitud POST al endpoint de registro
        $response = $this->postJson('/api/register', $userData);

        // Verificar que la respuesta es un error 422 (validación fallida)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }
}
