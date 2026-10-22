<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    // ---------- Accesso ----------

    public function test_guest_is_redirected_to_login_from_every_profile_route(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->put(route('profile.update'), ['name' => 'X'])->assertRedirect(route('login'));
        $this->put(route('profile.email'), [])->assertRedirect(route('login'));
        $this->put(route('profile.password'), [])->assertRedirect(route('login'));
        $this->post(route('profile.ics-token'))->assertRedirect(route('login'));
    }

    public function test_profile_page_shows_the_user_data_and_is_reachable_from_the_header(): void
    {
        $user = $this->makeUser('Membro', ['name' => 'Mario Rossi', 'email' => 'mario@example.com']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Mario Rossi')
            ->assertSee('mario@example.com');

        $this->actingAs($user)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('href="'.route('profile.edit').'"', false);
    }

    public function test_unverified_user_can_open_the_profile_page(): void
    {
        $user = $this->makeUser('Membro', ['email_verified_at' => null]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Non verificata');
    }

    // ---------- Nome ----------

    public function test_user_can_change_the_name(): void
    {
        $user = $this->makeUser('Membro', ['name' => 'Vecchio Nome']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'Nuovo Nome'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Nuovo Nome', $user->fresh()->name);
    }

    public function test_name_is_required(): void
    {
        $user = $this->makeUser('Membro', ['name' => 'Resto Uguale']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name', null, 'profile');

        $this->assertSame('Resto Uguale', $user->fresh()->name);
    }

    // ---------- Email ----------

    public function test_email_change_requires_the_current_password(): void
    {
        Notification::fake();
        $user = $this->makeUser('Membro', ['email' => 'vecchia@example.com']);

        $this->actingAs($user)
            ->put(route('profile.email'), ['email' => 'nuova@example.com', 'current_password' => 'sbagliata'])
            ->assertSessionHasErrors('current_password', null, 'email');

        $this->assertSame('vecchia@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_email_change_marks_the_user_unverified_and_sends_the_verification_link(): void
    {
        Notification::fake();
        $user = $this->makeUser('Membro', ['email' => 'vecchia@example.com']);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->actingAs($user)
            ->put(route('profile.email'), ['email' => 'nuova@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('profile.edit'));

        $user = $user->fresh();
        $this->assertSame('nuova@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_change_keeps_roles(): void
    {
        Notification::fake();
        $cd = $this->makeUser('CD');

        $this->actingAs($cd)
            ->put(route('profile.email'), ['email' => 'cd-nuova@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($cd->fresh()->hasRole('CD'));
    }

    public function test_email_must_be_unique(): void
    {
        Notification::fake();
        $this->makeUser('Membro', ['email' => 'occupata@example.com']);
        $user = $this->makeUser('Membro', ['email' => 'mia@example.com']);

        $this->actingAs($user)
            ->put(route('profile.email'), ['email' => 'occupata@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrors('email', null, 'email');

        $this->assertSame('mia@example.com', $user->fresh()->email);
    }

    public function test_submitting_the_same_email_changes_nothing_and_keeps_the_verification(): void
    {
        Notification::fake();
        $user = $this->makeUser('Membro', ['email' => 'mia@example.com']);

        $this->actingAs($user)
            ->put(route('profile.email'), ['email' => 'mia@example.com', 'current_password' => 'password'])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    // ---------- Password ----------

    public function test_user_can_change_the_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'nuovaPassword1',
                'password_confirmation' => 'nuovaPassword1',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('nuovaPassword1', $user->fresh()->password));
    }

    public function test_password_change_requires_the_current_password_confirmation_and_minimum_length(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'sbagliata',
                'password' => 'nuovaPassword1',
                'password_confirmation' => 'nuovaPassword1',
            ])
            ->assertSessionHasErrors('current_password', null, 'password');

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'nuovaPassword1',
                'password_confirmation' => 'diversa',
            ])
            ->assertSessionHasErrors('password', null, 'password');

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'corta',
                'password_confirmation' => 'corta',
            ])
            ->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    // ---------- Token ICS ----------

    public function test_regenerating_the_ics_token_invalidates_the_old_feed_url(): void
    {
        $user = $this->makeUser();
        $old = $user->ics_token;

        $this->get(route('calendar.ics', ['token' => $old]))->assertOk();

        $this->actingAs($user)
            ->post(route('profile.ics-token'))
            ->assertRedirect(route('profile.edit'));

        $new = $user->fresh()->ics_token;
        $this->assertNotSame($old, $new);
        $this->assertSame(64, strlen($new));

        $this->get(route('calendar.ics', ['token' => $old]))->assertNotFound();
        $this->get(route('calendar.ics', ['token' => $new]))->assertOk();
    }

    public function test_ics_url_is_shown_only_to_users_with_a_verified_email(): void
    {
        $verified = $this->makeUser();
        $unverified = $this->makeUser('Membro', ['email_verified_at' => null]);

        $this->actingAs($verified)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($verified->ics_token);

        $this->actingAs($unverified)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee($unverified->ics_token);
    }

    public function test_profile_actions_only_touch_the_logged_in_user(): void
    {
        $user = $this->makeUser('Membro', ['name' => 'Io']);
        $other = $this->makeUser('Membro', ['name' => 'Altro']);
        $otherToken = $other->ics_token;

        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Io Cambiato']);
        $this->actingAs($user)->post(route('profile.ics-token'));

        $other = $other->fresh();
        $this->assertSame('Altro', $other->name);
        $this->assertSame($otherToken, $other->ics_token);
    }
}
