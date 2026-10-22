<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventLog;
use App\Models\Group;
use App\Models\SessionSwapRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

/**
 * Azioni della pagina Utenti: conferma manuale dell'email, reinvio della mail di conferma, eliminazione.
 */
class UserManagementActionsTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    private function unverified(string $role = 'Membro', array $attrs = []): User
    {
        return $this->makeUser($role, array_merge(['email_verified_at' => null], $attrs));
    }

    // ---------- Conferma manuale ----------

    public function test_staff_can_confirm_an_unverified_user_by_hand(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->unverified();

        $this->actingAs($admin)
            ->put(route('admin.users.verified', $target), ['verified' => 1])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->hasVerifiedEmail());
    }

    public function test_cd_can_confirm_too_and_confirmed_user_can_then_be_promoted(): void
    {
        $cd = $this->makeUser('CD');
        $target = $this->unverified();

        $this->actingAs($cd)->put(route('admin.users.verified', $target), ['verified' => 1]);
        $this->actingAs($cd)->put(route('admin.users.role', $target), ['role' => 'CD'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->hasRole('CD'));
    }

    public function test_confirmation_can_be_removed_from_a_plain_member_without_groups(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->makeUser();

        $this->actingAs($admin)
            ->put(route('admin.users.verified', $target), ['verified' => 0])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    public function test_confirmation_cannot_be_removed_from_staff_or_group_members(): void
    {
        $admin = $this->makeUser('ADMIN');
        $cd = $this->makeUser('CD');
        $member = $this->makeUser();
        Group::create(['name' => 'A', 'is_active' => true])->addUser($member);

        foreach ([$cd, $member] as $target) {
            $this->actingAs($admin)
                ->put(route('admin.users.verified', $target), ['verified' => 0])
                ->assertSessionHasErrors('verified');

            $this->assertTrue($target->fresh()->hasVerifiedEmail());
        }
    }

    public function test_an_admin_cannot_change_their_own_confirmation(): void
    {
        $admin = $this->makeUser('ADMIN');

        $this->actingAs($admin)
            ->put(route('admin.users.verified', $admin), ['verified' => 0])
            ->assertSessionHasErrors('verified');

        $this->assertTrue($admin->fresh()->hasVerifiedEmail());
    }

    public function test_confirmation_requires_a_valid_flag(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->unverified();

        $this->actingAs($admin)
            ->put(route('admin.users.verified', $target), ['verified' => 'forse'])
            ->assertSessionHasErrors('verified');

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    public function test_members_and_guests_cannot_change_the_confirmation(): void
    {
        $member = $this->makeUser();
        $target = $this->unverified();

        $this->actingAs($member)
            ->put(route('admin.users.verified', $target), ['verified' => 1])
            ->assertForbidden();
        $this->assertFalse($target->fresh()->hasVerifiedEmail());

        auth()->logout();
        $this->put(route('admin.users.verified', $target), ['verified' => 1])
            ->assertRedirect(route('login'));
    }

    // ---------- Reinvio mail di conferma ----------

    public function test_staff_can_resend_the_verification_email_to_an_unverified_user(): void
    {
        Notification::fake();
        $admin = $this->makeUser('ADMIN');
        $target = $this->unverified();

        $this->actingAs($admin)
            ->post(route('admin.users.verification', $target))
            ->assertRedirect();

        Notification::assertSentTo($target, VerifyEmail::class);
    }

    public function test_resend_is_refused_for_an_already_verified_user(): void
    {
        Notification::fake();
        $admin = $this->makeUser('ADMIN');
        $target = $this->makeUser();

        $this->actingAs($admin)
            ->post(route('admin.users.verification', $target))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_members_cannot_resend_verification_emails(): void
    {
        Notification::fake();
        $member = $this->makeUser();
        $target = $this->unverified();

        $this->actingAs($member)
            ->post(route('admin.users.verification', $target))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    // ---------- Pagina ----------

    public function test_users_page_shows_the_new_controls(): void
    {
        $admin = $this->makeUser('ADMIN');
        $this->unverified('Membro', ['name' => 'Utente Sospeso']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Confermato')
            ->assertSee('Reinvia mail')
            ->assertSee('Elimina');
    }

    // ---------- Eliminazione ----------

    public function test_staff_can_delete_a_user(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->makeUser();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::find($target->id));
    }

    public function test_deleting_a_user_keeps_the_events_they_created_and_the_logs(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->makeUser();
        $group = Group::create(['name' => 'Tavolo', 'is_active' => true]);
        $group->addUser($target);

        $event = Event::create([
            'title' => 'Sessione del tavolo',
            'type' => 'session',
            'group_id' => $group->id,
            'start_time' => now()->addWeek()->setTime(21, 0),
            'end_time' => now()->addWeek()->setTime(23, 0),
            'created_by' => $target->id,
        ]);
        $this->assertSame($target->id, EventLog::where('event_id', $event->id)->firstOrFail()->user_id);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $target))->assertRedirect();

        $event = Event::find($event->id);
        $this->assertNotNull($event, 'La sessione creata dall\'utente eliminato deve restare.');
        $this->assertNull($event->created_by);
        $this->assertNull(EventLog::where('event_id', $event->id)->firstOrFail()->user_id);
        $this->assertSame(0, $group->users()->count());
    }

    public function test_deleting_a_user_cancels_their_pending_swap_requests(): void
    {
        $admin = $this->makeUser('ADMIN');
        $target = $this->makeUser();
        $group = Group::create(['name' => 'Tavolo', 'is_active' => true]);
        $group->addUser($target);

        $event = $this->makeSession($group, now()->addWeek()->startOfDay());
        $swap = SessionSwapRequest::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'requested_by' => $target->id,
            'from_date' => $event->start_time->toDateString(),
            'target_date' => $event->start_time->copy()->addDays(7)->toDateString(),
            'status' => 'pending',
            'pending_flag' => 1,
        ]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $target))->assertRedirect();

        $swap = $swap->fresh();
        $this->assertSame('cancelled', $swap->status);
        $this->assertSame('user_deleted', $swap->cancel_reason);
        $this->assertSame($admin->id, $swap->cancelled_by);
        $this->assertNull($swap->requested_by);
        $this->assertNull($swap->pending_flag);
    }

    public function test_a_user_cannot_delete_themselves(): void
    {
        $admin = $this->makeUser('ADMIN');
        $this->makeUser('ADMIN');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_the_last_admin_cannot_be_deleted_by_a_cd(): void
    {
        $cd = $this->makeUser('CD');
        $onlyAdmin = $this->makeUser('ADMIN');

        $this->actingAs($cd)
            ->delete(route('admin.users.destroy', $onlyAdmin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull(User::find($onlyAdmin->id));
    }

    public function test_an_admin_can_be_deleted_when_another_admin_exists(): void
    {
        $admin = $this->makeUser('ADMIN');
        $other = $this->makeUser('ADMIN');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::find($other->id));
    }

    public function test_members_cannot_delete_users(): void
    {
        $member = $this->makeUser();
        $target = $this->makeUser();

        $this->actingAs($member)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertNotNull(User::find($target->id));
    }
}
