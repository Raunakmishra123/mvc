<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\ImportBatch;
use App\Services\CsvImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    public function showForm(Group $group)
    {
        $this->authoriseMember($group);

        $batches = ImportBatch::where('group_id', $group->id)
            ->with('importer')
            ->orderByDesc('imported_at')
            ->get();

        return view('import.form', compact('group', 'batches'));
    }

    public function import(Request $request, Group $group)
    {
        $this->authoriseMember($group);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file     = $request->file('csv_file');
        $tmpPath  = $file->getRealPath();
        $filename = $file->getClientOriginalName();

        try {
            $importer = new CsvImporter($group->id, Auth::id());
            $batchId  = $importer->import($tmpPath, $filename);
        } catch (\Exception $e) {
            return back()->withErrors(['csv_file' => 'Import failed: ' . $e->getMessage()]);
        }

        return redirect()->route('import.report', $batchId)
                         ->with('success', 'Import complete! Review the anomaly report below.');
    }

    public function report(ImportBatch $batch)
    {
        $this->authoriseMember($batch->group);

        $batch->load([
            'anomalies' => fn ($q) => $q->orderBy('row_number')->orderBy('id'),
            'group',
            'importer',
        ]);

        $anomaliesByRow    = $batch->anomalies->groupBy('row_number');
        $highCount         = $batch->anomalies->where('severity', 'high')->count();
        $needsReviewCount  = $batch->anomalies->where('needs_human_review', true)->count();

        return view('import.report', compact(
            'batch', 'anomaliesByRow', 'highCount', 'needsReviewCount'
        ));
    }

    private function authoriseMember(Group $group): void
    {
        $isMember = GroupMembership::where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->exists();
        if (!$isMember) {
            abort(403, 'You are not a member of this group.');
        }
    }
}
