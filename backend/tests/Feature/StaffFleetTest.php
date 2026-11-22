<?php

namespace Tests\Feature;

use App\Models\ReservationStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffFleetTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_staff_fleet_and_calendar_endpoints_require_staff_access(): void
    {
        $vehicle = Vehicle::factory()->create();
        $url = "/api/staff/vehicles/{$vehicle->id}";
        $requests = [
            ['GET', '/api/staff/vehicles'],
            ['POST', '/api/staff/vehicles'],
            ['GET', $url],
            ['PUT', $url],
            ['PUT', "$url/publication"],
            ['GET', "$url/availability"],
            ['POST', "$url/availability/blocks"],
            ['DELETE', "$url/availability/blocks/1"],
        ];

        foreach ($requests as [$method, $path]) {
            $this->json($method, $path)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());
        foreach ($requests as [$method, $path]) {
            $this->json($method, $path)->assertForbidden();
        }
    }

    public function test_staff_can_create_edit_publish_and_withdraw_an_individual_vehicle(): void
    {
        $this->signInStaff();
        $vehicle = ['display_label' => 'City 01', 'make' => 'Honda', 'model' => 'City'];
        $created = $this->postJson('/api/staff/vehicles', $vehicle + ['is_published' => true])
            ->assertCreated()->assertJsonPath('data.is_published', false);
        $id = $created->json('data.id');
        $url = "/api/staff/vehicles/$id";
        $search = $this->searchUrl($this->futureDay());

        $this->postJson('/api/staff/vehicles', $vehicle)->assertUnprocessable()->assertJsonValidationErrors('display_label');
        $this->getJson('/api/staff/vehicles')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson($url)->assertOk()->assertJsonPath('data.display_label', 'City 01');
        $this->getJson($search)->assertExactJson(['data' => []]);

        $updated = [...$vehicle, 'display_label' => 'City 02'];
        $this->putJson($url, $updated + ['is_published' => true])->assertOk()
            ->assertJsonPath('data.is_published', false)->assertJsonPath('data.id', $id);
        $this->putJson($url, $updated)->assertOk();
        $second = $this->postJson('/api/staff/vehicles', $vehicle)->assertCreated()->json('data.id');
        $this->assertNotSame($id, $second);
        $this->putJson($url, $vehicle)->assertUnprocessable()->assertJsonValidationErrors('display_label');
        $this->putJson("$url/publication", [])->assertUnprocessable()->assertJsonValidationErrors('is_published');
        $this->putJson("$url/publication", ['is_published' => true])->assertOk();
        $this->getJson($search)->assertOk()->assertExactJson(['data' => [$updated + ['id' => $id]]]);
        $this->putJson("$url/publication", ['is_published' => false])->assertOk();
        $this->getJson($search)->assertExactJson(['data' => []]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.is_published', false);
    }

    public function test_staff_can_block_and_release_dates_for_the_selected_vehicle(): void
    {
        $this->signInStaff();
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $other = Vehicle::factory()->create(['is_published' => true]);
        $url = "/api/staff/vehicles/{$vehicle->id}/availability";
        $date = $this->futureDay()->toDateString();
        $search = $this->searchUrl($this->futureDay());

        $this->getJson($search)->assertOk()->assertJsonCount(2, 'data');
        $block = $this->postJson("$url/blocks", ['starts_on' => $date, 'ends_on' => $date])->assertCreated()->json('data');
        $this->postJson("$url/blocks", ['starts_on' => $date, 'ends_on' => $date])->assertOk()->assertJsonPath('data', $block);
        $this->assertDatabaseCount('availability_blocks', 1);
        $this->getJson($url)->assertOk()->assertJsonPath('blocks.0', $block);
        $this->getJson($search)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->deleteJson("/api/staff/vehicles/{$other->id}/availability/blocks/{$block['id']}")->assertNotFound();
        $this->deleteJson("$url/blocks/{$block['id']}")->assertNoContent();
        $this->getJson($search)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'blocks');
    }

    public function test_confirmed_dates_include_the_return_day_and_cancelled_dates_can_be_blocked(): void
    {
        $this->signInStaff();
        $vehicle = Vehicle::factory()->create();
        $pickup = $this->futureDay()->toDateString();
        $return = $this->futureDay()->addDay()->toDateString();
        $next = $this->futureDay()->addDays(2)->toDateString();
        $vehicle->reservations()->create([
            'pickup_date' => $pickup, 'return_date' => $return, 'status' => ReservationStatus::Confirmed,
        ]);
        $vehicle->reservations()->create([
            'pickup_date' => $next, 'return_date' => $next, 'status' => ReservationStatus::Cancelled,
        ]);
        $url = "/api/staff/vehicles/{$vehicle->id}/availability";

        $this->getJson($url)->assertOk()->assertJsonPath('bookings', [['starts_on' => $pickup, 'ends_on' => $return]]);
        $this->postJson("$url/blocks", ['starts_on' => $next, 'ends_on' => $return])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->postJson("$url/blocks", ['starts_on' => '2027-02-30', 'ends_on' => $return])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->postJson("$url/blocks", ['starts_on' => $return, 'ends_on' => $next])
            ->assertConflict()->assertJsonPath('code', 'confirmed_booking_overlap');
        $this->assertDatabaseCount('availability_blocks', 0);
        $this->postJson("$url/blocks", ['starts_on' => $next, 'ends_on' => $next])->assertCreated();
    }

    private function signInStaff(): void
    {
        $this->actingAs(User::factory()->create(['is_staff' => true]));
    }

    private function futureDay(): Carbon
    {
        return today(config('rentgrid.business_timezone'))->addWeek();
    }

    private function searchUrl(Carbon $date): string
    {
        $day = $date->toDateString();

        return "/api/vehicles/availability?pickup_date=$day&return_date=$day";
    }
}
