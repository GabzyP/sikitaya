<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with('store')
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'filters'));
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Akun admin tidak dapat dinonaktifkan dari halaman ini.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        if ($user->isSeller() && $user->store) {
            $user->store->update(['is_active' => $user->is_active]);
        }

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', 'Akun '.$user->name.' berhasil '.($user->is_active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }
}
