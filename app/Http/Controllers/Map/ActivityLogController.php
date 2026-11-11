<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate([
            'q' => 'nullable|string|max:100', 'canal' => 'nullable|string|max:100',
            'sort' => ['nullable', Rule::in(['created_at'])], 'direction' => 'nullable|in:asc,desc',
        ]);
        $direction = $input['direction'] ?? 'desc';
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $items = Schema::hasTable('activity_log')
            ? DB::table('activity_log as a')
                ->leftJoin('users as u', fn ($join) => $join->on('u.id', '=', 'a.causer_id')->where('a.causer_type', User::class))
                ->when($input['q'] ?? null, fn ($query, $q) => $query->where(fn ($nested) => $nested
                    ->where('a.description', $operator, '%'.$q.'%')->orWhere('a.subject_type', $operator, '%'.$q.'%')
                    ->orWhere('a.log_name', $operator, '%'.$q.'%')))
                ->when($input['canal'] ?? null, fn ($query, $channel) => $query->where('a.log_name', $channel))
                ->select('a.*', 'u.name as causer_name')
                ->orderBy('a.created_at', $direction)->orderBy('a.id', $direction)->simplePaginate(20)->withQueryString()
            : new \Illuminate\Pagination\Paginator([], 20);
        return view('maps.activity-logs', compact('items', 'direction'));
    }

    public function show(int $id)
    {
        abort_unless(Schema::hasTable('activity_log'), 404);
        $item = DB::table('activity_log as a')
            ->leftJoin('users as u', fn ($join) => $join->on('u.id', '=', 'a.causer_id')->where('a.causer_type', User::class))
            ->select('a.*', 'u.name as causer_name')->where('a.id', $id)->first();
        abort_unless($item, 404);
        return view('maps.activity-log', compact('item'));
    }

    public function destroyBatch(Request $request)
    {
        $input = $request->validate([
            'selected' => 'required|array|min:1|max:20',
            'selected.*' => ['required', 'integer', 'distinct', Rule::exists('activity_log', 'id')],
        ]);
        $deleted = DB::transaction(fn () => DB::table('activity_log')->whereIn('id', $input['selected'])->delete());
        return redirect()->route('activity.logs.index')->with('status', "$deleted atividades excluídas.");
    }
}
