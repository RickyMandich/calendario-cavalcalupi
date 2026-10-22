<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private function unverified(string $role = 'Membro', array $attrs = []): User
    {
        return $this->makeUser($role, array_merge(['email_verified_at' => null], $attrs));
    }

    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    // ---------- Registrazione e verifica ----------

    public function test_registration_creates_an_unverified_user_and_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('calendar.index'));

        $user = User::where('email', 'nuovo@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole('Membro'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_sees_banner_and_notice_page_verified_user_does_not(): void
    {
        $user = $this->unverified();

        $this->actingAs($user)->get('/calendar')->assertOk()->assertSee('id="verify-email-banner"', false);
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('Controlla la tua email');

        $verified = $this->makeUser();
        $this->actingAs($verified)->get('/calendar')->assertOk()->assertDontSee('id="verify-email-banner"', false);
        $this->actingAs($verified)->get(route('verification.notice'))->assertRedirect(route('calendar.index'));
    }

    public function test_resend_sends_the_notification_only_to_unverified_users(): void
    {
        Notification::fake();

        $user = $this->unverified();
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);

        $verified = $this->makeUser();
        $this->actingAs($verified)->post(route('verification.send'))->assertRedirect(route('calendar.index'));
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_signed_link_verifies_the_email_and_wrong_hash_is_rejected(): void
    {
        $user = $this->unverified();

        $this->actingAs($user)->get($this->verificationUrl($user, sha1('altro@example.com')))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('calendar.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $user = $this->unverified();

        $this->actingAs($user)
            ->get("/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    // ---------- Gruppi ----------

    public function test_unverified_user_cannot_be_added_to_a_group(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $user = $this->unverified();

        try {
            $group->addUser($user);
            $this->fail('addUser doveva rifiutare un utente non verificato.');
        } catch (DomainException $e) {
            $this->assertSame(0, $group->users()->count());
        }

        $verified = $this->makeUser();
        $group->addUser($verified);
        $this->assertTrue($group->users()->where('users.id', $verified->id)->exists());
    }

    public function test_group_assignment_from_the_ui_is_refused_and_unverified_users_are_not_listed(): void
    {
        $group = Group::create(['name' => 'A', 'is_active' => true]);
        $unverified = $this->unverified('Membro', ['email' => 'nonverificato@example.com']);
        $verified = $this->makeUser('Membro', ['email' => 'verificato@example.com']);
        $admin = $this->makeUser('ADMIN');

        $this->actingAs($admin)->get(route('admin.groups.index'))
            ->assertOk()
            ->assertSee('verificato@example.com')
            ->assertDontSee('nonverificato@example.com');

        $this->actingAs($admin)
            ->post(route('admin.groups.users.assign', $group), ['user_id' => $unverified->id])
            ->assertSessionHasErrors('user_id');
        $this->assertSame(0, $group->users()->count());

        $this->actingAs($admin)
            ->post(route('admin.groups.users.assign', $group), ['user_id' => $verified->id])
            ->assertRedirect(route('admin.groups.index'));
        $this->assertSame(1, $group->users()->count());
    }

    // ---------- Feed ICS ----------

    public function test_ics_feed_is_forbidden_for_unverified_users_and_available_to_verified_ones(): void
    {
        $unverified = $this->unverified();
        $verified = $this->makeUser();

        $this->get(route('calendar.ics', ['token' => $unverified->ics_token]))->assertForbidden();
        $this->get(route('calendar.ics', ['token' => $verified->ics_token]))->assertOk();
    }

    public function test_calendar_page_hides_the_ics_url_from_unverified_users(): void
    {
        $unverified = $this->unverified();
        $verified = $this->makeUser();

        $this->actingAs($unverified)->get('/calendar')
            ->assertOk()
            ->assertDontSee($unverified->ics_token)
            ->assertDontSee('id="icsModal"', false)
            ->assertSee("Verifica l'email per il Feed ICS", false);

        $this->actingAs($verified)->get('/calendar')
            ->assertOk()
            ->assertSee($verified->ics_token)
            ->assertSee('id="icsModal"', false);
    }

    // ---------- Ruoli ----------

    public function test_unverified_user_can_only_be_membro(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->unverified();

        foreach (['CD', 'ADMIN'] as $role) {
            $this->actingAs($admin)
                ->put(route('admin.users.role', $target), ['role' => $role])
                ->assertSessionHasErrors('role');
            $this->assertTrue($target->fresh()->hasRole('Membro'));
            $this->assertFalse($target->fresh()->hasRole($role));
        }

        $this->actingAs($admin)
            ->put(route('admin.users.role', $target), ['role' => 'Membro'])
            ->assertRedirect(route('admin.users.index'));

        $verified = $this->makeUser();
        $this->actingAs($admin)
            ->put(route('admin.users.role', $verified), ['role' => 'CD'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertTrue($verified->fresh()->hasRole('CD'));
    }

    public function test_users_page_flags_unverified_users(): void
    {
        $admin = $this->makeUser('ADMIN');
        $this->unverified('Membro', ['name' => 'Utente Sospeso']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Utente Sospeso')
            ->assertSee('Email non verificata');
    }

    public function test_make_admin_marks_the_email_as_verified(): void
    {
        $user = $this->unverified('Membro', ['email' => 'primo@example.com']);

        $this->artisan('app:make-admin', ['email' => 'primo@example.com'])->assertSuccessful();

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertTrue($user->hasVerifiedEmail());
    }
}
