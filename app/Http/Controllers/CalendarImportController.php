<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\CalendarImport\CalendarImportService;
use App\Services\CalendarImport\ImportException;
use App\Services\CalendarImport\ImportFailedException;
use App\Services\CalendarImport\ImportOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CalendarImportController extends Controller
{
    private const EXTENSIONS = ['xlsx', 'xls'];

    private const OPTION_RULES = [
        'season_year' => ['required', 'integer', 'between:2000,2100'],
        'session_start' => ['required', 'date_format:H:i'],
        'session_end' => ['required', 'date_format:H:i'],
        'event_start' => ['required', 'date_format:H:i'],
        'event_end' => ['required', 'date_format:H:i'],
    ];

    public function __construct(private CalendarImportService $service)
    {
    }

    public function create(): View
    {
        Gate::authorize('import-calendar');

        return view('admin.import.calendar', [
            'preview' => null,
            'token' => null,
            'options' => new ImportOptions(ImportOptions::defaultSeasonYear()),
            'activeGroups' => collect(),
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        Gate::authorize('import-calendar');

        $request->validate(array_merge(self::OPTION_RULES, [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
        ]));

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $ext = in_array($ext, self::EXTENSIONS, true) ? $ext : 'xlsx';
        $token = (string) Str::uuid();

        Storage::disk('local')->putFileAs('imports', $file, $token.'.'.$ext);

        $options = ImportOptions::fromInput($request->only(array_keys(self::OPTION_RULES)));

        try {
            $preview = $this->service->preview($this->pathFor($token), $options);
        } catch (ImportException $e) {
            $this->deleteFile($token);

            return back()->withErrors(['file' => $e->getMessage()])->withInput($request->except('file'));
        }

        return $this->previewView($preview, $token, $options);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        Gate::authorize('import-calendar');

        $token = $this->validToken($request);
        $path = $this->pathFor($token);
        abort_unless($path, 404);

        $request->validate(self::OPTION_RULES);

        $options = ImportOptions::fromInput($request->all());

        try {
            $count = $this->service->commit($path, $options, $request->user());
        } catch (ImportFailedException $e) {
            $message = $e->preview->unresolvedGroups() && $e->preview->errorCount() === 0
                ? 'Scegli un gruppo (o «crea nuovo») per ogni nome evidenziato.'
                : $e->getMessage();

            return $this->previewView($e->preview, $token, $options)->withErrors(['file' => $message]);
        } catch (ImportException $e) {
            $this->deleteFile($token);

            return redirect()->route('admin.import.create')->withErrors(['file' => $e->getMessage()]);
        }

        $this->deleteFile($token);

        return redirect()->route('calendar.index')->with('success', "{$count} eventi importati.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('import-calendar');

        $this->deleteFile($this->validToken($request));

        return redirect()->route('admin.import.create')->with('success', 'Importazione annullata.');
    }

    private function previewView($preview, string $token, ImportOptions $options): View
    {
        return view('admin.import.calendar', [
            'preview' => $preview,
            'token' => $token,
            'options' => $options,
            'activeGroups' => Group::active()->orderBy('name')->get(),
        ]);
    }

    /** Il token è un UUID generato dal server: mai un percorso preso dalla richiesta. */
    private function validToken(Request $request): string
    {
        $token = (string) $request->input('token');
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $token) === 1, 422);

        return $token;
    }

    private function pathFor(string $token): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $relative = 'imports/'.$token.'.'.$ext;
            if (Storage::disk('local')->exists($relative)) {
                return Storage::disk('local')->path($relative);
            }
        }

        return null;
    }

    private function deleteFile(string $token): void
    {
        foreach (self::EXTENSIONS as $ext) {
            Storage::disk('local')->delete('imports/'.$token.'.'.$ext);
        }
    }
}
