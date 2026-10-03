<?php

namespace Tests\Feature;

use App\Mail\AdminWorkflowNotification;
use App\Models\Admin;
use App\Models\User;
use App\Models\UkPostcode;
use App\Support\TrademarkWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UkPostcodeLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_postcode_lookup_requires_authentication(): void
    {
        $this->getJson('/api/uk-postcodes/TN13%201AA')
            ->assertUnauthorized();
    }

    public function test_it_resolves_and_normalizes_a_uk_postcode(): void
    {
        Http::fake([
            'api.postcodes.io/postcodes/*' => Http::response([
                'status' => 200,
                'result' => [
                    'postcode' => 'TN13 1AA',
                    'country' => 'England',
                    'region' => 'South East',
                    'admin_district' => 'Sevenoaks',
                    'admin_county' => 'Kent',
                ],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/uk-postcodes/tn131aa')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'postcode' => 'TN13 1AA',
                'nation' => 'England',
                'town_city' => 'Sevenoaks',
                'county' => 'Kent',
            ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.postcodes.io/postcodes/TN131AA');
        $this->assertDatabaseHas('uk_postcodes', [
            'postcode_key' => 'TN131AA',
            'postcode' => 'TN13 1AA',
            'nation' => 'England',
            'region' => 'South East',
            'town_city' => 'Sevenoaks',
            'county' => 'Kent',
        ]);
    }

    public function test_it_rejects_invalid_postcodes_without_calling_the_api(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/uk-postcodes/123456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Enter a valid UK postcode.');

        Http::assertNothingSent();
    }

    public function test_it_returns_a_clear_not_found_response(): void
    {
        Http::fake([
            'api.postcodes.io/postcodes/*' => Http::response([
                'status' => 404,
                'error' => 'Invalid postcode',
            ], 404),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/uk-postcodes/ZZ1%201ZZ')
            ->assertNotFound()
            ->assertJsonPath('message', 'We could not find that UK postcode.');
    }

    public function test_stored_postcode_is_available_without_calling_the_external_api(): void
    {
        UkPostcode::query()->create([
            'postcode_key' => 'SW1A1AA',
            'postcode' => 'SW1A 1AA',
            'nation' => 'England',
            'region' => 'London',
            'town_city' => 'Westminster',
            'county' => null,
            'raw_payload' => ['postcode' => 'SW1A 1AA'],
            'last_verified_at' => now(),
        ]);
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/uk-postcodes/SW1A%201AA')
            ->assertOk()
            ->assertJson([
                'postcode' => 'SW1A 1AA',
                'nation' => 'England',
                'region' => 'London',
                'town_city' => 'Westminster',
            ]);

        Http::assertNothingSent();
    }

    public function test_live_application_form_uses_uk_lookup_and_has_no_gst_field(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('trademark.application-form'));

        $response->assertOk()
            ->assertSee('Find address')
            ->assertSee('UK postcodes only')
            ->assertSee('Nation <b>*</b>', false)
            ->assertSee('County or Region')
            ->assertDontSee('Country / Region')
            ->assertSee('placeholder="Filled from postcode"', false)
            ->assertSee('Logo or Mark File')
            ->assertSee('<select id="trademark_language" name="trademark_language"', false)
            ->assertSee('<option value="English" selected>English</option>', false)
            ->assertSee('<option value="Welsh"', false)
            ->assertSee('<select id="authorised_person_position" name="authorised_person_position"', false)
            ->assertSee('<option value="Director"', false)
            ->assertSee('<option value="Authorised Signatory"', false)
            ->assertSee('id="ukTrademarkForm" novalidate', false)
            ->assertSee('client-error')
            ->assertDontSee('name="gst_number"', false)
            ->assertDontSee('GST Certificate');

        $this->assertSame(4, preg_match_all('/<input[^>]+data-uk-mobile/', $response->getContent()));
        preg_match('/<input[^>]+id="trademark_image"[^>]*>/', $response->getContent(), $trademarkImageInput);
        $this->assertNotEmpty($trademarkImageInput);
        $this->assertStringNotContainsString(' required', $trademarkImageInput[0]);
    }

    public function test_application_can_be_submitted_without_a_trademark_image(): void
    {
        Mail::fake();
        $admin = Admin::create([
            'name' => 'Application Admin',
            'email' => 'application-admin@example.test',
            'password' => bcrypt('password'),
        ]);
        Http::fake([
            'api.postcodes.io/postcodes/*' => Http::response([
                'status' => 200,
                'result' => [
                    'postcode' => 'BR6 6BJ',
                    'country' => 'England',
                    'region' => 'London',
                    'admin_district' => 'Bromley',
                    'admin_county' => null,
                ],
            ]),
        ]);
        $data = $this->validApplicationData();
        unset($data['trademark_image']);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('trademark.store'), $data);

        $application = \App\Models\Application::query()->firstOrFail();
        $response->assertRedirect(route('payment.show', $application->id));
        $this->assertNull($application->logo_path);
        $this->assertSame(TrademarkWorkflow::DRAFT, $application->status);
        $this->assertSame(TrademarkWorkflow::DRAFT, $application->service_status);
        Mail::assertSent(AdminWorkflowNotification::class, function (AdminWorkflowNotification $mail) use ($admin, $application): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->title, 'New UK trade mark application submitted')
                && str_contains($mail->notificationMessage, $application->brand_name);
        });
    }

    public function test_application_uses_authoritative_postcode_data_and_normalizes_uk_mobiles(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.postcodes.io/postcodes/*' => Http::response([
                'status' => 200,
                'result' => [
                    'postcode' => 'BR6 6BJ',
                    'country' => 'England',
                    'region' => 'London',
                    'admin_district' => 'Bromley',
                    'admin_county' => null,
                ],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('trademark.store'), $this->validApplicationData());

        $application = \App\Models\Application::query()->firstOrFail();
        $applicant = $application->members_details['applicant_details'];

        $response->assertRedirect(route('payment.show', $application->id));
        $this->assertSame('+447123456789', $application->phone);
        $this->assertSame('BR6 6BJ', $applicant['postcode']);
        $this->assertSame('England', $applicant['nation']);
        $this->assertSame('London', $applicant['county_or_region']);
        $this->assertSame('Bromley', $applicant['town_city']);
    }

    public function test_every_application_mobile_field_rejects_non_uk_numbers(): void
    {
        $user = User::factory()->create();

        foreach (['billing_mobile', 'applicant_phone', 'authorised_person_phone', 'additional_applicant_phone'] as $field) {
            $data = $this->validApplicationData();
            if ($field === 'additional_applicant_phone') {
                $data['joint_applicants'] = 'yes';
                $data['additional_applicant_type'] = 'individual';
                $data['additional_applicant_name'] = 'Joint Owner';
                $data['additional_applicant_address'] = '20 High Street, Bromley';
                $data['additional_applicant_email'] = 'joint@example.test';
            }
            $data[$field] = '+919876543210';

            $this->actingAs($user)
                ->from(route('trademark.application-form'))
                ->post(route('trademark.store'), $data)
                ->assertRedirect(route('trademark.application-form'))
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('applications', 0);
    }

    private function validApplicationData(): array
    {
        return [
            'billing_name' => 'UK Brand Limited',
            'billing_address' => '10 High Street, Bromley',
            'billing_email' => 'billing@example.test',
            'billing_mobile' => '07123 456789',
            'applicant_type' => 'limited_company',
            'applicant_name' => 'UK Brand Limited',
            'company_number' => '12345678',
            'company_registration_country' => 'United Kingdom',
            'applicant_address_line_1' => '10 High Street',
            'applicant_address_line_2' => '',
            'applicant_town_city' => 'Tampered Town',
            'applicant_nation' => 'England',
            'applicant_region' => 'Tampered Region',
            'applicant_postcode' => 'BR6 6BJ',
            'applicant_country' => 'United Kingdom',
            'uk_address_for_service' => '10 High Street, Bromley, BR6 6BJ',
            'applicant_phone' => '07123 456789',
            'applicant_email' => 'owner@example.test',
            'authorised_person_name' => 'Test Signatory',
            'authorised_person_position' => 'Director',
            'authorised_person_phone' => '+44 7123 456 789',
            'authorised_person_email' => 'signatory@example.test',
            'authority_confirmed' => '1',
            'joint_applicants' => 'no',
            'trademark_type' => 'word',
            'mark_brand' => 'Example Mark',
            'trademark_language' => 'English',
            'trademark_image' => UploadedFile::fake()->image('mark.png'),
            'business_activities' => 'Business consultancy services',
            'currently_in_use' => 'no',
            'application_route' => 'standard',
            'earlier_foreign_application' => 'no',
        ];
    }
}
