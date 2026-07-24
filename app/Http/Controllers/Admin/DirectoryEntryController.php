<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\ResourceDirectoryController;
use App\Models\DirectoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff CRUD for the local resource directory (§5.8). Soft-deletes only —
 * nothing is hard-deleted (§10b).
 */
class DirectoryEntryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/directory/Index', [
            'entries' => DirectoryEntry::withTrashed()
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(fn (DirectoryEntry $e) => [
                    'id' => $e->id,
                    'category' => $e->category,
                    'name' => $e->name,
                    'area' => $e->area,
                    'url' => $e->url,
                    'notes' => $e->notes,
                    'active' => $e->active,
                    'deleted' => $e->trashed(),
                ]),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/directory/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DirectoryEntry::create($this->validated($request));

        return redirect()->route('admin.directory.index')->with('status', 'Resource added.');
    }

    public function edit(DirectoryEntry $directoryEntry): Response
    {
        return Inertia::render('admin/directory/Edit', [
            'entry' => $directoryEntry->only(['id', 'category', 'name', 'area', 'url', 'notes', 'active']),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, DirectoryEntry $directoryEntry): RedirectResponse
    {
        $directoryEntry->update($this->validated($request));

        return redirect()->route('admin.directory.index')->with('status', 'Resource updated.');
    }

    public function destroy(DirectoryEntry $directoryEntry): RedirectResponse
    {
        $directoryEntry->delete();

        return redirect()->route('admin.directory.index')->with('status', 'Resource archived.');
    }

    public function restore(int $directoryEntry): RedirectResponse
    {
        DirectoryEntry::withTrashed()->findOrFail($directoryEntry)->restore();

        return redirect()->route('admin.directory.index')->with('status', 'Resource restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(ResourceDirectoryController::CATEGORY_LABELS))],
            'name' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
    }

    /** @return array<int, array{value:string,label:string}> */
    private function categoryOptions(): array
    {
        return collect(ResourceDirectoryController::CATEGORY_LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
