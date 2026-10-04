<?php

namespace Tests\Feature;

use App\Models\BloodComponent;
use App\Models\BloodDonation;
use App\Models\BloodRequest;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\HealthcareFacility;
use App\Models\Recipient;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_donor_registration_creates_a_private_unverified_profile_and_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'role' => 'donor',
            'name' => 'Amina Sample',
            'first_name' => 'Amina',
            'last_name' => 'Sample',
            'email' => 'amina@example.test',
            'phone' => '+213555010101',
            'password' => 'a-very-long-test-password',
            'password_confirmation' => 'a-very-long-test-password',
            'wilaya_id' => Wilaya::where('code', '16')->value('id'),
            'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'donor')
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('donors', [
            'first_name' => 'Amina',
            'account_verified' => false,
            'medical_eligibility_verified' => false,
            'consent_to_contact' => false,
        ]);
        $this->assertDatabaseMissing('users', ['password' => 'a-very-long-test-password']);

        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('user.donor.first_name', 'Amina');
    }

    public function test_donor_can_set_contact_consent_and_availability_without_self_verifying(): void
    {
        $user = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $user->id,
            'first_name' => 'Amina',
            'last_name' => 'Sample',
            'phone' => '+213555010110',
            'wilaya_id' => Wilaya::where('code', '16')->value('id'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/donor/profile', [
                'consent_to_contact' => true,
                'availability' => 'available',
                'account_verified' => true,
                'medical_eligibility_verified' => true,
            ])
            ->assertOk()
            ->assertJsonPath('donor.consent_to_contact', true)
            ->assertJsonPath('donor.availability', 'available')
            ->assertJsonPath('donor.account_verified', false)
            ->assertJsonPath('donor.medical_eligibility_verified', false);

        $this->assertDatabaseHas('donors', [
            'id' => $donor->id,
            'consent_to_contact' => true,
            'availability' => 'available',
            'account_verified' => false,
            'medical_eligibility_verified' => false,
        ]);
    }

    public function test_public_registration_cannot_assign_privileged_roles(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'role' => 'admin',
            'name' => 'Untrusted Admin',
            'first_name' => 'Untrusted',
            'last_name' => 'Admin',
            'email' => 'admin@example.test',
            'phone' => '+213555010101',
            'password' => 'a-very-long-test-password',
            'password_confirmation' => 'a-very-long-test-password',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_login_normalizes_email_and_logout_revokes_the_token(): void
    {
        $user = User::factory()->create([
            'email' => 'case.test@example.test',
            'password' => Hash::make('a-very-long-test-password'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => '  CASE.TEST@EXAMPLE.TEST ',
            'password' => 'a-very-long-test-password',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $token = $login->json('token');
        $tokenId = (int) strstr($token, '|', true);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.login_succeeded', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.logout', 'user_id' => $user->id]);
    }

    public function test_recipient_requests_are_private_until_staff_review(): void
    {
        $first = $this->recipient();
        $other = $this->recipient();
        $this->actingAs($first);

        $response = $this->postJson('/api/v1/blood-requests', [
            'blood_type_id' => BloodType::where('code', 'A+')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'wilaya_id' => Wilaya::where('code', '16')->value('id'),
            'units' => 2,
            'urgency' => 'emergency',
            'needed_by' => now()->addDay()->toIso8601String(),
            'notes' => 'Please contact the facility.',
        ]);

        $response->assertCreated()->assertJsonPath('request.status', 'pending_review');
        $this->getJson('/api/v1/blood-requests')->assertOk()->assertJsonPath('data.0.status', 'pending_review');

        $this->actingAs($other)->getJson('/api/v1/blood-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_can_review_and_fulfill_a_request_from_compatible_unexpired_stock(): void
    {
        $wilaya = Wilaya::where('code', '16')->firstOrFail();
        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'first_name' => 'Donor',
            'last_name' => 'One',
            'phone' => '+213555010102',
            'wilaya_id' => $wilaya->id,
            'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
            'account_verified' => true,
            'medical_eligibility_verified' => true,
            'consent_to_contact' => true,
            'availability' => 'available',
        ]);

        $facility = HealthcareFacility::create([
            'name' => 'Test Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => $wilaya->id,
            'verified' => true,
            'accepts_requests' => true,
        ]);

        $recipientUser = User::factory()->create(['role' => 'recipient']);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'first_name' => 'Patient',
            'last_name' => 'One',
            'phone' => '+213555010103',
        ]);
        $bloodRequest = BloodRequest::create([
            'recipient_id' => $recipient->id,
            'facility_id' => $facility->id,
            'blood_type_id' => BloodType::where('code', 'A+')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'wilaya_id' => $wilaya->id,
            'units' => 1,
            'fulfilled_units' => 0,
            'urgency' => 'urgent',
            'status' => 'pending_review',
            'needed_by' => now()->addDay(),
        ]);
        $donation = BloodDonation::create([
            'donor_id' => $donor->id,
            'facility_id' => $facility->id,
            'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'donated_at' => now()->subDay(),
            'expires_at' => now()->addDays(5)->toDateString(),
            'status' => 'available',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $facility->staff()->attach($staff->id, ['assigned_by' => User::factory()->create(['role' => 'admin'])->id]);
        $this->actingAs($staff);

        $this->postJson("/api/v1/operations/blood-requests/{$bloodRequest->id}/review", [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('request.status', 'open');

        $this->postJson("/api/v1/operations/blood-requests/{$bloodRequest->id}/fulfill", [
            'units' => 1,
        ])->assertOk()->assertJsonPath('request.status', 'fulfilled')
            ->assertJsonPath('request.fulfilled_units', 1);

        $this->assertDatabaseHas('blood_donations', [
            'id' => $donation->id,
            'status' => 'issued',
            'blood_request_id' => $bloodRequest->id,
        ]);
        $this->assertDatabaseHas('notification_records', [
            'user_id' => $recipientUser->id,
            'type' => 'blood_request.fulfilled',
        ]);
    }

    public function test_operations_routes_require_staff_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'recipient']))
            ->getJson('/api/v1/operations/dashboard')
            ->assertForbidden();
    }

    public function test_reference_data_contains_all_58_wilaya_codes_and_discloses_missing_communes(): void
    {
        $response = $this->getJson('/api/v1/reference-data?locale=ar');

        $response->assertOk()
            ->assertJsonPath('communes_available', false)
            ->assertJsonCount(58, 'wilayas');
    }

    public function test_matching_returns_only_consented_verified_candidates_to_staff(): void
    {
        $wilaya = Wilaya::where('code', '16')->firstOrFail();
        $recipientUser = User::factory()->create(['role' => 'recipient']);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'first_name' => 'Patient',
            'last_name' => 'One',
            'phone' => '+213555010105',
        ]);
        $bloodRequest = BloodRequest::create([
            'recipient_id' => $recipient->id,
            'blood_type_id' => BloodType::where('code', 'A+')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'wilaya_id' => $wilaya->id,
            'units' => 1,
            'fulfilled_units' => 0,
            'urgency' => 'urgent',
            'status' => 'open',
            'needed_by' => now()->addDay(),
        ]);

        foreach ([
            ['Candidate', true, true, true],
            ['NoConsent', true, true, false],
            ['NotReviewed', true, false, true],
        ] as [$firstName, $accountVerified, $medicallyReviewed, $consent]) {
            $donorUser = User::factory()->create(['role' => 'donor']);
            Donor::create([
                'user_id' => $donorUser->id,
                'first_name' => $firstName,
                'last_name' => 'Test',
                'phone' => '+213555010106',
                'wilaya_id' => $wilaya->id,
                'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
                'account_verified' => $accountVerified,
                'medical_eligibility_verified' => $medicallyReviewed,
                'consent_to_contact' => $consent,
                'availability' => 'available',
            ]);
        }

        $staff = User::factory()->create(['role' => 'staff']);
        $facility = HealthcareFacility::create([
            'name' => 'Staff Assigned Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => $wilaya->id,
            'verified' => true,
            'accepts_requests' => true,
        ]);
        $facility->staff()->attach($staff->id, ['assigned_by' => User::factory()->create(['role' => 'admin'])->id]);

        $this->actingAs($staff)
            ->getJson("/api/v1/operations/blood-requests/{$bloodRequest->id}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.first_name', 'Candidate')
            ->assertJsonPath('notice', fn (string $notice): bool => str_contains($notice, 'operational aid'));
    }

    public function test_staff_donor_review_list_is_limited_to_assigned_wilayas_and_omits_phone_numbers(): void
    {
        $wilaya = Wilaya::where('code', '16')->firstOrFail();
        foreach ([$wilaya, Wilaya::where('code', '01')->firstOrFail()] as $index => $donorWilaya) {
            $user = User::factory()->create(['role' => 'donor']);
            Donor::create([
                'user_id' => $user->id,
                'first_name' => "Donor{$index}",
                'last_name' => 'Review',
                'phone' => '+21355501011'.($index + 1),
                'wilaya_id' => $donorWilaya->id,
            ]);
        }

        $staff = User::factory()->create(['role' => 'staff']);
        $facility = HealthcareFacility::create([
            'name' => 'Assigned Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => $wilaya->id,
            'verified' => true,
        ]);
        $facility->staff()->attach($staff->id, ['assigned_by' => User::factory()->create(['role' => 'admin'])->id]);

        $this->actingAs($staff)
            ->getJson('/api/v1/operations/donors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Donor0')
            ->assertJsonMissingPath('data.0.phone');
    }

    public function test_staff_cannot_review_requests_outside_assigned_wilayas(): void
    {
        $recipientUser = User::factory()->create(['role' => 'recipient']);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'first_name' => 'Patient',
            'last_name' => 'Test',
            'phone' => '+213555010107',
        ]);
        $bloodRequest = BloodRequest::create([
            'recipient_id' => $recipient->id,
            'blood_type_id' => BloodType::where('code', 'A+')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'wilaya_id' => Wilaya::where('code', '01')->value('id'),
            'units' => 1,
            'fulfilled_units' => 0,
            'urgency' => 'routine',
            'status' => 'pending_review',
            'needed_by' => now()->addDay(),
        ]);

        $staff = User::factory()->create(['role' => 'staff']);
        $otherWilayaFacility = HealthcareFacility::create([
            'name' => 'Unrelated Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => Wilaya::where('code', '16')->value('id'),
            'verified' => true,
        ]);
        $otherWilayaFacility->staff()->attach($staff->id, ['assigned_by' => User::factory()->create(['role' => 'admin'])->id]);

        $this->actingAs($staff)
            ->postJson("/api/v1/operations/blood-requests/{$bloodRequest->id}/review", ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertDatabaseHas('blood_requests', ['id' => $bloodRequest->id, 'status' => 'pending_review']);
    }

    public function test_fulfillment_does_not_consume_expired_or_missing_expiry_stock(): void
    {
        $wilaya = Wilaya::where('code', '16')->firstOrFail();
        $donorUser = User::factory()->create(['role' => 'donor']);
        $donor = Donor::create([
            'user_id' => $donorUser->id,
            'first_name' => 'Donor',
            'last_name' => 'One',
            'phone' => '+213555010108',
            'wilaya_id' => $wilaya->id,
            'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
            'account_verified' => true,
            'availability' => 'available',
        ]);
        $facility = HealthcareFacility::create([
            'name' => 'Test Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => $wilaya->id,
            'verified' => true,
        ]);
        $recipientUser = User::factory()->create(['role' => 'recipient']);
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'first_name' => 'Patient',
            'last_name' => 'One',
            'phone' => '+213555010109',
        ]);
        $bloodRequest = BloodRequest::create([
            'recipient_id' => $recipient->id,
            'facility_id' => $facility->id,
            'blood_type_id' => BloodType::where('code', 'A+')->value('id'),
            'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
            'wilaya_id' => $wilaya->id,
            'units' => 1,
            'fulfilled_units' => 0,
            'urgency' => 'routine',
            'status' => 'open',
            'needed_by' => now()->addDay(),
        ]);

        foreach ([now()->subDay()->toDateString(), null] as $expiry) {
            BloodDonation::create([
                'donor_id' => $donor->id,
                'facility_id' => $facility->id,
                'blood_type_id' => BloodType::where('code', 'O-')->value('id'),
                'blood_component_id' => BloodComponent::where('code', 'RBC')->value('id'),
                'donated_at' => now()->subDays(20),
                'expires_at' => $expiry,
                'status' => 'available',
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->postJson("/api/v1/operations/blood-requests/{$bloodRequest->id}/fulfill", ['units' => 1])
            ->assertConflict();

        $this->assertDatabaseHas('blood_requests', ['id' => $bloodRequest->id, 'status' => 'open', 'fulfilled_units' => 0]);
        $this->assertDatabaseCount('notification_records', 0);
    }

    public function test_browser_api_preflight_allows_only_the_configured_local_origin(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/reference-data', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertNoContent()
            ->assertHeader('access-control-allow-origin', 'http://localhost:3000');
    }

    public function test_facility_activation_requires_a_different_administrator_and_staff_assignment(): void
    {
        $wilaya = Wilaya::where('code', '16')->firstOrFail();
        $creator = User::factory()->create(['role' => 'admin']);
        $facility = HealthcareFacility::create([
            'name' => 'Pending Facility',
            'facility_type' => 'hospital',
            'wilaya_id' => $wilaya->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->patchJson("/api/v1/admin/facilities/{$facility->id}/verification", [
                'verified' => true,
                'accepts_requests' => true,
            ])->assertForbidden();

        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($otherAdmin)
            ->patchJson("/api/v1/admin/facilities/{$facility->id}/verification", [
                'verified' => true,
                'accepts_requests' => true,
            ])->assertOk();

        $staff = User::factory()->create(['role' => 'staff']);
        $this->putJson("/api/v1/admin/facilities/{$facility->id}/staff/{$staff->id}")
            ->assertCreated();

        $this->assertDatabaseHas('facility_staff', [
            'healthcare_facility_id' => $facility->id,
            'user_id' => $staff->id,
            'assigned_by' => $otherAdmin->id,
        ]);
    }

    public function test_facility_staff_cannot_read_global_audit_logs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->getJson('/api/v1/admin/audit-logs')
            ->assertForbidden();
    }

    private function recipient(): User
    {
        $user = User::factory()->create(['role' => 'recipient']);
        Recipient::create([
            'user_id' => $user->id,
            'first_name' => 'Recipient',
            'last_name' => 'Test',
            'phone' => '+213555010104',
        ]);

        return $user;
    }
}
