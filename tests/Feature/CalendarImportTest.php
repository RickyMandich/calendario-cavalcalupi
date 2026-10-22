<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Group;
use App\Models\Room;
use App\Services\CalendarImport\CalendarImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesRooms;
use Tests\TestCase;

/**
 * Importazione dal foglio mensile (vedi implementationPlan-importazioneCalendarioFoglioMensile.md).
 * Richiede phpoffice/phpspreadsheet.
 */
class CalendarImportTest extends TestCase
{
    use CreatesRooms;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createRoom(3, [4]); // giovedì: 3 posti
        $this->createRoom(5, [5]); // venerdì: 5 posti
    }

    // ---------------------------------------------------------------- helpers

    private function book(callable $fill, string $title = 'Calendario'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $fill($sheet);

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'calendario.xlsx', null, null, true);
    }

    /** Riga di serata: etichetta in A, un tavolo per colonna da B in poi. */
    private function day(Worksheet $sheet, int $row, string $label, array $cells = []): void
    {
        $sheet->setCellValue("A{$row}", $label);
        foreach ($cells as $i => $text) {
            $sheet->setCellValue(chr(66 + $i).$row, $text);
        }
    }

    private function banner(Worksheet $sheet, string $range, string $text): void
    {
        $sheet->setCellValue(explode(':', $range)[0], $text);
        $sheet->mergeCells($range);
    }

    /** Il dicembre dello screenshot (versione ridotta). */
    private function december(Worksheet $sheet): void
    {
        $this->banner($sheet, 'A1:F1', 'DICEMBRE');
        $this->day($sheet, 3, 'Giovedì 3', ["D&D 5.5\nIl Presagio del Corvo", "VILEBORN\nTales of Darkness", "SAVAGE WORLD\nRippers"]);
        $this->day($sheet, 4, 'Venerdì 4', ["13TH AGE\nDragon Empire", 'SCUOLE MASTER', "D&D 5E\nIl Ritorno di Darkhen", "DAGGERHEART\nAOU / beast feast", "DAGGERHEART\nSignori del Deserto"]);
        $this->day($sheet, 6, 'Giovedì 24');
        $this->day($sheet, 7, 'Venerdì 25');
        $this->banner($sheet, 'B6:F7', 'VIGILIA DI NATALE E NATALE');
        $this->day($sheet, 9, 'Giovedì 31');
        $this->banner($sheet, 'B9:F9', "ULTIMO DELL'ANNO");
        $this->day($sheet, 10, 'Venerdì 1');
    }

    private function simple(string $month, array $days): callable
    {
        return function (Worksheet $sheet) use ($month, $days) {
            $this->banner($sheet, 'A1:F1', $month);
            $row = 3;
            foreach ($days as [$label, $cells]) {
                $this->day($sheet, $row, $label, $cells);
                $row += 2;
            }
        };
    }

    private function baseOptions(array $override = []): array
    {
        return array_merge([
            'season_year' => 2026,
            'session_start' => '21:30',
            'session_end' => '00:00',
            'event_start' => '21:30',
            'event_end' => '00:00',
        ], $override);
    }

    private function upload(UploadedFile $file, array $override = [], $user = null)
    {
        return $this->actingAs($user ?? $this->makeUser('CD'))
            ->post(route('admin.import.store'), array_merge($this->baseOptions($override), ['file' => $file]));
    }

    private function confirm($user, string $token, array $extra = [], array $override = [])
    {
        return $this->actingAs($user)->post(route('admin.import.confirm'), array_merge(
            $this->baseOptions($override),
            ['token' => $token],
            $extra
        ));
    }

    /** Mappa "crea nuovo gruppo" per tutti i nomi non ancora riconosciuti. */
    private function newGroupsFor($preview): array
    {
        $map = [];
        foreach ($preview->unresolvedGroups() as $key => $choice) {
            $map[$key] = 'new';
        }

        return ['group_map' => $map];
    }

    // ---------------------------------------------------------------- permessi e sicurezza

    public function test_permissions(): void
    {
        $this->get(route('admin.import.create'))->assertRedirect(route('login'));

        $member = $this->makeUser();
        $this->actingAs($member)->get(route('admin.import.create'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.import.store'), [])->assertForbidden();
        $this->actingAs($member)->post(route('admin.import.confirm'), [])->assertForbidden();

        foreach (['CD', 'ADMIN'] as $role) {
            $this->actingAs($this->makeUser($role))->get(route('admin.import.create'))->assertOk();
        }
    }

    public function test_token_security_and_wrong_extension(): void
    {
        $staff = $this->makeUser('CD');

        $this->actingAs($staff)->post(route('admin.import.confirm'), ['token' => '../../.env'])->assertStatus(422);
        $this->actingAs($staff)->post(route('admin.import.confirm'), ['token' => '00000000-0000-0000-0000-000000000000'])->assertNotFound();

        $this->upload(UploadedFile::fake()->create('malware.exe', 10))->assertSessionHasErrors('file');
    }

    // ---------------------------------------------------------------- lettura del foglio

    public function test_december_sheet_is_read_correctly(): void
    {
        $preview = $this->upload($this->book(fn ($s) => $this->december($s)))->assertOk()->viewData('preview');

        $this->assertSame(8, $preview->rows->where('kind', 'table')->count());
        $this->assertSame(
            ['2026-12-24', '2026-12-25', '2026-12-31'],
            $preview->rows->where('kind', 'banner')->pluck('date')->values()->all()
        );
        $this->assertSame(0, $preview->errorCount());
        $this->assertTrue($preview->canCommit());
        $this->assertCount(8, $preview->groups);

        $first = $preview->rows->first();
        $this->assertSame('Il Presagio del Corvo', $first->groupName);
        $this->assertSame('D&D 5.5', $first->system);
        $this->assertSame('2026-12-03', $first->date);

        $scuole = $preview->rows->firstWhere('groupName', 'SCUOLE MASTER');
        $this->assertNull($scuole->system);
        $this->assertSame('2026-12-04', $scuole->date);
    }

    public function test_sessions_run_until_midnight_of_the_next_day(): void
    {
        $preview = $this->upload($this->book(fn ($s) => $this->december($s)))->viewData('preview');
        $session = $preview->rows->first();

        $this->assertSame('2026-12-03 21:30', $session->attributes['start_time']->format('Y-m-d H:i'));
        $this->assertSame('2026-12-04 00:00', $session->attributes['end_time']->format('Y-m-d H:i'));
        $this->assertSame('Sistema: D&D 5.5', $session->attributes['description']);

        $custom = $this->upload($this->book(fn ($s) => $this->december($s)), ['session_start' => '20:00', 'session_end' => '23:30'])
            ->viewData('preview')->rows->first();
        $this->assertSame('2026-12-03 23:30', $custom->attributes['end_time']->format('Y-m-d H:i'));
    }

    public function test_several_months_on_one_sheet_and_month_rollover(): void
    {
        $file = $this->book(function (Worksheet $sheet) {
            $this->banner($sheet, 'A1:F1', 'NOVEMBRE');
            $this->day($sheet, 3, 'Giovedì 5', ["D&D 5E\nA"]);
            $this->banner($sheet, 'A5:F5', 'DICEMBRE');
            $this->day($sheet, 7, 'Giovedì 31', ["D&D 5E\nA"]);
            $this->day($sheet, 8, 'Venerdì 1', ["D&D 5E\nB"]);
        });

        $preview = $this->upload($file)->viewData('preview');

        $this->assertSame(['2026-11-05', '2026-12-31', '2027-01-01'], $preview->rows->pluck('date')->all());
        $this->assertSame(0, $preview->errorCount());
    }

    public function test_month_from_sheet_name_and_missing_month_error(): void
    {
        $byName = $this->book(function (Worksheet $sheet) {
            $this->day($sheet, 2, 'Giovedì 3', ["D&D 5E\nA"]);
        }, 'Dicembre');
        $this->assertSame('2026-12-03', $this->upload($byName)->viewData('preview')->rows->first()->date);

        $noMonth = $this->book(function (Worksheet $sheet) {
            $this->day($sheet, 2, 'Giovedì 3', ["D&D 5E\nA"]);
        }, 'Foglio1');
        $this->upload($noMonth)->assertSessionHasErrors('file');
    }

    public function test_wrong_season_year_is_caught_by_weekday_check(): void
    {
        $preview = $this->upload($this->book(fn ($s) => $this->december($s)), ['season_year' => 2025])->viewData('preview');

        $this->assertFalse($preview->canCommit());
        $this->assertStringContainsString('non corrisponde', $preview->rows->first()->errors[0]);
    }

    // ---------------------------------------------------------------- gruppi

    public function test_matching_groups_are_preselected_and_others_unresolved(): void
    {
        $presagio = Group::create(['name' => 'Il Presagio del Corvo', 'is_active' => true]);
        Group::create(['name' => 'Tales of Darkness', 'is_active' => false]);

        $preview = $this->upload($this->book(fn ($s) => $this->december($s)))->viewData('preview');

        $this->assertSame((string) $presagio->id, $preview->groups[CalendarImportService::key('Il Presagio del Corvo')]['selected']);
        $tales = $preview->groups[CalendarImportService::key('Tales of Darkness')];
        $this->assertSame('', $tales['selected']);
        $this->assertTrue($tales['inactive']);
        $this->assertCount(7, $preview->unresolvedGroups());
    }

    public function test_confirm_creates_new_groups_and_all_events(): void
    {
        $staff = $this->makeUser('CD');
        $response = $this->upload($this->book(fn ($s) => $this->december($s)), [], $staff);
        $preview = $response->viewData('preview');

        $this->confirm($staff, $response->viewData('token'), $this->newGroupsFor($preview))
            ->assertRedirect(route('calendar.index'));

        $this->assertSame(8, Group::count());
        $this->assertSame(11, Event::count());
        $this->assertSame(8, Event::where('type', 'session')->count());
        $this->assertSame(3, Event::where('type', 'association_event')->count());

        $event = Event::where('title', 'Il Presagio del Corvo')->firstOrFail();
        $this->assertSame('Il Presagio del Corvo', $event->group->name);
        $this->assertTrue($event->group->is_active);
        $this->assertSame('Sistema: D&D 5.5', $event->description);
        $this->assertSame('2026-12-03 21:30', $event->start_time->format('Y-m-d H:i'));
        $this->assertSame('2026-12-04 00:00', $event->end_time->format('Y-m-d H:i'));
        $this->assertSame($staff->id, $event->created_by);
        $this->assertDatabaseHas('event_logs', ['event_id' => $event->id, 'action' => 'created']);
        $this->assertEmpty(Storage::disk('local')->files('imports'));
    }

    public function test_unresolved_groups_block_the_commit_and_nothing_is_created(): void
    {
        $staff = $this->makeUser('CD');
        $response = $this->upload($this->book(fn ($s) => $this->december($s)), [], $staff);

        $this->confirm($staff, $response->viewData('token'))->assertOk()->assertViewHas('preview');

        $this->assertSame(0, Event::count());
        $this->assertSame(0, Group::count());
        $this->assertCount(1, Storage::disk('local')->files('imports')); // il file resta, si può riprovare
    }

    public function test_typo_can_be_mapped_to_an_existing_group(): void
    {
        $group = Group::create(['name' => 'Il Presagio del Corvo', 'is_active' => true]);
        $staff = $this->makeUser('CD');
        $file = $this->book($this->simple('DICEMBRE', [['Giovedì 3', ["D&D 5.5\nIl Presagio del Corvp"]]]));

        $response = $this->upload($file, [], $staff);
        $key = CalendarImportService::key('Il Presagio del Corvp');
        $this->assertSame('', $response->viewData('preview')->groups[$key]['selected']);

        $this->confirm($staff, $response->viewData('token'), ['group_map' => [$key => (string) $group->id]])
            ->assertRedirect(route('calendar.index'));

        $this->assertSame(1, Group::count());
        $this->assertSame($group->id, Event::firstOrFail()->group_id);
    }

    public function test_inactive_group_cannot_be_chosen(): void
    {
        $inactive = Group::create(['name' => 'Vecchio', 'is_active' => false]);
        $staff = $this->makeUser('CD');
        $file = $this->book($this->simple('DICEMBRE', [['Giovedì 3', ["D&D 5.5\nNuovo"]]]));

        $response = $this->upload($file, [], $staff);
        $this->confirm($staff, $response->viewData('token'), ['group_map' => [CalendarImportService::key('Nuovo') => (string) $inactive->id]])->assertOk();

        $this->assertSame(0, Event::count());
    }

    // ---------------------------------------------------------------- bande

    public function test_gioco_libero_is_ignored_and_oneshot_becomes_association_event(): void
    {
        $staff = $this->makeUser('CD');
        $file = $this->book(function (Worksheet $sheet) {
            $this->banner($sheet, 'A1:F1', 'OTTOBRE');
            $this->day($sheet, 3, 'Giovedì 29');
            $this->banner($sheet, 'B3:F3', 'GIOCO LIBERO');
            $this->day($sheet, 4, 'Venerdì 30');
            $this->banner($sheet, 'B4:F4', 'ONESHOT DI HALLOWEEN');
        });

        $response = $this->upload($file, [], $staff);
        $preview = $response->viewData('preview');

        $this->assertTrue($preview->rows->first()->isIgnored());
        $this->assertSame('association_event', $preview->rows->last()->action);
        $this->assertSame(1, $preview->validCount());

        $this->confirm($staff, $response->viewData('token'))->assertRedirect(route('calendar.index'));

        $this->assertSame(1, Event::count());
        $event = Event::firstOrFail();
        $this->assertSame('ONESHOT DI HALLOWEEN', $event->title);
        $this->assertSame('association_event', $event->type);
        $this->assertSame('2026-10-30', $event->start_time->toDateString());
    }

    public function test_banner_can_be_imported_as_assembly(): void
    {
        $staff = $this->makeUser('CD');
        $file = $this->book(function (Worksheet $sheet) {
            $this->banner($sheet, 'A1:F1', 'OTTOBRE');
            $this->day($sheet, 3, 'Venerdì 30');
            $this->banner($sheet, 'B3:F3', 'ASSEMBLEA DEI SOCI');
        });

        $token = $this->upload($file, [], $staff)->viewData('token');
        $this->confirm($staff, $token, ['banner_action' => [CalendarImportService::key('ASSEMBLEA DEI SOCI') => 'assembly']])
            ->assertRedirect(route('calendar.index'));

        $this->assertSame('assembly', Event::firstOrFail()->type);
    }

    // ---------------------------------------------------------------- regole di calendario

    public function test_capacity_counts_tables_of_the_same_sheet(): void
    {
        // giovedì: capienza 3, quattro tavoli -> l'ultimo è in errore
        $file = $this->book($this->simple('DICEMBRE', [['Giovedì 3', ["A\nUno", "B\nDue", "C\nTre", "D\nQuattro"]]]));

        $preview = $this->upload($file)->viewData('preview');

        $this->assertSame([true, true, true, false], $preview->rows->map->isValid()->all());
        $this->assertStringContainsString('Giorno pieno', $preview->rows->last()->errors[0]);
        $this->assertFalse($preview->canCommit());
    }

    public function test_system_and_group_in_two_stacked_cells_are_read_like_one_cell(): void
    {
        // Giovedì 3: riga sistema + riga gruppo (celle sovrapposte, etichetta unita sulle due righe); Venerdì 4 subito dopo
        $file = $this->book(function (Worksheet $sheet) {
            $this->banner($sheet, 'A1:F1', 'DICEMBRE');
            $sheet->setCellValue('A3', 'Giovedì 3');
            $sheet->mergeCells('A3:A4');
            $sheet->setCellValue('B3', 'D&D 5.5');
            $sheet->setCellValue('B4', 'Il Presagio del Corvo');
            $sheet->setCellValue('C4', 'SCUOLE MASTER'); // solo gruppo, sistema vuoto
            $sheet->setCellValue('A5', 'Venerdì 4');
            $sheet->mergeCells('A5:A6');
            $sheet->setCellValue('B5', '13TH AGE');
            $sheet->setCellValue('B6', 'Dragon Empire');
        });

        $preview = $this->upload($file)->viewData('preview');

        $this->assertCount(3, $preview->rows);
        $this->assertSame(['Il Presagio del Corvo', 'SCUOLE MASTER', 'Dragon Empire'], $preview->rows->map->groupName->all());
        $this->assertSame(['D&D 5.5', null, '13TH AGE'], $preview->rows->map->system->all());
        $this->assertTrue($preview->canCommit());
    }

    public function test_day_without_rooms_is_an_error(): void
    {
        Room::query()->delete();

        $preview = $this->upload($this->book($this->simple('DICEMBRE', [['Giovedì 3', ["A\nUno"]]])))->viewData('preview');

        $this->assertStringContainsString('Nessuna stanza', $preview->rows->first()->errors[0]);
    }

    public function test_assembly_banner_conflicts_with_an_existing_session_of_the_same_evening(): void
    {
        $staff = $this->makeUser('CD');
        $group = Group::create(['name' => 'Esistente', 'is_active' => true]);
        $this->makeSession($group, Carbon::create(2026, 12, 3, 0, 0, 0, config('app.timezone')), '21:30', '23:30');

        $file = $this->book(function (Worksheet $sheet) {
            $this->banner($sheet, 'A1:F1', 'DICEMBRE');
            $this->day($sheet, 3, 'Giovedì 3');
            $this->banner($sheet, 'B3:F3', 'ASSEMBLEA');
        });

        $token = $this->upload($file, [], $staff)->viewData('token');

        // come evento associativo va bene; come assemblea no (esclude gli altri eventi nello stesso orario)
        $response = $this->confirm($staff, $token, ['banner_action' => [CalendarImportService::key('ASSEMBLEA') => 'assembly']])
            ->assertOk();

        $this->assertSame(1, $response->viewData('preview')->errorCount());
        $this->assertSame(1, Event::count());
    }

    public function test_reimporting_the_same_sheet_reports_duplicates(): void
    {
        $staff = $this->makeUser('CD');
        $response = $this->upload($this->book(fn ($s) => $this->december($s)), [], $staff);
        $this->confirm($staff, $response->viewData('token'), $this->newGroupsFor($response->viewData('preview')));
        $this->assertSame(11, Event::count());

        $again = $this->upload($this->book(fn ($s) => $this->december($s)), [], $staff)->viewData('preview');

        $this->assertFalse($again->canCommit());
        $this->assertSame(0, $again->validCount());
        $this->assertStringContainsString('già presente', $again->rows->first()->errors[0]);
        $this->assertSame(11, Event::count());
    }

    public function test_commit_revalidates_against_the_current_state(): void
    {
        $staff = $this->makeUser('CD');
        $response = $this->upload($this->book(fn ($s) => $this->december($s)), [], $staff);
        $this->assertTrue($response->viewData('preview')->canCommit());

        // dopo l'anteprima spariscono le stanze: nulla deve essere creato (neppure i gruppi)
        Room::query()->delete();
        $this->confirm($staff, $response->viewData('token'), $this->newGroupsFor($response->viewData('preview')))->assertOk();

        $this->assertSame(0, Event::count());
        $this->assertSame(0, Group::count());
    }
}
