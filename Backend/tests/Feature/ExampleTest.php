<?php

namespace Tests\Feature;

use App\Http\Middleware\Services\RouteSensitivityService;
use Mockery\MockInterface;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_unverified_visitors_are_redirected_to_the_security_check(): void
    {
        $this->mock(RouteSensitivityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn([
                    'route_access' => 0,
                    'cookie_score' => 50,
                    'ip_reputation_score' => 50,
                    'human_verification_score' => 0,
                    'human_verification_source' => 'missing',
                    'final_score' => 0.0,
                ]);
        });

        $response = $this->get('/');

        $response->assertRedirect(route('security.check', [
            'return' => '/',
        ]));
    }
}
