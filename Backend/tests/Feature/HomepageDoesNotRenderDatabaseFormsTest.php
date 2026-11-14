<?php

namespace Tests\Feature;

use App\Http\Middleware\Services\RouteSensitivityService;
use Mockery\MockInterface;
use Tests\TestCase;

class HomepageDoesNotRenderDatabaseFormsTest extends TestCase
{
    protected function allowProtectedPageRequests(): void
    {
        $this->mock(RouteSensitivityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('evaluate')
                ->andReturn([
                    'route_access' => 1,
                    'cookie_score' => 50,
                    'ip_reputation_score' => 50,
                    'human_verification_score' => 1,
                    'human_verification_source' => 'cookie_cache',
                    'final_score' => 0.625,
                ]);
        });
    }

    public function test_homepage_does_not_render_the_booking_forms_container(): void
    {
        $this->allowProtectedPageRequests();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="booking-forms"', false);
        $response->assertDontSee('/api/forms/', false);
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_booking_page_contains_the_only_database_form_mount(): void
    {
        $this->allowProtectedPageRequests();

        $response = $this->get('/booking.html');

        $response->assertOk();
        $response->assertSee('id="booking-forms"', false);
        $response->assertSee('id="booking-section"', false);
    }
}
